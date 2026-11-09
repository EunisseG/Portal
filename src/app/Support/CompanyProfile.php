<?php

namespace App\Support;

use App\Domain\Notifications\Models\Setting;
use Throwable;

/**
 * Datos fiscales/legales del EMISOR del portal (la empresa que cobra). Se usan
 * para pintar recibos/órdenes de pago estilo recibo. El IVA no se configura
 * aquí: viene en cada producto y queda guardado en el pedido y la orden.
 *
 * Son configurables en el almacén de settings; si aún no se configuró nada se
 * devuelven valores por defecto para no romper la UI.
 */
class CompanyProfile
{
    public const KEY_LEGAL_NAME = 'company.legal_name';
    public const KEY_TAX_ID = 'company.tax_id';
    public const KEY_ADDRESS = 'company.address';
    public const KEY_EMAIL = 'company.email';
    public const KEY_PHONE = 'company.phone';

    /** Lee un setting de forma resiliente (null si la tabla no existe aún). */
    private function read(string $key): ?string
    {
        try {
            $value = Setting::get($key);
        } catch (Throwable) {
            return null;
        }

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /** Razón social del emisor (cae al nombre de marca si no se configuró). */
    public function legalName(): string
    {
        return $this->read(self::KEY_LEGAL_NAME) ?? app(Branding::class)->name();
    }

    /** RFC / identificador fiscal del emisor (null si no se configuró). */
    public function taxId(): ?string
    {
        return $this->read(self::KEY_TAX_ID);
    }

    /** Domicilio fiscal del emisor (null si no se configuró). */
    public function address(): ?string
    {
        return $this->read(self::KEY_ADDRESS);
    }

    /** Correo de contacto del emisor (null si no se configuró). */
    public function email(): ?string
    {
        return $this->read(self::KEY_EMAIL);
    }

    /** Teléfono de contacto del emisor (null si no se configuró). */
    public function phone(): ?string
    {
        return $this->read(self::KEY_PHONE);
    }
}
