<?php

namespace App\Support;

use App\Domain\Notifications\Models\Setting;
use Throwable;

/**
 * Modo de operación del portal: define si ciertos flujos funcionan de forma
 * MANUAL (hoy) o AUTOMÁTICA (futuro, usando las integraciones). Son
 * interruptores independientes guardados en settings; por defecto, manual.
 *
 *  - Pagos: en manual el cliente sube comprobante y el admin lo aprueba; en
 *    automático el cliente podrá pagar en línea con una pasarela configurada.
 *  - Renovación de dominios: en manual el admin renueva a mano; en automático
 *    se renueva contra el proveedor (DomainGateway).
 */
class OperationMode
{
    public const KEY_PAYMENTS = 'ops.payments_mode';
    public const KEY_DOMAIN_RENEWAL = 'ops.domain_renewal_mode';

    public const MODE_MANUAL = 'manual';
    public const MODE_AUTOMATIC = 'automatic';

    /** Lee un modo de forma resiliente (manual si la tabla aún no existe). */
    private function mode(string $key): string
    {
        try {
            $value = Setting::get($key);
        } catch (Throwable) {
            return self::MODE_MANUAL;
        }

        return $value === self::MODE_AUTOMATIC ? self::MODE_AUTOMATIC : self::MODE_MANUAL;
    }

    public function paymentsMode(): string
    {
        return $this->mode(self::KEY_PAYMENTS);
    }

    public function paymentsAutomatic(): bool
    {
        return $this->paymentsMode() === self::MODE_AUTOMATIC;
    }

    public function domainRenewalMode(): string
    {
        return $this->mode(self::KEY_DOMAIN_RENEWAL);
    }

    public function domainRenewalAutomatic(): bool
    {
        return $this->domainRenewalMode() === self::MODE_AUTOMATIC;
    }
}
