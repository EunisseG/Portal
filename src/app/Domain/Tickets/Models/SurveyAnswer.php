<?php

namespace App\Domain\Tickets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Respuesta a una pregunta concreta de la encuesta. value_number para escalas
 * (1..5); value_text para texto o sí/no.
 */
class SurveyAnswer extends Model
{
    protected $table = 'survey_answers';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'satisfaction_survey_id',
        'survey_question_id',
        'value_number',
        'value_text',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value_number' => 'integer',
        ];
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(SatisfactionSurvey::class, 'satisfaction_survey_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(SurveyQuestion::class, 'survey_question_id');
    }
}
