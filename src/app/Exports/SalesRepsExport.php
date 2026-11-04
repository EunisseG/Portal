<?php

namespace App\Exports;

use App\Domain\Sales\Models\SalesRep;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Exporta el catálogo de ejecutivos de ventas con el número de servicios que
 * lleva cada uno.
 */
class SalesRepsExport implements FromQuery, WithHeadings, WithMapping
{
    /**
     * @param  ?string  $from    Fecha inicial (inclusive) por created_at, Y-m-d.
     * @param  ?string  $to      Fecha final (inclusive) por created_at, Y-m-d.
     * @param  ?string  $status  null/'' (todos), 'active' o 'inactive'.
     */
    public function __construct(
        private readonly ?string $from = null,
        private readonly ?string $to = null,
        private readonly ?string $status = null,
    ) {}

    public function query(): Builder
    {
        return SalesRep::query()
            ->withCount('services')
            ->when($this->from, fn ($q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('created_at', '<=', $this->to))
            ->when($this->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('id');
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['ID', 'Nombre', 'Correo', 'Teléfono', 'Servicios', 'Estado', 'Fecha de alta'];
    }

    /**
     * @param  SalesRep  $rep
     * @return list<mixed>
     */
    public function map($rep): array
    {
        return [
            $rep->id,
            $rep->name,
            $rep->email,
            $rep->phone,
            $rep->services_count,
            $rep->is_active ? 'Activo' : 'Inactivo',
            $rep->created_at?->format('Y-m-d H:i'),
        ];
    }
}
