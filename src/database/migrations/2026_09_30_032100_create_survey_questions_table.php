<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preguntas configurables de un formulario de encuesta. Cada pregunta tiene un
 * tipo (escala 1-5, texto libre o sí/no), puede ser obligatoria, tiene un
 * orden y se puede activar/desactivar sin borrarla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('survey_form_id')->constrained('survey_forms')->cascadeOnDelete();
            $table->string('label');
            $table->string('type')->default('scale');   // scale | text | boolean
            $table->boolean('required')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['survey_form_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_questions');
    }
};
