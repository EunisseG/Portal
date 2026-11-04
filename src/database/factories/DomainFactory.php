<?php

namespace Database\Factories;

use App\Domain\Clients\Models\Client;
use App\Domain\Services\Enums\DomainStatus;
use App\Domain\Services\Models\Domain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Domain>
 */
class DomainFactory extends Factory
{
    protected $model = Domain::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'service_id' => null,
            'name' => fake()->unique()->domainName(),
            'status' => DomainStatus::Active->value,
            'registered_at' => now()->subYear()->toDateString(),
            'expires_at' => now()->addMonths(2)->toDateString(),
            'auto_renew' => false,
            'notes' => null,
        ];
    }
}
