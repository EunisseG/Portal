<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Precios por ciclo de facturación de un producto recurrente (mismo esquema
 * que plan_prices). Un producto de pago único no tiene filas aquí.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('billing_cycle');          // BillingCycle
            $table->unsignedBigInteger('unit_price_cents')->default(0);
            $table->decimal('tax_rate', 5, 2)->default(16);
            $table->string('currency', 3)->default('MXN');
            $table->timestamps();

            $table->unique(['product_id', 'billing_cycle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }
};
