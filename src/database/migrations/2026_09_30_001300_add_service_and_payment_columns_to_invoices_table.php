<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vincula la factura al pedido del que se generó (opcional, ya que las
 * facturas cargadas manualmente no tienen pedido) y agrega el seguimiento de
 * pagos: monto pagado acumulado para soportar pagos parciales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('service_id')
                ->nullable()
                ->after('client_id')
                ->constrained('services')
                ->nullOnDelete();

            $table->unsignedBigInteger('amount_paid_cents')->default(0)->after('total_cents');

            // El PDF deja de ser obligatorio: las facturas generadas desde un
            // pedido pueden no tener aún el documento fiscal cargado.
            $table->string('pdf_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_id');
            $table->dropColumn('amount_paid_cents');
        });
    }
};
