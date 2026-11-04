{{--
    Formulario de encuesta configurable (reutilizable por cliente y empleado).
    Requiere: $surveyForm (SurveyForm con activeQuestions cargadas), $survey
    (SatisfactionSurvey), y que el componente tenga la propiedad surveyAnswers
    (array por question_id) y el método submitSurvey.
--}}
<div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
    <h2 class="text-lg font-medium text-slate-900">{{ $surveyForm->title }}</h2>

    @if ($survey->responded_at)
        <p class="mt-2 text-sm text-slate-600">Gracias, ya registramos tu respuesta.</p>
        @if ($survey->score)
            <p class="mt-1 text-sm text-slate-500">Satisfacción general: <span class="font-semibold text-slate-900">{{ $survey->score }}/5</span></p>
        @endif
    @else
        @if ($surveyForm->instructions)
            <p class="mt-1 text-sm text-slate-500">{{ $surveyForm->instructions }}</p>
        @endif

        <form wire:submit="submitSurvey" class="mt-4 space-y-5">
            @foreach ($surveyForm->activeQuestions as $question)
                <div>
                    <label class="block text-sm font-medium text-slate-700">
                        {{ $question->label }}
                        @if ($question->required) <span class="text-red-500">*</span> @endif
                    </label>

                    @if ($question->type === \App\Domain\Tickets\Enums\SurveyQuestionType::Scale)
                        <div class="mt-2 flex items-center gap-2">
                            @for ($i = 1; $i <= 5; $i++)
                                <button type="button" wire:click="$set('surveyAnswers.{{ $question->id }}', {{ $i }})"
                                        class="flex h-10 w-10 items-center justify-center rounded-full border text-sm font-semibold transition
                                               {{ (int) ($surveyAnswers[$question->id] ?? 0) >= $i ? 'border-amber-400 bg-amber-400 text-white' : 'border-slate-300 bg-white text-slate-500 hover:border-amber-300' }}">
                                    {{ $i }}
                                </button>
                            @endfor
                        </div>
                    @elseif ($question->type === \App\Domain\Tickets\Enums\SurveyQuestionType::Boolean)
                        <div class="mt-2 flex items-center gap-2">
                            @foreach (['Sí' => 'Sí', 'No' => 'No'] as $val => $label)
                                <button type="button" wire:click="$set('surveyAnswers.{{ $question->id }}', '{{ $val }}')"
                                        class="rounded-md border px-4 py-1.5 text-sm font-medium transition
                                               {{ ($surveyAnswers[$question->id] ?? '') === $val ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-600 hover:border-slate-400' }}">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                    @else
                        <textarea wire:model="surveyAnswers.{{ $question->id }}" rows="3"
                                  class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    @endif

                    @error('surveyAnswers.'.$question->id) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endforeach

            <button type="submit"
                    wire:loading.attr="disabled" wire:target="submitSurvey"
                    class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-60">
                <span wire:loading.remove wire:target="submitSurvey">Enviar</span>
                <span wire:loading wire:target="submitSurvey">Enviando…</span>
            </button>
        </form>
    @endif
</div>
