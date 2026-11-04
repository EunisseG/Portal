<?php

namespace Database\Factories;

use App\Domain\Services\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Server>
 */
class ServerFactory extends Factory
{
    protected $model = Server::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'srv-'.fake()->unique()->numberBetween(1, 9999),
            'hostname' => fake()->domainName(),
            'ip_address' => fake()->ipv4(),
            'notes' => null,
            'is_active' => true,
        ];
    }
}
