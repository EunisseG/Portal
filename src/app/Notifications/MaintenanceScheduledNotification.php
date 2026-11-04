<?php

namespace App\Notifications;

use App\Domain\Content\Models\Maintenance;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notificación in-app (canal database) cuando se programa un mantenimiento.
 * Alimenta la campana del área de cliente.
 */
class MaintenanceScheduledNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Maintenance $maintenance) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $when = $this->maintenance->starts_at?->translatedFormat('d M Y · H:i');

        return [
            'type' => 'maintenance',
            'title' => 'Mantenimiento programado',
            'message' => $this->maintenance->title.($when ? ' — '.$when : ''),
            'url' => route('client.maintenances.index'),
        ];
    }
}
