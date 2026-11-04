<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SLA específico de un cliente por criticidad (override del catálogo global).
 * Un cliente puede tener override solo para algunas criticidades; las que no
 * defina usan la política global (sla_policies). Una fila por (cliente, criticidad).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_sla_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('criticality');
            $table->unsignedInteger('first_response_minutes');
            $table->unsignedInteger('resolution_minutes');
            $table->timestamps();

            $table->unique(['client_id', 'criticality']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_sla_policies');
    }
};
