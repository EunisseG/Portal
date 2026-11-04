<?php

namespace App\Domain\Users\Services;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Users\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, email: string, password: string, roles?: list<string>, permissions?: list<string>}  $data
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'status' => UserStatus::Active->value,
            ]);

            if (! empty($data['roles'])) {
                $user->syncRoles($data['roles']);
            }

            // Permisos directos ("acceso personalizado"), adicionales al rol.
            if (array_key_exists('permissions', $data)) {
                $user->syncPermissions($data['permissions'] ?? []);
            }

            $this->audit->log(AuditAction::UserCreated, $user, null, [
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames()->all(),
                'permissions' => $user->getDirectPermissions()->pluck('name')->all(),
            ]);

            return $user;
        });
    }

    /**
     * @param  array{name: string, email: string, password?: ?string, roles?: list<string>, permissions?: list<string>}  $data
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $old = [
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames()->all(),
                'permissions' => $user->getDirectPermissions()->pluck('name')->all(),
            ];

            $user->name = $data['name'];
            $user->email = $data['email'];

            if (! empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }

            $user->save();

            if (array_key_exists('roles', $data)) {
                $user->syncRoles($data['roles'] ?? []);
            }

            // Permisos directos ("acceso personalizado"), adicionales al rol.
            if (array_key_exists('permissions', $data)) {
                $user->syncPermissions($data['permissions'] ?? []);
            }

            $this->audit->log(AuditAction::UserUpdated, $user, $old, [
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames()->all(),
                'permissions' => $user->getDirectPermissions()->pluck('name')->all(),
            ]);

            return $user;
        });
    }

    public function block(User $user): User
    {
        if ($user->isBlocked()) {
            return $user;
        }

        $user->forceFill([
            'status' => UserStatus::Blocked->value,
            'blocked_at' => now(),
        ])->save();

        $this->audit->log(AuditAction::UserBlocked, $user);

        return $user;
    }

    public function unblock(User $user): User
    {
        if (! $user->isBlocked()) {
            return $user;
        }

        $user->forceFill([
            'status' => UserStatus::Active->value,
            'blocked_at' => null,
        ])->save();

        $this->audit->log(AuditAction::UserUnblocked, $user);

        return $user;
    }
}
