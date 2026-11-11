<?php

namespace App\Livewire\Admin\System;

use App\Domain\Notifications\Models\Setting;
use App\Support\Branding;
use App\Support\BrandedEmail;
use App\Support\CompanyProfile;
use App\Support\EmailTemplate;
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

    /** Datos del emisor (empresa que cobra) para recibos/órdenes de pago. */
    public string $companyLegalName = '';
    public string $companyTaxId = '';
    public string $companyAddress = '';
    public string $companyEmail = '';
    public string $companyPhone = '';

    /** Diseño de los correos (HTML con la marca). */
    public bool $emailHtmlEnabled = true;
    public string $emailAccent = EmailTemplate::DEFAULT_ACCENT;
    public string $emailFooter = '';

    /** Archivos nuevos a subir (null = sin cambios). */
    public $logo = null;
    public $favicon = null;

    public function mount(Branding $branding, CompanyProfile $company, EmailTemplate $email): void
    {
        Gate::authorize('settings.manage');

        $this->name = $branding->name();
        $this->emailHtmlEnabled = $email->enabled();
        $this->emailAccent = $email->accentColor();
        $this->emailFooter = (string) ($email->footerText() ?? '');

        // No usamos legalName() aquí (cae al nombre de marca); mostramos el
        // valor crudo para que el campo quede vacío si no se configuró.
        $this->companyLegalName = (string) (Setting::get(CompanyProfile::KEY_LEGAL_NAME) ?? '');
        $this->companyTaxId = (string) ($company->taxId() ?? '');
        $this->companyAddress = (string) ($company->address() ?? '');
        $this->companyEmail = (string) ($company->email() ?? '');
        $this->companyPhone = (string) ($company->phone() ?? '');
    }

    public function save(Branding $branding): void
    {
        Gate::authorize('settings.manage');

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'companyLegalName' => ['nullable', 'string', 'max:255'],
            'companyTaxId' => ['nullable', 'string', 'max:50'],
            'companyAddress' => ['nullable', 'string', 'max:500'],
            'companyEmail' => ['nullable', 'email', 'max:255'],
            'companyPhone' => ['nullable', 'string', 'max:50'],
            'emailHtmlEnabled' => ['boolean'],
            'emailAccent' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'emailFooter' => ['nullable', 'string', 'max:300'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
            'favicon' => ['nullable', 'image', 'mimes:png,ico,svg', 'max:512'],
        ], [], [
            'name' => 'nombre del portal',
            'companyLegalName' => 'razón social',
            'companyTaxId' => 'RFC',
            'companyAddress' => 'domicilio fiscal',
            'companyEmail' => 'correo del emisor',
            'companyPhone' => 'teléfono del emisor',
            'emailAccent' => 'color de los correos',
            'emailFooter' => 'pie de los correos',
            'logo' => 'logo',
            'favicon' => 'favicon',
        ]);

        Setting::put(Branding::KEY_NAME, trim($this->name));

        // Datos del emisor (se guardan en blanco -> null para que el recibo
        // simplemente los omita si no se configuraron).
        Setting::put(CompanyProfile::KEY_LEGAL_NAME, $this->nullableTrim($this->companyLegalName));
        Setting::put(CompanyProfile::KEY_TAX_ID, $this->nullableTrim($this->companyTaxId));
        Setting::put(CompanyProfile::KEY_ADDRESS, $this->nullableTrim($this->companyAddress));
        Setting::put(CompanyProfile::KEY_EMAIL, $this->nullableTrim($this->companyEmail));
        Setting::put(CompanyProfile::KEY_PHONE, $this->nullableTrim($this->companyPhone));

        // Diseño de los correos.
        Setting::put(EmailTemplate::KEY_ENABLED, $this->emailHtmlEnabled ? '1' : '0');
        Setting::put(EmailTemplate::KEY_ACCENT, strtolower($this->emailAccent));
        Setting::put(EmailTemplate::KEY_FOOTER, $this->nullableTrim($this->emailFooter));

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
        Gate::authorize('settings.manage');

        $this->deleteFile($branding->logoPath());
        Setting::put(Branding::KEY_LOGO_PATH, null);
        $this->logo = null;

        session()->flash('status', 'Logo eliminado.');
    }

    /** Elimina el favicon actual. */
    public function removeFavicon(Branding $branding): void
    {
        Gate::authorize('settings.manage');

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

    /** Devuelve el texto recortado, o null si queda vacío. */
    private function nullableTrim(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    public function render(Branding $branding, BrandedEmail $branded): View
    {
        // Vista previa con el color y pie que se están editando (aunque aún no
        // se guarden). El logo y el nombre son los guardados.
        $preview = $branded->render(
            'Tu factura F-1024 ya está disponible',
            "Hola Juan,\n\nTu factura F-1024 por 1,160.00 MXN ya está disponible en el portal.\nPuedes consultarla y descargarla desde tu cuenta.\n\nGracias por tu preferencia.",
            ['cta' => ['label' => 'Ver mi factura', 'url' => url('/')]],
            ['accent' => $this->emailAccent, 'footer' => trim($this->emailFooter) ?: null],
        );

        return view('livewire.admin.system.branding-settings', [
            'logoUrl' => $branding->logoUrl(),
            'faviconUrl' => $branding->faviconUrl(),
            'emailPreview' => $preview['html'],
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
