<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liga un comprobante de pago a la ORDEN de pago que cubre. Al aprobarse el
 * comprobante, la orden se marca como pagada y se genera la factura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->foreignId('payment_order_id')
                ->nullable()
                ->after('invoice_id')
                ->constrained('payment_orders')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->dropForeign(['payment_order_id']);
            $table->dropColumn('payment_order_id');
        });
    }
};
