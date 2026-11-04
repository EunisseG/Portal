<?php

namespace App\Domain\Notifications\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Elemento por vencer (servicio, dominio o complemento), normalizado para
 * la vista centralizada y los recordatorios.
 */
final class DueItem
{
    public function __construct(
        public readonly string $type,        // 'service' | 'domain' | 'addon'
        public readonly string $typeLabel,
        public readonly Model $model,        // instancia original
        public readonly string $name,        // nombre/identificador legible
        public readonly ?int $clientId,
        public readonly ?string $clientName,
        public readonly ?string $clientEmail,
        public readonly ?string $clientWhatsapp,
        public readonly Carbon $dueDate,
    ) {}

    public function daysLeft(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->dueDate->copy()->startOfDay(), false);
    }
}
