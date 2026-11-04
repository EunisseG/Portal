<?php

namespace App\Domain\Tickets\Services;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Tickets\Enums\TicketEvent;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Events\TicketCreated;
use App\Domain\Tickets\Events\TicketStatusChanged;
use App\Domain\Tickets\Jobs\SendSurveyInvitation;
use App\Domain\Tickets\Jobs\SendTicketNotification;
use App\Domain\Tickets\Jobs\SendTicketReplyWhatsApp;
use App\Domain\Tickets\Jobs\SendTicketSlackNotification;
use App\Domain\Tickets\Models\SatisfactionSurvey;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketMessage;
use App\Models\User;
use App\Notifications\TicketRepliedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TicketService
{
    /** Disco privado para adjuntos de tickets. */
    public const DISK = 'local';

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Crea un ticket con su primer mensaje. Lo abre el cliente (autor = su usuario).
     *
     * @param  array{subject: string, department: string, priority: string}  $data
     * @param  list<UploadedFile>  $attachments
     */
    public function create(int $clientId, User $author, array $data, string $body, array $attachments = [], bool $isStaff = false): Ticket
    {
        return DB::transaction(function () use ($clientId, $author, $data, $body, $attachments, $isStaff): Ticket {
            $ticket = Ticket::create([
                'number' => $this->generateNumber(),
                'client_id' => $clientId,
                'subject' => $data['subject'],
                'department' => $data['department'],
                'priority' => $data['priority'],
                // Criticidad (impacto): la envía el formulario; por defecto media.
                'criticality' => $data['criticality'] ?? \App\Domain\Tickets\Enums\TicketCriticality::Medium->value,
                // Si lo abre el staff (a nombre del cliente), queda "respondido";
                // si lo abre el cliente, queda "abierto".
                'status' => ($isStaff ? TicketStatus::Answered : TicketStatus::Open)->value,
                'last_reply_at' => now(),
                // SLA: si lo abre el staff, su primer mensaje ya es la primera
                // respuesta, así que se marca el tiempo desde el inicio.
                'first_response_at' => $isStaff ? now() : null,
            ]);

            $this->addMessage($ticket, $author, $body, $isStaff, $attachments);

            $this->audit->log(AuditAction::TicketCreated, $ticket, null, [
                'number' => $ticket->number,
                'subject' => $ticket->subject,
                'client_id' => $clientId,
            ]);

            SendTicketNotification::dispatch($ticket->id, TicketEvent::Opened)->afterCommit();

            // Aviso interno al equipo por Slack solo cuando lo abre el cliente
            // (si lo abre el staff no hace falta avisarse a sí mismo).
            if (! $isStaff) {
                SendTicketSlackNotification::dispatch($ticket->id)->afterCommit();
            }

            // Evento de dominio para las integraciones (Monday, etc.).
            TicketCreated::dispatch($ticket);

            return $ticket;
        });
    }

    /**
     * Crea un ticket INTERNO levantado por un empleado. No tiene cliente: se
     * liga al empleado (opened_by_user_id) y a un área interna. No genera
     * encuesta de satisfacción ni avisos a clientes.
     *
     * @param  array{subject: string, priority: string, criticality?: string}  $data
     * @param  list<UploadedFile>  $attachments
     */
    public function createInternal(User $employee, int $internalAreaId, array $data, string $body, array $attachments = []): Ticket
    {
        return DB::transaction(function () use ($employee, $internalAreaId, $data, $body, $attachments): Ticket {
            $ticket = Ticket::create([
                'number' => $this->generateNumber(),
                'type' => \App\Domain\Tickets\Enums\TicketType::Internal->value,
                'client_id' => null,
                'opened_by_user_id' => $employee->id,
                'internal_area_id' => $internalAreaId,
                'subject' => $data['subject'],
                // El departamento no aplica a internos; se guarda "soporte" por
                // compatibilidad de columna (el destino real es el área).
                'department' => \App\Domain\Tickets\Enums\TicketDepartment::Support->value,
                'priority' => $data['priority'],
                'criticality' => $data['criticality'] ?? \App\Domain\Tickets\Enums\TicketCriticality::Medium->value,
                'status' => TicketStatus::Open->value,
                'last_reply_at' => now(),
            ]);

            $this->addMessage($ticket, $employee, $body, false, $attachments);

            $this->audit->log(AuditAction::TicketCreated, $ticket, null, [
                'number' => $ticket->number,
                'subject' => $ticket->subject,
                'type' => 'internal',
                'internal_area_id' => $internalAreaId,
            ]);

            // Aviso interno al equipo por Slack de que entró un ticket interno.
            SendTicketSlackNotification::dispatch($ticket->id)->afterCommit();

            // Aviso por correo a los agentes del área (si tiene correos).
            \App\Domain\Tickets\Jobs\SendInternalTicketNotification::dispatch($ticket->id)->afterCommit();

            return $ticket;
        });
    }

    /**
     * Añade una respuesta al ticket.
     *
     * @param  list<UploadedFile>  $attachments
     */
    public function reply(Ticket $ticket, User $author, string $body, bool $isStaff, array $attachments = []): TicketMessage
    {
        return DB::transaction(function () use ($ticket, $author, $body, $isStaff, $attachments): TicketMessage {
            $message = $this->addMessage($ticket, $author, $body, $isStaff, $attachments);

            // Estado automático: respuesta del staff => "answered"; del cliente => "open".
            $updates = [
                'status' => $isStaff ? TicketStatus::Answered->value : TicketStatus::Open->value,
                'last_reply_at' => now(),
            ];

            // SLA: primera respuesta del staff (si aún no se había registrado).
            if ($isStaff && $ticket->first_response_at === null) {
                $updates['first_response_at'] = now();
            }

            $ticket->update($updates);

            $this->audit->log(AuditAction::TicketReplied, $ticket, null, [
                'by' => $isStaff ? 'staff' : 'client',
            ]);

            $event = $isStaff ? TicketEvent::StaffReplied : TicketEvent::ClientReplied;
            SendTicketNotification::dispatch($ticket->id, $event)->afterCommit();

            // Avisos automáticos al cliente cuando responde el staff: WhatsApp
            // y notificación in-app (campana del portal). Solo para tickets de
            // cliente; los internos no tienen cliente al que avisar.
            if ($isStaff && $ticket->client_id !== null) {
                SendTicketReplyWhatsApp::dispatch($ticket->id)->afterCommit();

                $clientUser = $ticket->client?->user;
                if ($clientUser) {
                    $clientUser->notify(new TicketRepliedNotification($ticket));
                }
            }

            return $message;
        });
    }

    public function changeStatus(Ticket $ticket, TicketStatus $status): Ticket
    {
        $old = ['status' => $ticket->status->value];

        $updates = ['status' => $status->value];

        // SLA: marca el tiempo de resolución la primera vez que el ticket pasa
        // a Resuelto o Cerrado. Si se reabre (vuelve a un estado activo previo),
        // se limpia para medir de nuevo cuando se vuelva a resolver.
        if (in_array($status, [TicketStatus::Resolved, TicketStatus::Closed], true)) {
            if ($ticket->resolved_at === null) {
                $updates['resolved_at'] = now();
            }
        } elseif ($ticket->resolved_at !== null) {
            $updates['resolved_at'] = null;
        }

        $ticket->update($updates);
        $this->audit->log(AuditAction::TicketStatusChanged, $ticket, $old, ['status' => $status->value]);

        // Al RESOLVER o CERRAR el ticket se genera (una sola vez) la encuesta de
        // satisfacción. Para tickets de CLIENTE se liga al cliente y se le
        // invita por correo; para INTERNOS se liga al empleado que lo abrió.
        if (in_array($status, [TicketStatus::Resolved, TicketStatus::Closed], true)) {
            $isInternal = $ticket->isInternal();

            $survey = SatisfactionSurvey::firstOrCreate(
                ['ticket_id' => $ticket->id],
                [
                    'type' => $ticket->type?->value ?? \App\Domain\Tickets\Enums\TicketType::Client->value,
                    'client_id' => $ticket->client_id,
                    'user_id' => $isInternal ? $ticket->opened_by_user_id : null,
                ],
            );

            if ($survey->wasRecentlyCreated && ! $isInternal) {
                // La invitación por correo solo aplica a clientes; el empleado
                // la ve en su portal al abrir el ticket.
                SendSurveyInvitation::dispatch($ticket->id)->afterCommit();
            }
        }

        // Evento de dominio para las integraciones (cubre también el cierre).
        TicketStatusChanged::dispatch($ticket);

        return $ticket;
    }

    /**
     * Guarda las respuestas de la encuesta configurable de un ticket. Recibe
     * un mapa [question_id => valor]. Para escalas el valor es 1..5; para
     * texto/sí-no es una cadena. Calcula y persiste en la cabecera el score
     * (promedio de las escalas) y el comentario (primera respuesta de texto),
     * por compatibilidad con las métricas CSAT existentes.
     *
     * @param  array<int|string, mixed>  $answers
     */
    public function answerSurvey(SatisfactionSurvey $survey, array $answers): void
    {
        DB::transaction(function () use ($survey, $answers): void {
            $form = \App\Domain\Tickets\Models\SurveyForm::forType(
                $survey->type ?? \App\Domain\Tickets\Enums\TicketType::Client
            );
            $questions = $form->activeQuestions()->get();

            $scaleValues = [];
            $firstText = null;

            foreach ($questions as $question) {
                $raw = $answers[$question->id] ?? null;

                $valueNumber = null;
                $valueText = null;

                if ($question->type === \App\Domain\Tickets\Enums\SurveyQuestionType::Scale) {
                    $valueNumber = $raw !== null && $raw !== '' ? (int) $raw : null;
                    if ($valueNumber !== null) {
                        $scaleValues[] = $valueNumber;
                    }
                } else {
                    $valueText = $raw !== null && $raw !== '' ? (string) $raw : null;
                    if ($valueText !== null && $firstText === null && $question->type === \App\Domain\Tickets\Enums\SurveyQuestionType::Text) {
                        $firstText = $valueText;
                    }
                }

                \App\Domain\Tickets\Models\SurveyAnswer::updateOrCreate(
                    ['satisfaction_survey_id' => $survey->id, 'survey_question_id' => $question->id],
                    ['value_number' => $valueNumber, 'value_text' => $valueText],
                );
            }

            $survey->update([
                // Promedio de escalas (redondeado) para el CSAT agregado.
                'score' => $scaleValues !== [] ? (int) round(array_sum($scaleValues) / count($scaleValues)) : null,
                'comment' => $firstText,
                'responded_at' => now(),
            ]);
        });
    }

    public function changePriority(Ticket $ticket, TicketPriority $priority): Ticket
    {
        $old = ['priority' => $ticket->priority->value];
        $ticket->update(['priority' => $priority->value]);
        $this->audit->log(AuditAction::TicketPriorityChanged, $ticket, $old, ['priority' => $priority->value]);

        return $ticket;
    }

    public function changeCriticality(Ticket $ticket, \App\Domain\Tickets\Enums\TicketCriticality $criticality): Ticket
    {
        $old = ['criticality' => $ticket->criticality?->value];
        $ticket->update(['criticality' => $criticality->value]);
        $this->audit->log(AuditAction::TicketPriorityChanged, $ticket, $old, ['criticality' => $criticality->value]);

        return $ticket;
    }

    /**
     * @param  list<UploadedFile>  $attachments
     */
    private function addMessage(Ticket $ticket, User $author, string $body, bool $isStaff, array $attachments): TicketMessage
    {
        $message = $ticket->messages()->create([
            'user_id' => $author->id,
            'is_staff' => $isStaff,
            'body' => $body,
        ]);

        foreach ($attachments as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $path = $file->store('tickets/'.$ticket->id, self::DISK);
            $message->attachments()->create([
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        return $message;
    }

    private function generateNumber(): string
    {
        $year = now()->year;
        $seq = Ticket::whereYear('created_at', $year)->count() + 1;

        do {
            $number = 'TK-'.$year.'-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
            $seq++;
        } while (Ticket::where('number', $number)->exists());

        return $number;
    }
}
