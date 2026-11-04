<?php

namespace App\Exports;

use App\Domain\Services\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ServicesExport implements FromQuery, WithHeadings, WithMapping
{
    /**
     * @param  ?string  $from  Fecha inicial (inclusive) por created_at, formato Y-m-d.
     * @param  ?string  $to    Fecha final (inclusive) por created_at, formato Y-m-d.
     */
    public function __construct(
        private readonly ?string $type = null,
        private readonly ?int $clientId = null,
        private readonly ?string $from = null,
        private readonly ?string $to = null,
    ) {}

    public function query(): Builder
    {
        return Service::query()
            ->with('client.user')
            ->when($this->type, fn ($q) => $q->where('type', $this->type))
            ->when($this->clientId, fn ($q) => $q->where('client_id', $this->clientId))
            ->when($this->from, fn ($q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('created_at', '<=', $this->to))
            ->orderBy('id');
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['ID', 'Cliente', 'Servicio', 'Tipo', 'Estado', 'Ciclo', 'Total', 'Moneda', 'Inicia', 'Vence', 'Fecha de registro'];
    }

    /**
     * @param  Service  $service
     * @return list<mixed>
     */
    public function map($service): array
    {
        return [
            $service->id,
            $service->client?->user?->name,
            $service->name,
            $service->type?->label(),
            $service->status?->label(),
            $service->billing_cycle?->label(),
            number_format(($service->total_cents ?? 0) / 100, 2),
            $service->currency,
            $service->starts_at?->format('Y-m-d'),
            $service->expires_at?->format('Y-m-d'),
            $service->created_at?->format('Y-m-d H:i'),
        ];
    }
}
