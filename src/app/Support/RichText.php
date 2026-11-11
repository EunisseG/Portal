<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Texto enriquecido de los mensajes de ticket (salida del editor Quill).
 *
 * El HTML que llega del navegador NO es confiable: se sanitiza con una lista
 * blanca estricta (etiquetas de formato básico, enlaces http/https/mailto y
 * solo las clases de formato de Quill). Los mensajes antiguos en texto plano
 * se siguen mostrando escapados, respetando sus saltos de línea.
 */
final class RichText
{
    /** Longitud máxima del texto visible (sin etiquetas). */
    public const MAX_TEXT_LENGTH = 5000;

    /** Longitud máxima del HTML crudo (el marcado ocupa más que el texto). */
    public const MAX_HTML_LENGTH = 30000;

    private static ?HtmlSanitizer $sanitizer = null;

    /** ¿El cuerpo viene del editor (HTML) o es texto plano? */
    public static function isHtml(?string $body): bool
    {
        return $body !== null
            && preg_match('#</?(p|br|strong|b|em|i|u|s|span|ul|ol|li|a)\b[^>]*>#i', $body) === 1;
    }

    /** Sanitiza HTML del editor con la lista blanca. */
    public static function sanitize(string $html): string
    {
        return trim(self::sanitizer()->sanitize($html));
    }

    /** Normaliza un cuerpo antes de guardarlo: HTML sanitizado o texto tal cual. */
    public static function normalize(string $body): string
    {
        return self::isHtml($body) ? self::sanitize($body) : $body;
    }

    /** HTML seguro para mostrar (se vuelve a sanitizar por defensa en profundidad). */
    public static function toHtml(?string $body): string
    {
        if ($body === null || $body === '') {
            return '';
        }

        if (self::isHtml($body)) {
            return self::sanitize($body);
        }

        return nl2br(e($body), false);
    }

    /** Texto visible sin etiquetas (para validar que no esté vacío y su longitud). */
    public static function plainText(?string $body): string
    {
        if ($body === null) {
            return '';
        }

        $text = self::isHtml($body)
            ? html_entity_decode(strip_tags(preg_replace('#<(br|/p|/li)\b[^>]*>#i', "\n", $body)), ENT_QUOTES | ENT_HTML5, 'UTF-8')
            : $body;

        // Quita espacios, incluidos los no separables (U+00A0).
        return trim(str_replace("\u{00A0}", ' ', $text));
    }

    private static function sanitizer(): HtmlSanitizer
    {
        if (self::$sanitizer !== null) {
            return self::$sanitizer;
        }

        $config = (new HtmlSanitizerConfig())
            ->allowElement('p', ['class'])
            ->allowElement('br')
            ->allowElement('strong')
            ->allowElement('b')
            ->allowElement('em')
            ->allowElement('i')
            ->allowElement('u')
            ->allowElement('s')
            ->allowElement('span', ['class'])
            ->allowElement('ul', ['class'])
            ->allowElement('ol', ['class'])
            ->allowElement('li', ['class'])
            ->allowElement('a', ['href'])
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowRelativeLinks(false)
            ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow')
            ->forceAttribute('a', 'target', '_blank')
            ->withAttributeSanitizer(self::quillClassSanitizer())
            ->withMaxInputLength(self::MAX_HTML_LENGTH * 2);

        return self::$sanitizer = new HtmlSanitizer($config);
    }

    /**
     * Solo deja pasar las clases de formato de Quill (alineación, fuente,
     * tamaño, sangría). Cualquier otra clase (p. ej. utilidades de Tailwind
     * que alteren la página) se descarta.
     */
    private static function quillClassSanitizer(): AttributeSanitizerInterface
    {
        return new class implements AttributeSanitizerInterface
        {
            private const ALLOWED = '/^ql-(align-(center|right|justify)|font-(serif|monospace)|size-(small|large|huge)|indent-[1-8])$/';

            public function getSupportedElements(): ?array
            {
                return null;
            }

            public function getSupportedAttributes(): ?array
            {
                return ['class'];
            }

            public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
            {
                $classes = array_filter(
                    preg_split('/\s+/', trim($value)) ?: [],
                    fn (string $class) => preg_match(self::ALLOWED, $class) === 1,
                );

                return $classes === [] ? null : implode(' ', $classes);
            }
        };
    }
}
