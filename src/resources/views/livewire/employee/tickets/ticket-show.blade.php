@include('partials.ticket-detail', [
    'ticket' => $ticket,
    'surveyForm' => $surveyForm,
    'attachments' => $attachments,
    'backRoute' => route('employee.dashboard'),
    'backLabel' => 'Volver',
    'staffName' => 'Equipo de soporte',
    'meta' => [
        ['label' => 'Área', 'value' => $ticket->internalArea?->name],
        ['label' => 'Criticidad', 'value' => $ticket->criticality?->label(), 'badge' => $ticket->criticality?->badgeClasses()],
        ['label' => 'Creado', 'value' => $ticket->created_at?->format('d/m/Y H:i')],
        ['label' => 'Última actualización', 'value' => ($ticket->last_reply_at ?? $ticket->updated_at)?->format('d/m/Y H:i')],
    ],
])
