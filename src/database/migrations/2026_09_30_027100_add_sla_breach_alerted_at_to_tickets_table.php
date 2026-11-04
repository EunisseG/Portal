<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca cuándo se avisó por última vez de un incumplimiento de SLA en un
 * ticket, para que el comando programado `sla:check` no repita la alerta en
 * cada corrida. Se limpia cuando el ticket vuelve a estar en tiempo (no hay
 * incumplimiento vigente).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->timestamp('sla_breach_alerted_at')->nullable()->after('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropColumn('sla_breach_alerted_at');
        });
    }
};
