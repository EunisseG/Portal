<?php

namespace Database\Seeders;

use App\Domain\Tickets\Enums\SurveyQuestionType;
use App\Domain\Tickets\Enums\TicketType;
use App\Domain\Tickets\Models\SurveyForm;
use Illuminate\Database\Seeder;

/**
 * Formularios de encuesta por defecto (cliente y empleado), cada uno con una
 * pregunta de escala (satisfacción general) y una de comentario. Idempotente:
 * no duplica si ya existen. El admin los edita luego desde el panel.
 */
class SurveyFormsSeeder extends Seeder
{
    public function run(): void
    {
        $forms = [
            TicketType::Client->value => 'Encuesta de satisfacción',
            TicketType::Internal->value => 'Encuesta de satisfacción (interno)',
        ];

        foreach ($forms as $type => $title) {
            $form = SurveyForm::firstOrCreate(
                ['type' => $type],
                ['title' => $title, 'instructions' => 'Tu opinión nos ayuda a mejorar.'],
            );

            // Solo siembra preguntas si el formulario aún no tiene.
            if ($form->questions()->count() > 0) {
                continue;
            }

            $form->questions()->create([
                'label' => '¿Qué tan satisfecho quedaste con la atención?',
                'type' => SurveyQuestionType::Scale->value,
                'required' => true,
                'sort_order' => 1,
                'is_active' => true,
            ]);
            $form->questions()->create([
                'label' => 'Comentarios (opcional)',
                'type' => SurveyQuestionType::Text->value,
                'required' => false,
                'sort_order' => 2,
                'is_active' => true,
            ]);
        }
    }
}
