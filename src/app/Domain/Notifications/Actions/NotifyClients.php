<?php

namespace App\Domain\Notifications\Actions;

use App\Domain\Auth\Enums\RoleName;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Envía una notificación in-app (campana) a usuarios con rol "client".
 * Se usa para avisos del portal: nuevas noticias, mantenimientos, etc.
 */
class NotifyClients
{
    /**
     * Envía a TODOS los clientes.
     */
    public function send(Notification $notification): void
    {
        User::role(RoleName::Client->value)
            ->chunkById(200, function ($clients) use ($notification) {
                NotificationFacade::send($clients, $notification);
            });
    }

    /**
     * Envía solo a los usuarios indicados (destinatarios ya resueltos).
     *
     * @param  Collection<int, User>|iterable<User>  $users
     */
    public function sendTo(iterable $users, Notification $notification): void
    {
        $users = $users instanceof Collection ? $users : collect($users);

        if ($users->isEmpty()) {
            return;
        }

        NotificationFacade::send($users, $notification);
    }
}
