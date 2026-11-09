<?php

namespace App\Support;

use App\Domain\Notifications\Models\Setting;
use Throwable;

/**
 * Apariencia editable de los correos del portal (color, pie de página y si se
 * envían con diseño HTML). Se lee del almacén de settings en el momento de
 * enviar, así que cualquier cambio —o un cambio de logo en Apariencia— se
 * refleja en el siguiente correo sin regenerar nada.
 */
class EmailTemplate
{
    public const KEY_ENABLED = 'email.html_enabled';
    public const KEY_ACCENT = 'email.accent_color';
    public const KEY_FOOTER = 'email.footer_text';

    public const DEFAULT_ACCENT = '#4f46e5';

    /** Lee un setting de forma resiliente (null si la tabla aún no existe). */
    private function read(string $key): ?string
    {
        try {
            $value = Setting::get($key);
        } catch (Throwable) {
            return null;
        }

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /** ¿Los correos se envían con diseño HTML? Activo salvo que se apague. */
    public function enabled(): bool
    {
        return $this->read(self::KEY_ENABLED) !== '0';
    }

    /** Color de acento (botón y cabecera) en formato #RRGGBB. */
    public function accentColor(): string
    {
        $value = $this->read(self::KEY_ACCENT);

        return $value !== null && self::isHexColor($value) ? strtolower($value) : self::DEFAULT_ACCENT;
    }

    /** Texto del pie de página (null si no se configuró). */
    public function footerText(): ?string
    {
        return $this->read(self::KEY_FOOTER);
    }

    public static function isHexColor(string $value): bool
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1;
    }

    /** Color de texto legible (blanco u oscuro) sobre el color de acento dado. */
    public static function contrastOn(string $hex): string
    {
        $hex = ltrim($hex, '#');
        [$r, $g, $b] = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];

        // Luminancia percibida (0–255).
        $luminance = (0.299 * $r) + (0.587 * $g) + (0.114 * $b);

        return $luminance > 160 ? '#0f172a' : '#ffffff';
    }
}
