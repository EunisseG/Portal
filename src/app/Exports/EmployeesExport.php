<?php

namespace App\Exports;

use App\Domain\Auth\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Exporta los empleados internos (usuarios con rol "employee").
 */
class EmployeesExport implements FromQuery, WithHeadings, WithMapping
{
    /**
     * @param  ?string  $from  Fecha inicial (inclusive) por created_at, Y-m-d.
     * @param  ?string  $to    Fecha final (inclusive) por created_at, Y-m-d.
     * @param  ?string  $area  Filtro por área: null/'' (todas) o id.
     */
    public function __construct(
        private readonly ?string $from = null,
        private readonly ?string $to = null,
        private readonly ?string $area = null,
    ) {}

    public function query(): Builder
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', RoleName::Employee->value))
            ->with('internalArea')
            ->when($this->from, fn ($q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('created_at', '<=', $this->to))
            ->when($this->area !== null && $this->area !== '', fn ($q) => $q->where('internal_area_id', (int) $this->area))
            ->orderBy('id');
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['ID', 'Nº de empleado', 'Nombre', 'Apellido', 'Correo', 'Área', 'Fecha de alta'];
    }

    /**
     * @param  User  $user
     * @return list<mixed>
     */
    public function map($user): array
    {
        return [
            $user->id,
            $user->employee_number,
            $user->name,
            $user->last_name,
            $user->email,
            $user->internalArea?->name,
            $user->created_at?->format('Y-m-d H:i'),
        ];
    }
}
