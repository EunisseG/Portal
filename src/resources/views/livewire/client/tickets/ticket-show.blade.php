@include('partials.ticket-detail', [
    'ticket' => $ticket,
    'surveyForm' => $surveyForm,
    'attachments' => $attachments,
    'backRoute' => route('client.tickets.index'),
    'backLabel' => 'Volver a mis tickets',
    'staffName' => null,
    'meta' => [
        ['label' => 'Departamento', 'value' => $ticket->department?->label()],
        ['label' => 'Prioridad', 'value' => $ticket->priority?->label(), 'badge' => $ticket->priority?->badgeClasses()],
        ['label' => 'Creado', 'value' => $ticket->created_at?->format('d/m/Y H:i')],
        ['label' => 'Última actualización', 'value' => ($ticket->last_reply_at ?? $ticket->updated_at)?->format('d/m/Y H:i')],
    ],
])
