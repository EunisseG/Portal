<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Respuestas a cada pregunta de la encuesta. Se cuelgan de la "cabecera"
 * (satisfaction_surveys, una por ticket). Para preguntas de escala se guarda el
 * número en value_number; para texto/sí-no, en value_text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('satisfaction_survey_id')->constrained('satisfaction_surveys')->cascadeOnDelete();
            $table->foreignId('survey_question_id')->constrained('survey_questions')->cascadeOnDelete();
            $table->unsignedTinyInteger('value_number')->nullable();   // escala 1..5
            $table->text('value_text')->nullable();                    // texto o sí/no
            $table->timestamps();

            $table->unique(['satisfaction_survey_id', 'survey_question_id'], 'survey_answer_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_answers');
    }
};
