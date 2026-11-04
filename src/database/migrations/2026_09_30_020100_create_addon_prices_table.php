<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Precios por ciclo de los complementos recurrentes (espejo de product_prices).
        Schema::create('addon_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('addon_catalog_item_id')->constrained()->cascadeOnDelete();
            $table->string('billing_cycle');          // BillingCycle
            $table->unsignedBigInteger('unit_price_cents')->default(0);
            $table->decimal('tax_rate', 5, 2)->default(16);
            $table->string('currency', 3)->default('MXN');
            $table->timestamps();

            $table->unique(['addon_catalog_item_id', 'billing_cycle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_prices');
    }
};
