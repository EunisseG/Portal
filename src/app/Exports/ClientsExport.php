<?php

namespace App\Exports;

use App\Domain\Clients\Models\Client;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ClientsExport implements FromQuery, WithHeadings, WithMapping
{
    /**
     * @param  ?string  $from  Fecha inicial (inclusive) por created_at, formato Y-m-d.
     * @param  ?string  $to    Fecha final (inclusive) por created_at, formato Y-m-d.
     * @param  ?string  $rep   Filtro por ejecutivo: null/''(todos), '0'(sin asignar) o id.
     */
    public function __construct(
        private readonly ?string $from = null,
        private readonly ?string $to = null,
        private readonly ?string $rep = null,
    ) {}

    public function query(): Builder
    {
        return Client::query()
            ->with('user', 'services.salesRep')
            ->when($this->from, fn ($q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('created_at', '<=', $this->to))
            ->when($this->rep !== null && $this->rep !== '', function ($q) {
                if ($this->rep === '0') {
                    $q->whereDoesntHave('services', fn ($s) => $s->whereNotNull('sales_rep_id'));
                } else {
                    $q->whereHas('services', fn ($s) => $s->where('sales_rep_id', (int) $this->rep));
                }
            })
            ->orderBy('id');
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['ID', 'Nombre', 'Correo', 'Empresa', 'Teléfono', 'WhatsApp', 'RFC', 'Dirección de facturación', 'Ejecutivo de ventas', 'Fecha de registro'];
    }

    /**
     * @param  Client  $client
     * @return list<mixed>
     */
    public function map($client): array
    {
        return [
            $client->id,
            $client->user?->name,
            $client->user?->email,
            $client->company,
            $client->phone,
            $client->whatsapp,
            $client->tax_id,
            $client->billing_address,
            $client->services->pluck('salesRep')->filter()->pluck('name')->unique()->implode(', '),
            $client->created_at?->format('Y-m-d H:i'),
        ];
    }
}
