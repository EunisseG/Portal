<?php

namespace App\Domain\Tickets\Enums;

/**
 * Tipo de una pregunta de encuesta: escala 1-5, texto libre o sí/no.
 */
enum SurveyQuestionType: string
{
    case Scale = 'scale';
    case Text = 'text';
    case Boolean = 'boolean';

    public function label(): string
    {
        return match ($this) {
            self::Scale => 'Escala 1 a 5',
            self::Text => 'Texto libre',
            self::Boolean => 'Sí / No',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
