<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos para la gestión de SLA de tickets: criticidad (impacto en el negocio,
 * distinta de la prioridad) y los tiempos clave para medir el nivel de servicio
 * (primera respuesta del staff y resolución).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('criticality')->default('medium')->index()->after('priority');
            $table->timestamp('first_response_at')->nullable()->after('last_reply_at');
            $table->timestamp('resolved_at')->nullable()->after('first_response_at');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['criticality', 'first_response_at', 'resolved_at']);
        });
    }
};
