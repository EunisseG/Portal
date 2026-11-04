<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Orden de pago en línea (folio de cobro). Es el intento de cobro por
        // pasarela; NO es la factura CFDI (esa lleva el sello del SAT).
        Schema::create('payment_orders', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            // Factura que cubre esta orden (si aplica). Si la factura se borra,
            // la orden queda sin factura pero conserva su rastro de cobro.
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3)->default('MXN');
            $table->string('status')->default('pending')->index(); // PaymentOrderStatus
            $table->string('gateway')->nullable();                 // p. ej. 'mercadopago'
            $table->string('external_reference')->nullable()->index(); // referencia enviada a la pasarela
            $table->string('preference_id')->nullable();           // id de preferencia/checkout
            $table->text('checkout_url')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_orders');
    }
};
