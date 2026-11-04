<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de movimientos del saldo a favor del cliente. Cada movimiento es
 * un abono (credit) o un cargo (debit) con su motivo y quién lo registró.
 * El saldo del cliente es la suma de estos movimientos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_credit_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('type');                 // CreditMovementType: credit | debit
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3)->default('MXN');
            // Saldo resultante tras aplicar este movimiento (para trazabilidad).
            $table->unsignedBigInteger('balance_after_cents');
            $table->string('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_credit_movements');
    }
};
