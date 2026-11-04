<?php

namespace App\Notifications;

use App\Domain\Content\Models\News;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notificación in-app (canal database) cuando se publica una noticia.
 * Alimenta la campana del área de cliente.
 */
class NewsPublishedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly News $news) {}

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
        return [
            'type' => 'news',
            'title' => 'Nueva noticia',
            'message' => $this->news->title,
            'url' => route('client.news.show', $this->news->slug),
        ];
    }
}
