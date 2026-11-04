<?php

namespace App\Domain\Tickets\Models;

use App\Domain\Tickets\Enums\SurveyQuestionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pregunta configurable de un formulario de encuesta.
 */
class SurveyQuestion extends Model
{
    protected $table = 'survey_questions';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'survey_form_id',
        'label',
        'type',
        'required',
        'sort_order',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SurveyQuestionType::class,
            'required' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(SurveyForm::class, 'survey_form_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(SurveyAnswer::class);
    }
}
