<?php

namespace App\Support;

use App\Domain\Notifications\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Lee la configuración de marca/apariencia del portal (nombre, logo, favicon)
 * guardada en el almacén de settings. Centraliza las claves y provee valores
 * por defecto para no romper la UI si aún no se configuró nada.
 */
class Branding
{
    public const KEY_NAME = 'branding.name';
    public const KEY_LOGO_PATH = 'branding.logo_path';
    public const KEY_FAVICON_PATH = 'branding.favicon_path';

    public const DEFAULT_NAME = 'Portal WORTEV';

    /**
     * Lee un setting de forma resiliente: si la tabla aún no existe (p. ej.
     * antes de migrar, o en páginas públicas sin BD lista) devuelve null en
     * lugar de lanzar una excepción, para no tumbar la UI.
     */
    private function read(string $key): ?string
    {
        try {
            $value = Setting::get($key);
        } catch (Throwable) {
            return null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** Nombre del portal (p. ej. para títulos y encabezados). */
    public function name(): string
    {
        $value = $this->read(self::KEY_NAME);

        return $value !== null && trim($value) !== '' ? $value : self::DEFAULT_NAME;
    }

    /** Ruta del logo en el disco público (null si no se configuró). */
    public function logoPath(): ?string
    {
        return $this->read(self::KEY_LOGO_PATH);
    }

    /** URL pública del logo, o null si no hay logo configurado. */
    public function logoUrl(): ?string
    {
        $path = $this->logoPath();

        return $path ? Storage::disk('public')->url($path) : null;
    }

    /** Ruta del favicon en el disco público (null si no se configuró). */
    public function faviconPath(): ?string
    {
        return $this->read(self::KEY_FAVICON_PATH);
    }

    /** URL pública del favicon, o null si no hay favicon configurado. */
    public function faviconUrl(): ?string
    {
        $path = $this->faviconPath();

        return $path ? Storage::disk('public')->url($path) : null;
    }
}
