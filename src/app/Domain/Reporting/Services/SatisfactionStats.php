<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Tickets\Enums\TicketType;
use App\Domain\Tickets\Models\SatisfactionSurvey;

/**
 * Métricas de la encuesta de satisfacción (CSAT). Todas las consultas se
 * pueden acotar por tipo de ticket ('client' | 'internal'); sin tipo, agrega
 * ambos.
 */
class SatisfactionStats
{
    /**
     * Distribución de respuestas por puntuación (1..5).
     *
     * @return array{labels: list<string>, data: list<int>}
     */
    public function distribution(?TicketType $type = null): array
    {
        $counts = SatisfactionSurvey::query()
            ->whereNotNull('responded_at')
            ->when($type, fn ($q) => $q->where('type', $type->value))
            ->selectRaw('score, count(*) as total')
            ->groupBy('score')
            ->pluck('total', 'score');

        $labels = [];
        $data = [];

        for ($score = 1; $score <= 5; $score++) {
            $labels[] = $score.' ★';
            $data[] = (int) ($counts[$score] ?? 0);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * Promedio de satisfacción (redondeado a 2 decimales).
     */
    public function average(?TicketType $type = null): float
    {
        return round((float) SatisfactionSurvey::query()
            ->whereNotNull('responded_at')
            ->when($type, fn ($q) => $q->where('type', $type->value))
            ->avg('score'), 2);
    }

    /**
     * Total de encuestas respondidas.
     */
    public function answered(?TicketType $type = null): int
    {
        return SatisfactionSurvey::query()
            ->whereNotNull('responded_at')
            ->when($type, fn ($q) => $q->where('type', $type->value))
            ->count();
    }

    /**
     * Total de encuestas pendientes de responder.
     */
    public function pending(?TicketType $type = null): int
    {
        return SatisfactionSurvey::query()
            ->whereNull('responded_at')
            ->when($type, fn ($q) => $q->where('type', $type->value))
            ->count();
    }

    /**
     * Desglose de resultados por cada pregunta activa del formulario del tipo.
     * Para escalas: promedio (1..5) y nº de respuestas. Para texto: nº de
     * comentarios y los más recientes. Para sí/no: conteo de cada opción.
     *
     * @return list<array<string, mixed>>
     */
    public function questionBreakdown(TicketType $type): array
    {
        $form = \App\Domain\Tickets\Models\SurveyForm::forType($type);
        $questions = $form->activeQuestions()->get();

        $rows = [];

        foreach ($questions as $question) {
            $answers = \App\Domain\Tickets\Models\SurveyAnswer::query()
                ->where('survey_question_id', $question->id)
                ->whereHas('survey', fn ($q) => $q->whereNotNull('responded_at'))
                ->get();

            $row = [
                'label' => $question->label,
                'type' => $question->type->value,
                'type_label' => $question->type->label(),
                'count' => 0,
            ];

            if ($question->type === \App\Domain\Tickets\Enums\SurveyQuestionType::Scale) {
                $values = $answers->pluck('value_number')->filter(fn ($v) => $v !== null);
                $row['count'] = $values->count();
                $row['average'] = $values->count() > 0 ? round($values->avg(), 2) : null;
            } elseif ($question->type === \App\Domain\Tickets\Enums\SurveyQuestionType::Boolean) {
                $texts = $answers->pluck('value_text')->filter(fn ($v) => $v !== null && $v !== '');
                $row['count'] = $texts->count();
                $row['yes'] = $texts->filter(fn ($v) => $v === 'Sí')->count();
                $row['no'] = $texts->filter(fn ($v) => $v === 'No')->count();
            } else { // Text
                $texts = $answers->pluck('value_text')->filter(fn ($v) => $v !== null && $v !== '');
                $row['count'] = $texts->count();
                $row['recent'] = $texts->take(5)->values()->all();
            }

            $rows[] = $row;
        }

        return $rows;
    }
}
