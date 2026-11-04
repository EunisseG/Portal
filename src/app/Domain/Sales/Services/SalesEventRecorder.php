<?php

namespace App\Domain\Sales\Services;

use App\Domain\Sales\Enums\SalesEventType;
use App\Domain\Sales\Models\SalesEvent;
use App\Domain\Services\Models\Addon;
use App\Domain\Services\Models\Domain;
use App\Domain\Services\Models\Service;
use Illuminate\Database\Eloquent\Model;

/**
 * Registra eventos comerciales (ventas nuevas y renovaciones) asociados a un
 * servicio, dominio o complemento. Resuelve el cliente y congela su ejecutivo
 * de ventas al momento del evento.
 *
 * Diseñado para que el registro NUNCA rompa el flujo de negocio: si algo falla
 * al resolver cliente/monto, simplemente no registra (el evento es analítico,
 * no transaccional crítico).
 */
class SalesEventRecorder
{
    /**
     * Registra una venta nueva para el subject dado. Idempotente: si ya existe
     * un evento new_sale para ese subject, no crea otro (seguro para backfill).
     */
    public function recordNewSale(Model $subject, ?\DateTimeInterface $occurredAt = null): ?SalesEvent
    {
        return $this->record(SalesEventType::NewSale, $subject, $occurredAt, onlyOnce: true);
    }

    /**
     * Registra una renovación para el subject dado. Permite múltiples (una por
     * cada renovación real).
     */
    public function recordRenewal(Model $subject, ?\DateTimeInterface $occurredAt = null): ?SalesEvent
    {
        return $this->record(SalesEventType::Renewal, $subject, $occurredAt, onlyOnce: false);
    }

    private function record(SalesEventType $type, Model $subject, ?\DateTimeInterface $occurredAt, bool $onlyOnce): ?SalesEvent
    {
        $client = $this->resolveClient($subject);
        if (! $client) {
            return null;
        }

        if ($onlyOnce && SalesEvent::query()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->where('type', $type->value)
            ->exists()
        ) {
            return null;
        }

        [$amountCents, $currency] = $this->resolveAmount($subject);

        return SalesEvent::create([
            'type' => $type->value,
            'client_id' => $client->id,
            // Ejecutivo congelado al momento del evento. Se toma del SERVICIO
            // (fuente única del ejecutivo); para dominios/complementos se sube
            // por su servicio.
            'sales_rep_id' => $this->resolveSalesRepId($subject),
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'occurred_at' => $occurredAt ?? now(),
        ]);
    }

    /**
     * Resuelve el ejecutivo del subject a partir de su servicio. Un dominio o
     * complemento hereda el ejecutivo de su servicio. Un dominio no ligado a un
     * servicio no tiene ejecutivo (null).
     */
    private function resolveSalesRepId(Model $subject): ?int
    {
        if ($subject instanceof Service) {
            return $subject->sales_rep_id;
        }
        if ($subject instanceof Addon) {
            return $subject->service?->sales_rep_id;
        }
        if ($subject instanceof Domain) {
            return $subject->service?->sales_rep_id;
        }

        return null;
    }

    /** Resuelve el cliente dueño del subject (servicio/dominio/complemento). */
    private function resolveClient(Model $subject): ?\App\Domain\Clients\Models\Client
    {
        if ($subject instanceof Service) {
            return $subject->client;
        }
        if ($subject instanceof Domain) {
            return $subject->client;
        }
        if ($subject instanceof Addon) {
            return $subject->service?->client;
        }

        return null;
    }

    /**
     * Resuelve el monto del evento según el tipo de subject.
     *
     * @return array{0: int, 1: string}  [amount_cents, currency]
     */
    private function resolveAmount(Model $subject): array
    {
        if ($subject instanceof Service) {
            return [(int) ($subject->total_cents ?? 0), $subject->currency ?? 'MXN'];
        }
        if ($subject instanceof Addon) {
            return [(int) ($subject->price_cents ?? 0), $subject->currency ?? 'MXN'];
        }
        // El dominio no tiene costo modelado en el sistema: monto 0 (el evento
        // sirve para el conteo de altas/renovaciones de dominios).
        return [0, 'MXN'];
    }
}
