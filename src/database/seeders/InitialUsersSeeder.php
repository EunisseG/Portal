<?php

namespace Database\Seeders;

use App\Domain\Auth\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Crea el usuario super administrador inicial a partir de variables de entorno.
 * Pensado para producción: no contiene credenciales quemadas y es idempotente
 * (si el usuario ya existe, solo se asegura de que tenga el rol).
 *
 * Variables en .env:
 *   SUPERADMIN_NAME="Nombre Apellido"
 *   SUPERADMIN_EMAIL="admin@tu-dominio.com"
 *   SUPERADMIN_PASSWORD="contraseña-fuerte"
 */
class InitialUsersSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('superadmin.email');
        $password = config('superadmin.password');
        $name = config('superadmin.name', 'Super administrador');

        if (empty($email) || empty($password)) {
            $this->command?->warn(
                'InitialUsersSeeder omitido: define SUPERADMIN_EMAIL y SUPERADMIN_PASSWORD en el .env.'
            );

            return;
        }

        $user = User::where('email', $email)->first();

        if ($user) {
            // No se sobreescribe la contraseña de un usuario existente.
            if (! $user->hasRole(RoleName::SuperAdmin->value)) {
                $user->assignRole(RoleName::SuperAdmin->value);
            }
            $this->command?->info('Super administrador ya existía: '.$email.' (rol verificado).');

            return;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);

        $user->assignRole(RoleName::SuperAdmin->value);

        $this->command?->info('Super administrador creado: '.$email);
    }
}
