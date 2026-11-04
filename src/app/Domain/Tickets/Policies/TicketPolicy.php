<?php

namespace App\Domain\Tickets\Policies;

use App\Domain\Tickets\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tickets.view');
    }

    /**
     * Ver un ticket concreto. Para tickets INTERNOS: staff con permiso de
     * tickets internos o el empleado que lo abrió. Para tickets de CLIENTE:
     * staff con permiso o el cliente dueño.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        if ($ticket->isInternal()) {
            return $user->can('internal_tickets.view')
                || $ticket->opened_by_user_id === $user->id;
        }

        if ($user->can('tickets.view')) {
            return true;
        }

        $account = $user->clientAccount();

        return $account && $account->id === $ticket->client_id
            && (! $user->isSubUser() || $user->can('client.tickets.view'));
    }

    /**
     * Responder. Internos: staff con permiso interno o el empleado dueño.
     * Cliente: staff con permiso o el cliente dueño.
     */
    public function reply(User $user, Ticket $ticket): bool
    {
        if ($ticket->isInternal()) {
            return $user->can('internal_tickets.reply')
                || $ticket->opened_by_user_id === $user->id;
        }

        if ($user->can('tickets.reply')) {
            return true;
        }

        $account = $user->clientAccount();

        // El sub-usuario responde solo si tiene el permiso de crear/responder.
        return $account && $account->id === $ticket->client_id
            && (! $user->isSubUser() || $user->can('client.tickets.create'));
    }

    /**
     * Gestionar (cambiar estado/prioridad): staff con permiso. Para internos
     * se exige el permiso de gestión de tickets internos.
     */
    public function manage(User $user, ?Ticket $ticket = null): bool
    {
        if ($ticket !== null && $ticket->isInternal()) {
            return $user->can('internal_tickets.manage');
        }

        return $user->can('tickets.manage');
    }

    /**
     * Descargar un adjunto: staff con permiso, o el cliente dueño del ticket.
     */
    public function download(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket);
    }
}
