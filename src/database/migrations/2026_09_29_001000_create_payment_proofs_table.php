<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comprobantes de pago que el cliente sube (transferencia, depósito, etc.).
 * El admin/staff los revisa y aprueba/rechaza; luego emite la factura formal.
 * El comprobante puede ligarse opcionalmente a una factura existente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->unsignedBigInteger('amount_cents')->default(0);
            $table->string('currency', 3)->default('MXN');
            $table->string('method')->nullable();          // transfer/cash/card/...
            $table->date('paid_at')->nullable();
            $table->string('reference')->nullable();
            $table->string('status')->default('pending');  // pending/approved/rejected
            $table->text('review_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_proofs');
    }
};
