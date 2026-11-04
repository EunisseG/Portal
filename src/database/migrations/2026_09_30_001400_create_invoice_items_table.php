<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Líneas de una factura. Se generan a partir del pedido (plan, complementos,
 * descuento) para que el total quede desglosado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price_cents')->default(0);
            // Importe de la línea (cantidad * unitario). Puede ser negativo en
            // el caso del descuento, por eso es entero con signo.
            $table->bigInteger('amount_cents')->default(0);
            $table->string('currency', 3)->default('MXN');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
