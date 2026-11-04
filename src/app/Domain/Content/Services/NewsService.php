<?php

namespace App\Domain\Content\Services;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\News;
use App\Domain\Notifications\Actions\NotifyClients;
use App\Notifications\NewsPublishedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NewsService
{
    /** Disco público para imágenes de contenido. */
    public const DISK = 'public';

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotifyClients $notifyClients,
    ) {}

    /**
     * @param  array{title: string, excerpt?: ?string, body: string, status: string, published_at?: ?string}  $data
     * @param  list<UploadedFile>  $galleryImages
     */
    public function create(array $data, ?int $authorId, ?UploadedFile $cover = null, array $galleryImages = []): News
    {
        $news = DB::transaction(function () use ($data, $authorId, $cover, $galleryImages): News {
            $news = News::create([
                'author_id' => $authorId,
                'title' => $data['title'],
                'slug' => $this->uniqueSlug($data['title']),
                'cover_path' => $cover ? $cover->store('content/news', self::DISK) : null,
                'excerpt' => $data['excerpt'] ?? null,
                'body' => $data['body'],
                'status' => $data['status'],
                'published_at' => $this->resolvePublishedAt($data),
            ]);

            $this->storeGallery($news, $galleryImages);

            $this->audit->log(AuditAction::NewsCreated, $news, null, $this->snapshot($news));

            return $news;
        });

        // Aviso a clientes (campana) si la noticia nace publicada.
        if ($this->isPublishedNow($news)) {
            $this->notifyClients->send(new NewsPublishedNotification($news));
        }

        return $news;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $galleryImages
     */
    public function update(News $news, array $data, ?UploadedFile $cover = null, array $galleryImages = []): News
    {
        $wasPublished = $this->isPublishedNow($news);

        $news = DB::transaction(function () use ($news, $data, $cover, $galleryImages): News {
            $old = $this->snapshot($news);

            if ($cover) {
                $this->deleteFile($news->cover_path);
                $news->cover_path = $cover->store('content/news', self::DISK);
            }

            $news->fill([
                'title' => $data['title'],
                'excerpt' => $data['excerpt'] ?? null,
                'body' => $data['body'],
                'status' => $data['status'],
                'published_at' => $this->resolvePublishedAt($data),
            ])->save();

            $this->storeGallery($news, $galleryImages);

            $this->audit->log(AuditAction::NewsUpdated, $news, $old, $this->snapshot($news->refresh()));

            return $news;
        });

        // Aviso a clientes solo cuando la noticia pasa de NO publicada a
        // publicada (evita re-notificar en cada edición de una ya publicada).
        if (! $wasPublished && $this->isPublishedNow($news)) {
            $this->notifyClients->send(new NewsPublishedNotification($news));
        }

        return $news;
    }

    /**
     * ¿La noticia está publicada y su fecha de publicación ya llegó?
     */
    private function isPublishedNow(News $news): bool
    {
        if ($news->status !== ContentStatus::Published) {
            return false;
        }

        return $news->published_at === null || $news->published_at->lte(now());
    }

    public function delete(News $news): void
    {
        $this->audit->log(AuditAction::NewsDeleted, $news, $this->snapshot($news));

        $this->deleteFile($news->cover_path);
        foreach ($news->images as $image) {
            $this->deleteFile($image->path);
        }
        $news->delete();
    }

    /**
     * @param  list<UploadedFile>  $images
     */
    private function storeGallery(News $news, array $images): void
    {
        foreach ($images as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $news->images()->create([
                'path' => $file->store('content/news', self::DISK),
                'original_name' => $file->getClientOriginalName(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolvePublishedAt(array $data): ?string
    {
        // Si se publica y no hay fecha, usa ahora. Si es borrador, sin fecha.
        if (($data['status'] ?? null) !== ContentStatus::Published->value) {
            return null;
        }

        return $data['published_at'] ?: now()->toDateTimeString();
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'noticia';
        $slug = $base;
        $i = 2;
        while (News::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function deleteFile(?string $path): void
    {
        if ($path && Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(News $news): array
    {
        return [
            'title' => $news->title,
            'status' => $news->status?->value,
            'published_at' => $news->published_at?->toDateTimeString(),
        ];
    }
}
