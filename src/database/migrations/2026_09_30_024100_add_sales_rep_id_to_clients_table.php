<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liga cada cliente a un ejecutivo de ventas (gestor de cuenta). FK nullable:
 * un cliente puede no tener ejecutivo. nullOnDelete: si se borra el ejecutivo,
 * los clientes quedan sin asignar (no se borran).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('sales_rep_id')->nullable()->after('user_id')
                ->constrained('sales_reps')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_rep_id');
        });
    }
};
