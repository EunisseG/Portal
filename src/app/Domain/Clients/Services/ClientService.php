<?php

namespace App\Domain\Clients\Services;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Clients\Models\Client;
use App\Domain\Notifications\Models\Reminder;
use App\Domain\Services\Models\Addon;
use App\Domain\Services\Models\Domain;
use App\Domain\Services\Models\Service;
use App\Domain\Users\Enums\UserStatus;
use App\Domain\Users\Services\UserService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClientService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SendAccountSetupLink $setupLink,
        private readonly UserService $users,
    ) {}

    /**
     * Cambia el estado administrativo del cliente. Suspender/cancelar bloquea
     * el acceso del titular; reactivar lo restaura. Devuelve el cliente.
     */
    public function setStatus(Client $client, ClientStatus $status): Client
    {
        $old = $client->status;

        if ($old === $status) {
            return $client;
        }

        return DB::transaction(function () use ($client, $status, $old): Client {
            $client->forceFill(['status' => $status->value])->save();

            // Sincroniza el acceso con el estado del cliente: afecta al titular
            // y a todos sus sub-usuarios (suspender/cancelar bloquea el acceso
            // de toda la cuenta; reactivar lo restaura).
            $accounts = collect([$client->user])
                ->concat($client->members)
                ->filter();

            foreach ($accounts as $account) {
                if ($status === ClientStatus::Active) {
                    $this->users->unblock($account);
                } else {
                    $this->users->block($account);
                }
            }

            $this->audit->log(AuditAction::ClientStatusChanged, $client,
                ['status' => $old?->value],
                ['status' => $status->value],
            );

            return $client;
        });
    }

    /**
     * Crea la cuenta de cliente completa: usuario con rol "client" + perfil Client.
     *
     * El alta desde el panel NO fija la contraseña: se genera una aleatoria y
     * se envía al cliente un enlace para que él establezca la suya.
     *
     * @param  array{
     *     name: string, email: string,
     *     company?: ?string, phone?: ?string, whatsapp?: ?string,
     *     tax_id?: ?string, billing_address?: ?string
     * }  $data
     */
    public function create(array $data): Client
    {
        $client = DB::transaction(function () use ($data): Client {
            $user = User::create([
                'name' => $data['name'],
                'last_name' => $data['last_name'] ?? null,
                'email' => $data['email'],
                'secondary_email' => $data['secondary_email'] ?? null,
                // Contraseña aleatoria: el cliente la reemplazará vía el enlace.
                'password' => Hash::make(Str::random(40)),
                'status' => UserStatus::Active->value,
                'email_verified_at' => now(),
            ]);

            $user->assignRole(RoleName::Client->value);

            $client = Client::create([
                'user_id' => $user->id,
                'company' => $data['company'] ?? null,
                'phone' => $data['phone'] ?? null,
                'whatsapp' => $data['whatsapp'] ?? null,
                'tax_id' => $data['tax_id'] ?? null,
                'billing_address' => $data['billing_address'] ?? null,
                'address_line1' => $data['address_line1'] ?? null,
                'address_line2' => $data['address_line2'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'country' => $data['country'] ?? null,
                'billing_contact' => $data['billing_contact'] ?? 'owner',
            ]);

            $this->audit->log(AuditAction::ClientCreated, $client, null, [
                'user' => ['name' => $user->name, 'email' => $user->email],
                'company' => $client->company,
            ]);

            return $client;
        });

        // Enviar el enlace para establecer contraseña (fuera de la transacción
        // para no revertir la creación si el correo fallara).
        $this->setupLink->send($client->user);

        return $client;
    }

    /**
     * @param  array{
     *     name: string, email: string,
     *     company?: ?string, phone?: ?string, whatsapp?: ?string,
     *     tax_id?: ?string, billing_address?: ?string
     * }  $data
     */
    public function update(Client $client, array $data): Client
    {
        return DB::transaction(function () use ($client, $data): Client {
            $user = $client->user;

            $old = [
                'user' => ['name' => $user->name, 'last_name' => $user->last_name, 'email' => $user->email],
                'company' => $client->company,
                'phone' => $client->phone,
                'whatsapp' => $client->whatsapp,
                'tax_id' => $client->tax_id,
                'billing_address' => $client->billing_address,
                'address_line1' => $client->address_line1,
                'address_line2' => $client->address_line2,
                'city' => $client->city,
                'state' => $client->state,
                'postal_code' => $client->postal_code,
                'country' => $client->country,
                'billing_contact' => $client->billing_contact,
            ];

            $user->forceFill([
                'name' => $data['name'],
                'last_name' => $data['last_name'] ?? null,
                'email' => $data['email'],
                'secondary_email' => $data['secondary_email'] ?? null,
            ])->save();

            $client->fill([
                'company' => $data['company'] ?? null,
                'phone' => $data['phone'] ?? null,
                'whatsapp' => $data['whatsapp'] ?? null,
                'tax_id' => $data['tax_id'] ?? null,
                'billing_address' => $data['billing_address'] ?? null,
                'address_line1' => $data['address_line1'] ?? null,
                'address_line2' => $data['address_line2'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'country' => $data['country'] ?? null,
                'billing_contact' => $data['billing_contact'] ?? 'owner',
            ]);

            $client->save();

            $this->audit->log(AuditAction::ClientUpdated, $client, $old, [
                'user' => ['name' => $user->name, 'last_name' => $user->last_name, 'email' => $user->email],
                'company' => $client->company,
                'phone' => $client->phone,
                'whatsapp' => $client->whatsapp,
                'tax_id' => $client->tax_id,
                'billing_address' => $client->billing_address,
                'address_line1' => $client->address_line1,
                'address_line2' => $client->address_line2,
                'city' => $client->city,
                'state' => $client->state,
                'postal_code' => $client->postal_code,
                'country' => $client->country,
                'billing_contact' => $client->billing_contact,
            ]);

            return $client;
        });
    }

    /**
     * Elimina por completo una cuenta de cliente: sus sub-usuarios, el usuario
     * titular y, por cascada de BD, su Client con servicios, dominios,
     * complementos, facturas, tickets, comprobantes, pagos y saldo. Además
     * limpia los recordatorios polimórficos (que no tienen FK) para no dejar
     * basura. Todo dentro de una transacción.
     */
    public function delete(Client $client): void
    {
        // Salvaguarda fiscal: no se puede borrar un cliente con facturas
        // timbradas (CFDI con UUID/folio del SAT); hay que conservarlas.
        if ($client->hasStampedInvoices()) {
            throw ValidationException::withMessages([
                'client' => 'No se puede eliminar: el cliente tiene facturas timbradas (CFDI). Debes conservar el histórico fiscal.',
            ]);
        }

        DB::transaction(function () use ($client): void {
            $client->loadMissing(['user', 'members', 'services', 'domains']);

            // 1) Limpia recordatorios polimórficos (sin FK) de las entidades que
            //    caerán en cascada: servicios, dominios y complementos.
            $this->purgeReminders($client);

            $user = $client->user;

            // 2) Auditoría ANTES de borrar (para conservar los datos).
            $this->audit->log(AuditAction::ClientDeleted, $client, [
                'user' => ['name' => $user?->name, 'email' => $user?->email],
                'company' => $client->company,
                'members' => $client->members->count(),
            ], null);

            // 3) Borra los sub-usuarios primero: su FK parent_client_id es
            //    nullOnDelete, así que quedarían huérfanos si no se borran.
            foreach ($client->members as $member) {
                $member->delete();
            }

            // 4) Borra el usuario titular: por cascadeOnDelete arrastra el
            //    Client y toda su rama (services, domains, invoices, tickets...).
            if ($user) {
                $user->delete();
            } else {
                $client->delete();
            }
        });
    }

    /** Elimina los recordatorios de los servicios, dominios y complementos del cliente. */
    private function purgeReminders(Client $client): void
    {
        $serviceIds = $client->services->pluck('id');
        $domainIds = $client->domains->pluck('id');
        $addonIds = Addon::whereIn('service_id', $serviceIds)->pluck('id');

        $targets = [
            [(new Service)->getMorphClass(), $serviceIds],
            [(new Domain)->getMorphClass(), $domainIds],
            [(new Addon)->getMorphClass(), $addonIds],
        ];

        foreach ($targets as [$type, $ids]) {
            if ($ids->isNotEmpty()) {
                Reminder::where('remindable_type', $type)
                    ->whereIn('remindable_id', $ids)
                    ->delete();
            }
        }
    }
}
