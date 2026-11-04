<?php

namespace App\Integrations\DTO;

/**
 * Representación serializable de un ticket de WORTEV para enviarlo a sistemas
 * externos. Contiene solo campos planos (sin modelos vivos), de modo que puede
 * viajar por la cola y reconstruirse en el Job.
 */
final class TicketPayload
{
    public function __construct(
        public readonly int $ticketId,
        public readonly string $number,
        public readonly string $subject,
        public readonly string $status,
        public readonly string $priority,
        public readonly ?string $clientName = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            ticketId: (int) $data['ticket_id'],
            number: (string) ($data['number'] ?? ''),
            subject: (string) ($data['subject'] ?? ''),
            status: (string) ($data['status'] ?? ''),
            priority: (string) ($data['priority'] ?? ''),
            clientName: $data['client_name'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ticket_id' => $this->ticketId,
            'number' => $this->number,
            'subject' => $this->subject,
            'status' => $this->status,
            'priority' => $this->priority,
            'client_name' => $this->clientName,
        ];
    }
}
