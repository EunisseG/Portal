<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de eventos comerciales (ventas nuevas y renovaciones) para medir el
 * desempeño de los ejecutivos de ventas. El sales_rep_id se congela al momento
 * del evento; el subject (servicio/dominio/complemento) es polimórfico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_events', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);            // new_sale | renewal
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            // Ejecutivo congelado al momento del evento. nullOnDelete para que
            // borrar un ejecutivo no destruya el histórico (queda sin rep).
            $table->foreignId('sales_rep_id')->nullable()->constrained('sales_reps')->nullOnDelete();
            $table->nullableMorphs('subject');     // subject_type + subject_id
            $table->unsignedBigInteger('amount_cents')->default(0);
            $table->string('currency', 3)->default('MXN');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['sales_rep_id', 'type']);
            $table->index('occurred_at');
            // Evita duplicar la venta nueva del mismo subject (idempotencia del backfill).
            $table->index(['subject_type', 'subject_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_events');
    }
};
