<?php

namespace App\Domain\Tickets\Models;

use App\Domain\Tickets\Enums\TicketType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Formulario de encuesta de satisfacción por tipo de destinatario (cliente o
 * empleado). Agrupa sus preguntas configurables.
 */
class SurveyForm extends Model
{
    protected $table = 'survey_forms';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'title',
        'instructions',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TicketType::class,
        ];
    }

    public function questions(): HasMany
    {
        return $this->hasMany(SurveyQuestion::class)->orderBy('sort_order')->orderBy('id');
    }

    /** Preguntas activas, en orden (las que ve quien responde). */
    public function activeQuestions(): HasMany
    {
        return $this->questions()->where('is_active', true);
    }

    /** Devuelve (o crea vacío) el formulario de un tipo. */
    public static function forType(TicketType $type): self
    {
        return static::firstOrCreate(
            ['type' => $type->value],
            ['title' => 'Encuesta de satisfacción'],
        );
    }
}
