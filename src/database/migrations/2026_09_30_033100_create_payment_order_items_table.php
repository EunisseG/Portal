<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Líneas (conceptos) de una orden de pago. Se copian del pedido (plan base,
 * complementos y, si aplica, descuento) al crear la orden, para que el recibo
 * muestre el detalle aunque el pedido cambie después.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_order_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price_cents')->default(0);
            // Importe de la línea; negativo para líneas de descuento.
            $table->bigInteger('amount_cents')->default(0);
            $table->string('currency', 3)->default('MXN');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_order_items');
    }
};
