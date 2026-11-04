<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pagos registrados contra una factura. Soporta pagos parciales: una factura
 * puede tener varios pagos hasta cubrir su total.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            // Comprobante que originó este pago (si vino de la aprobación de uno).
            $table->foreignId('payment_proof_id')->nullable()->constrained('payment_proofs')->nullOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3)->default('MXN');
            $table->string('method');           // PaymentMethod
            $table->date('paid_at');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            // Usuario (staff/admin) que registró el pago.
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['invoice_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
