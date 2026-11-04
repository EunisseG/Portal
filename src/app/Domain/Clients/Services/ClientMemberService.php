<?php

namespace App\Domain\Clients\Services;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Clients\Models\Client;
use App\Domain\Users\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Gestión de sub-usuarios de una cuenta de cliente. El titular puede tener
 * como máximo un sub-usuario con permisos acotados del portal.
 */
class ClientMemberService
{
    /** Máximo de sub-usuarios por cliente. */
    public const MAX_MEMBERS = 1;

    public function __construct(private readonly SendAccountSetupLink $setupLink) {}

    /**
     * Crea el sub-usuario del cliente y le envía el enlace para establecer su
     * contraseña. Lanza ValidationException si ya se alcanzó el límite.
     *
     * @param  array{name:string, email:string, permissions?: list<string>}  $data
     */
    public function create(Client $client, array $data): User
    {
        if ($client->members()->count() >= self::MAX_MEMBERS) {
            throw ValidationException::withMessages([
                'member' => 'Este cliente ya alcanzó el máximo de sub-usuarios permitidos ('.self::MAX_MEMBERS.').',
            ]);
        }

        $member = DB::transaction(function () use ($client, $data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make(Str::random(40)),
                'status' => UserStatus::Active->value,
                'email_verified_at' => now(),
                'parent_client_id' => $client->id,
            ]);

            $user->assignRole(RoleName::Client->value);
            $user->syncPermissions($this->sanitizePermissions($data['permissions'] ?? []));

            return $user;
        });

        $this->setupLink->send($member);

        return $member;
    }

    /**
     * Actualiza nombre y permisos del sub-usuario.
     *
     * @param  array{name:string, permissions?: list<string>}  $data
     */
    public function update(User $member, array $data): User
    {
        $member->forceFill(['name' => $data['name']])->save();
        $member->syncPermissions($this->sanitizePermissions($data['permissions'] ?? []));

        return $member;
    }

    public function block(User $member): void
    {
        $member->update(['status' => UserStatus::Blocked->value]);
    }

    public function unblock(User $member): void
    {
        $member->update(['status' => UserStatus::Active->value]);
    }

    public function delete(User $member): void
    {
        $member->delete();
    }

    /**
     * Deja solo permisos válidos del conjunto permitido para sub-usuarios.
     *
     * @param  list<string>  $permissions
     * @return list<string>
     */
    private function sanitizePermissions(array $permissions): array
    {
        return array_values(array_intersect(
            $permissions,
            RolesAndPermissionsSeeder::CLIENT_MEMBER_PERMISSIONS,
        ));
    }
}
