<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formularios de encuesta de satisfacción. Hay uno por tipo de destinatario:
 * 'client' (clientes) e 'internal' (empleados). Cada uno tiene título e
 * instrucciones editables y un conjunto de preguntas configurables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_forms', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->unique();          // client | internal
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_forms');
    }
};
