<?php

namespace App\Imports;

use App\Domain\Clients\Models\Client;
use App\Domain\Services\Enums\BillingCycle;
use App\Domain\Services\Enums\ServiceStatus;
use App\Domain\Services\Enums\ServiceType;
use App\Domain\Services\Models\Service;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Importa servicios desde un archivo Excel. Cada fila se asocia a un cliente
 * existente por su correo. Si el cliente no existe, la fila se omite.
 *
 * Encabezados esperados (fila 1): cliente_correo, servicio, tipo, ciclo,
 * total, moneda, inicia, vence.
 */
class ServicesImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public int $created = 0;

    public int $skipped = 0;

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $email = trim((string) ($row['cliente_correo'] ?? ''));
            $name = trim((string) ($row['servicio'] ?? ''));

            $client = $email !== ''
                ? Client::whereHas('user', fn ($q) => $q->where('email', $email))->first()
                : null;

            if (! $client || $name === '') {
                $this->skipped++;

                continue;
            }

            $totalCents = (int) round(((float) ($row['total'] ?? 0)) * 100);

            Service::create([
                'client_id' => $client->id,
                'type' => $this->resolveType($row['tipo'] ?? null)->value,
                'name' => $name,
                'status' => ServiceStatus::Active->value,
                'billing_cycle' => $this->resolveCycle($row['ciclo'] ?? null)->value,
                'unit_price_cents' => $totalCents,
                'tax_rate' => 0,
                'total_cents' => $totalCents,
                'price_cents' => $totalCents,
                'currency' => trim((string) ($row['moneda'] ?? 'MXN')) ?: 'MXN',
                'starts_at' => $this->nullable($row['inicia'] ?? null),
                'expires_at' => $this->nullable($row['vence'] ?? null),
            ]);

            $this->created++;
        }
    }

    private function resolveType(mixed $value): ServiceType
    {
        $value = strtolower(trim((string) ($value ?? '')));

        foreach (ServiceType::cases() as $case) {
            if ($value === $case->value || $value === strtolower($case->label())) {
                return $case;
            }
        }

        return ServiceType::Other;
    }

    private function resolveCycle(mixed $value): BillingCycle
    {
        $value = strtolower(trim((string) ($value ?? '')));

        foreach (BillingCycle::cases() as $case) {
            if ($value === $case->value || $value === strtolower($case->label())) {
                return $case;
            }
        }

        return BillingCycle::Annual;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
