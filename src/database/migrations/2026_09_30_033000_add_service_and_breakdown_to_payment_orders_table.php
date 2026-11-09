<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_orders', function (Blueprint $table) {
            // Pedido (Service) que origina esta orden de pago. Si el pedido se
            // borra, la orden conserva su rastro de cobro (service_id = null).
            $table->foreignId('service_id')
                ->nullable()
                ->after('client_id')
                ->constrained('services')
                ->nullOnDelete();

            // Desglose congelado al crear la orden (se copia del pedido). Así el
            // recibo no cambia si el pedido se modifica después. amount_cents
            // (ya existente) sigue siendo el total a cobrar.
            $table->unsignedBigInteger('subtotal_cents')->default(0)->after('amount_cents');
            $table->unsignedBigInteger('discount_cents')->default(0)->after('subtotal_cents');
            $table->unsignedBigInteger('tax_cents')->default(0)->after('discount_cents');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('tax_cents');

            // Concepto/título legible para el recibo y el checkout.
            $table->string('concept')->nullable()->after('tax_rate');
        });
    }

    public function down(): void
    {
        Schema::table('payment_orders', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
            $table->dropColumn([
                'service_id',
                'subtotal_cents',
                'discount_cents',
                'tax_cents',
                'tax_rate',
                'concept',
            ]);
        });
    }
};
