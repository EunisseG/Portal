<?php

namespace App\Livewire\Admin\System;

use App\Domain\Notifications\Models\Setting;
use App\Support\Branding;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Configuración de marca/apariencia del portal: nombre, logo y favicon.
 * El nombre vive en settings; las imágenes se guardan en el disco público y
 * se referencian por su ruta. Reutiliza el permiso de integraciones/sistema.
 */
class BrandingSettings extends Component
{
    use WithFileUploads;

    public string $name = '';

    /** Archivos nuevos a subir (null = sin cambios). */
    public $logo = null;
    public $favicon = null;

    public function mount(Branding $branding): void
    {
        Gate::authorize('integrations.manage');

        $this->name = $branding->name();
    }

    public function save(Branding $branding): void
    {
        Gate::authorize('integrations.manage');

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
            'favicon' => ['nullable', 'image', 'mimes:png,ico,svg', 'max:512'],
        ], [], [
            'name' => 'nombre del portal',
            'logo' => 'logo',
            'favicon' => 'favicon',
        ]);

        Setting::put(Branding::KEY_NAME, trim($this->name));

        if ($this->logo instanceof TemporaryUploadedFile) {
            $this->replaceFile($branding->logoPath(), $this->logo, Branding::KEY_LOGO_PATH);
            $this->logo = null;
        }

        if ($this->favicon instanceof TemporaryUploadedFile) {
            $this->replaceFile($branding->faviconPath(), $this->favicon, Branding::KEY_FAVICON_PATH);
            $this->favicon = null;
        }

        session()->flash('status', 'Apariencia actualizada.');
    }

    /** Elimina el logo actual (vuelve al nombre en texto). */
    public function removeLogo(Branding $branding): void
    {
        Gate::authorize('integrations.manage');

        $this->deleteFile($branding->logoPath());
        Setting::put(Branding::KEY_LOGO_PATH, null);
        $this->logo = null;

        session()->flash('status', 'Logo eliminado.');
    }

    /** Elimina el favicon actual. */
    public function removeFavicon(Branding $branding): void
    {
        Gate::authorize('integrations.manage');

        $this->deleteFile($branding->faviconPath());
        Setting::put(Branding::KEY_FAVICON_PATH, null);
        $this->favicon = null;

        session()->flash('status', 'Favicon eliminado.');
    }

    /** Guarda el archivo nuevo (borrando el anterior) y actualiza su setting. */
    private function replaceFile(?string $currentPath, TemporaryUploadedFile $file, string $settingKey): void
    {
        $this->deleteFile($currentPath);
        $path = $file->store('branding', 'public');
        Setting::put($settingKey, $path);
    }

    private function deleteFile(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    public function render(Branding $branding): View
    {
        return view('livewire.admin.system.branding-settings', [
            'logoUrl' => $branding->logoUrl(),
            'faviconUrl' => $branding->faviconUrl(),
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
