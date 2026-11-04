<?php

namespace Database\Seeders;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Clients\Models\Client;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Genera clientes de ejemplo para desarrollo (datos ficticios).
 */
class ClientsDemoSeeder extends Seeder
{
    public function run(): void
    {
        Client::factory()
            ->count(15)
            ->create()
            ->each(function (Client $client): void {
                /** @var User $user */
                $user = $client->user;
                $user->assignRole(RoleName::Client->value);
            });
    }
}
