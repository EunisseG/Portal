<?php

namespace App\Imports;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Clients\Models\Client;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Importa clientes desde un archivo Excel. Cada fila crea (o actualiza por
 * correo) un usuario con rol "client" y su perfil de cliente.
 *
 * Encabezados esperados (fila 1): nombre, correo, empresa, telefono, whatsapp,
 * rfc, direccion.
 */
class ClientsImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public int $created = 0;

    public int $updated = 0;

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $email = trim((string) ($row['correo'] ?? ''));
            $name = trim((string) ($row['nombre'] ?? ''));

            if ($email === '' || $name === '') {
                continue;
            }

            $existing = User::where('email', $email)->first();

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => $existing?->password ?? Hash::make(Str::random(32)),
                ],
            );

            if (! $user->hasRole(RoleName::Client->value)) {
                $user->assignRole(RoleName::Client->value);
            }

            Client::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'company' => $this->nullable($row['empresa'] ?? null),
                    'phone' => $this->nullable($row['telefono'] ?? null),
                    'whatsapp' => $this->nullable($row['whatsapp'] ?? null),
                    'tax_id' => $this->nullable($row['rfc'] ?? null),
                    'billing_address' => $this->nullable($row['direccion'] ?? null),
                ],
            );

            $existing ? $this->updated++ : $this->created++;
        }
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
