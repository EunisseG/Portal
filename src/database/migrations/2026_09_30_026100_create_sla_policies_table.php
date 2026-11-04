<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Políticas de SLA por criticidad: minutos objetivo de primera respuesta y de
 * resolución. Una fila por cada nivel de criticidad. Editable desde el panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_policies', function (Blueprint $table) {
            $table->id();
            // Sin unique simple: el SLA se separa por (type, criticality).
            // Ver migración 2026_09_30_031100 que añade 'type' y el unique compuesto.
            $table->string('criticality');                   // TicketCriticality
            $table->unsignedInteger('first_response_minutes');
            $table->unsignedInteger('resolution_minutes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_policies');
    }
};
