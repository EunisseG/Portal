<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Renderiza Markdown a HTML de forma segura:
 * - html_input=escape: el HTML crudo dentro del contenido se escapa (evita XSS).
 * - allow_unsafe_links=false: bloquea enlaces peligrosos (javascript:, etc.).
 *
 * Las imágenes en sintaxis Markdown ![alt](url) sí se renderizan como <img>.
 */
final class Markdown
{
    public static function toHtml(?string $markdown): string
    {
        if ($markdown === null || trim($markdown) === '') {
            return '';
        }

        return Str::markdown($markdown, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }
}
