<?php

namespace App\Support;

/**
 * Convierte el texto de un correo en un mensaje HTML con la marca del portal
 * (logo, nombre, color y pie editables) más su versión en texto plano.
 *
 * Los jobs siguen entregando texto plano; aquí se le da el diseño. Todo el
 * contenido se escapa: el texto nunca se interpreta como HTML.
 *
 * Opciones reconocidas en $options:
 *  - 'cta' => ['label' => 'Ver mi factura', 'url' => 'https://…']  botón de acción
 */
class BrandedEmail
{
    public function __construct(
        private readonly EmailTemplate $style,
        private readonly Branding $branding,
        private readonly CompanyProfile $company,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @param  array{accent?:string, footer?:?string}|null  $override  estilo temporal (vista previa)
     * @return array{html: string, text: string}
     */
    public function render(string $subject, string $body, array $options = [], ?array $override = null): array
    {
        $accent = $override['accent'] ?? $this->style->accentColor();
        if (! EmailTemplate::isHexColor($accent)) {
            $accent = EmailTemplate::DEFAULT_ACCENT;
        }
        $footer = array_key_exists('footer', $override ?? []) ? $override['footer'] : $this->style->footerText();

        $cta = $this->normalizeCta($options['cta'] ?? null);

        $html = view('emails.branded', [
            'subject' => $subject,
            'paragraphs' => $this->paragraphs($body),
            'cta' => $cta,
            'brandName' => $this->branding->name(),
            'logoUrl' => $this->absoluteUrl($this->branding->logoUrl()),
            'accent' => $accent,
            'accentText' => EmailTemplate::contrastOn($accent),
            'footer' => $footer !== null && trim((string) $footer) !== '' ? trim((string) $footer) : null,
            'companyName' => $this->company->legalName(),
            'companyAddress' => $this->company->address(),
        ])->render();

        $text = $body;
        if ($cta) {
            $text .= "\n\n".$cta['label'].': '.$cta['url'];
        }

        return ['html' => $html, 'text' => $text];
    }

    /**
     * Separa el texto en párrafos (línea en blanco) y escapa cada uno,
     * conservando los saltos de línea y convirtiendo las URL en enlaces.
     *
     * @return list<string> HTML ya seguro
     */
    private function paragraphs(string $body): array
    {
        $blocks = preg_split("/\R{2,}/", trim($body)) ?: [];

        return array_values(array_filter(array_map(function (string $block) {
            $block = trim($block);
            if ($block === '') {
                return null;
            }

            $escaped = e($block);

            // URL → enlace (sin arrastrar puntuación final).
            $escaped = preg_replace(
                '~(https?://[^\s<]+?)([.,;:!?)]*)(?=\s|<|$)~i',
                '<a href="$1" style="color:inherit;text-decoration:underline;">$1</a>$2',
                $escaped,
            );

            return nl2br($escaped, false);
        }, $blocks)));
    }

    /**
     * Valida el botón de acción: solo enlaces http(s).
     *
     * @return array{label:string, url:string}|null
     */
    private function normalizeCta(mixed $cta): ?array
    {
        if (! is_array($cta) || empty($cta['label']) || empty($cta['url'])) {
            return null;
        }

        $url = (string) $cta['url'];
        if (preg_match('~^https?://~i', $url) !== 1) {
            return null;
        }

        return ['label' => (string) $cta['label'], 'url' => $url];
    }

    /** Los clientes de correo necesitan URL absolutas para mostrar el logo. */
    private function absoluteUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        return preg_match('~^https?://~i', $url) === 1 ? $url : url($url);
    }
}
