<?php

namespace App\Livewire\Client;

use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Campana de notificaciones del área de cliente. Muestra el número de
 * notificaciones no leídas (badge) y un panel desplegable con las últimas.
 * Al abrir una notificación se marca como leída y redirige a su destino.
 */
class NotificationBell extends Component
{
    /** Máximo de notificaciones a listar en el panel. */
    public const LIST_LIMIT = 8;

    public function markAllAsRead(): void
    {
        $this->user()?->unreadNotifications()->update(['read_at' => now()]);
    }

    /**
     * Marca una notificación como leída y redirige a su URL de destino.
     */
    public function open(string $id): void
    {
        $user = $this->user();
        $notification = $user?->notifications()->whereKey($id)->first();

        if (! $notification) {
            return;
        }

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        $url = $notification->data['url'] ?? null;

        if ($url) {
            $this->redirect($url, navigate: true);
        }
    }

    private function user(): ?\App\Models\User
    {
        return auth()->user();
    }

    public function render(): View
    {
        $user = $this->user();

        $unreadCount = $user ? $user->unreadNotifications()->count() : 0;
        $notifications = $user
            ? $user->notifications()->latest()->limit(self::LIST_LIMIT)->get()
            : collect();

        return view('livewire.client.notification-bell', [
            'unreadCount' => $unreadCount,
            'notifications' => $notifications,
        ]);
    }
}
