<?php

namespace Database\Seeders;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Clients\Models\Client;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Usuarios de demostración para desarrollo. Contraseñas de ejemplo, NO reales.
 * No usar en producción.
 */
class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin@wortev.test'],
            [
                'name' => 'Super Administrador',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'status' => 'active',
            ],
        );
        $superAdmin->syncRoles([RoleName::SuperAdmin->value]);

        $admin = User::updateOrCreate(
            ['email' => 'admin@wortev.test'],
            [
                'name' => 'Administrador Demo',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'status' => 'active',
            ],
        );
        $admin->syncRoles([RoleName::Admin->value]);

        $client = User::updateOrCreate(
            ['email' => 'cliente@wortev.test'],
            [
                'name' => 'Cliente Demo',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'status' => 'active',
            ],
        );
        $client->syncRoles([RoleName::Client->value]);

        Client::updateOrCreate(
            ['user_id' => $client->id],
            [
                'company' => 'Empresa Demo S.A. de C.V.',
                'phone' => '+52 55 0000 0000',
                'whatsapp' => '+52 55 0000 0000',
            ],
        );
    }
}
