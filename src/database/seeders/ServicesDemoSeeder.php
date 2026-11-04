<?php

namespace Database\Seeders;

use App\Domain\Clients\Models\Client;
use App\Domain\Services\Enums\AddonStatus;
use App\Domain\Services\Enums\DomainStatus;
use App\Domain\Services\Enums\ServiceStatus;
use App\Domain\Services\Enums\ServiceType;
use App\Domain\Services\Models\Addon;
use App\Domain\Services\Models\Domain;
use App\Domain\Services\Models\Server;
use App\Domain\Services\Models\Service;
use Illuminate\Database\Seeder;

/**
 * Datos de ejemplo del módulo de servicios (desarrollo).
 */
class ServicesDemoSeeder extends Seeder
{
    public function run(): void
    {
        $servers = Server::factory()->count(2)->create();

        // Cliente demo principal (creado en DemoUsersSeeder).
        $demoClient = Client::whereHas('user', fn ($q) => $q->where('email', 'cliente@wortev.test'))->first();

        if ($demoClient) {
            $this->seedForClient($demoClient, $servers->first());
        }

        // Algunos clientes más con servicios y dominios.
        Client::query()->inRandomOrder()->limit(5)->get()->each(function (Client $client) use ($servers) {
            $this->seedForClient($client, $servers->random());
        });
    }

    private function seedForClient(Client $client, ?Server $server): void
    {
        $hosting = Service::factory()->create([
            'client_id' => $client->id,
            'server_id' => $server?->id,
            'type' => ServiceType::Hosting->value,
            'name' => 'Hosting Pro',
            'status' => ServiceStatus::Active->value,
        ]);

        Addon::factory()->create([
            'service_id' => $hosting->id,
            'name' => 'Backup diario',
            'status' => AddonStatus::Active->value,
        ]);

        Domain::factory()->create([
            'client_id' => $client->id,
            'service_id' => $hosting->id,
            'status' => DomainStatus::Active->value,
        ]);
    }
}
