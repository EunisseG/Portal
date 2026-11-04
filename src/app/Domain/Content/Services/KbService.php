<?php

namespace App\Domain\Content\Services;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\KbArticle;
use App\Domain\Content\Models\KbCategory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class KbService
{
    public const DISK = 'public';

    public function __construct(private readonly AuditLogger $audit) {}

    // ---- Categorías ----

    /**
     * @param  array{name: string, description?: ?string, sort_order?: int}  $data
     */
    public function createCategory(array $data): KbCategory
    {
        $category = KbCategory::create([
            'name' => $data['name'],
            'slug' => $this->uniqueCategorySlug($data['name']),
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        $this->audit->log(AuditAction::KbCategoryCreated, $category, null, ['name' => $category->name]);

        return $category;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateCategory(KbCategory $category, array $data): KbCategory
    {
        $old = ['name' => $category->name];
        $category->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);
        $this->audit->log(AuditAction::KbCategoryUpdated, $category, $old, ['name' => $category->name]);

        return $category;
    }

    public function deleteCategory(KbCategory $category): void
    {
        $this->audit->log(AuditAction::KbCategoryDeleted, $category, ['name' => $category->name]);
        $category->delete(); // cascade elimina artículos
    }

    // ---- Artículos ----

    /**
     * @param  array{kb_category_id: int, title: string, excerpt?: ?string, body: string, status: string, published_at?: ?string}  $data
     * @param  list<UploadedFile>  $galleryImages
     */
    public function createArticle(array $data, ?int $authorId, ?UploadedFile $cover = null, array $galleryImages = []): KbArticle
    {
        return DB::transaction(function () use ($data, $authorId, $cover, $galleryImages): KbArticle {
            $article = KbArticle::create([
                'kb_category_id' => $data['kb_category_id'],
                'author_id' => $authorId,
                'title' => $data['title'],
                'slug' => $this->uniqueArticleSlug($data['title']),
                'cover_path' => $cover ? $cover->store('content/kb', self::DISK) : null,
                'excerpt' => $data['excerpt'] ?? null,
                'body' => $data['body'],
                'status' => $data['status'],
                'published_at' => $this->resolvePublishedAt($data),
            ]);

            $this->storeGallery($article, $galleryImages);

            $this->audit->log(AuditAction::KbArticleCreated, $article, null, $this->snapshot($article));

            return $article;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $galleryImages
     */
    public function updateArticle(KbArticle $article, array $data, ?UploadedFile $cover = null, array $galleryImages = []): KbArticle
    {
        return DB::transaction(function () use ($article, $data, $cover, $galleryImages): KbArticle {
            $old = $this->snapshot($article);

            if ($cover) {
                $this->deleteFile($article->cover_path);
                $article->cover_path = $cover->store('content/kb', self::DISK);
            }

            $article->fill([
                'kb_category_id' => $data['kb_category_id'],
                'title' => $data['title'],
                'excerpt' => $data['excerpt'] ?? null,
                'body' => $data['body'],
                'status' => $data['status'],
                'published_at' => $this->resolvePublishedAt($data),
            ])->save();

            $this->storeGallery($article, $galleryImages);

            $this->audit->log(AuditAction::KbArticleUpdated, $article, $old, $this->snapshot($article->refresh()));

            return $article;
        });
    }

    public function deleteArticle(KbArticle $article): void
    {
        $this->audit->log(AuditAction::KbArticleDeleted, $article, $this->snapshot($article));

        $this->deleteFile($article->cover_path);
        foreach ($article->images as $image) {
            $this->deleteFile($image->path);
        }
        $article->delete();
    }

    /**
     * @param  list<UploadedFile>  $images
     */
    private function storeGallery(KbArticle $article, array $images): void
    {
        foreach ($images as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $article->images()->create([
                'path' => $file->store('content/kb', self::DISK),
                'original_name' => $file->getClientOriginalName(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolvePublishedAt(array $data): ?string
    {
        if (($data['status'] ?? null) !== ContentStatus::Published->value) {
            return null;
        }

        return $data['published_at'] ?: now()->toDateTimeString();
    }

    private function uniqueCategorySlug(string $name): string
    {
        $base = Str::slug($name) ?: 'categoria';
        $slug = $base;
        $i = 2;
        while (KbCategory::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function uniqueArticleSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'articulo';
        $slug = $base;
        $i = 2;
        while (KbArticle::where('slug', $slug)->exists()) {
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
    private function snapshot(KbArticle $article): array
    {
        return [
            'title' => $article->title,
            'category_id' => $article->kb_category_id,
            'status' => $article->status?->value,
        ];
    }
}
