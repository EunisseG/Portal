<?php

namespace Database\Factories;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Clients\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'folio' => 'WV-'.fake()->unique()->numberBetween(1000, 9999),
            'uuid' => null,
            'total_cents' => fake()->numberBetween(50000, 500000),
            'currency' => 'MXN',
            'status' => InvoiceStatus::Pending->value,
            'issued_at' => now()->subDays(5)->toDateString(),
            'due_at' => now()->addDays(25)->toDateString(),
            'pdf_path' => 'invoices/demo/'.fake()->uuid().'.pdf',
            'xml_path' => null,
            'notes' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => ['status' => InvoiceStatus::Paid->value]);
    }
}
