<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('billing_cycle');          // BillingCycle
            // Precio unitario (base, sin impuesto) en centavos.
            $table->unsignedBigInteger('unit_price_cents')->default(0);
            // Tasa de IVA en porcentaje (ej. 16.00).
            $table->decimal('tax_rate', 5, 2)->default(16);
            $table->string('currency', 3)->default('MXN');
            $table->timestamps();

            // Un plan solo puede tener un precio por ciclo.
            $table->unique(['plan_id', 'billing_cycle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_prices');
    }
};
