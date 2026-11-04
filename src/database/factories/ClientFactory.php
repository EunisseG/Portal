<?php

namespace Database\Factories;

use App\Domain\Clients\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => \App\Domain\Clients\Enums\ClientStatus::Active->value,
            'company' => fake()->company(),
            'phone' => fake()->phoneNumber(),
            'whatsapp' => fake()->phoneNumber(),
            'tax_id' => fake()->bothify('???######???'),
            'billing_address' => fake()->address(),
        ];
    }
}
