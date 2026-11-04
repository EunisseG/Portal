<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\Enums\ContentStatus;
use App\Models\User;
use App\Support\Markdown;
use Database\Factories\NewsFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;

class News extends Model
{
    /** @use HasFactory<NewsFactory> */
    use HasFactory;

    protected $table = 'news';

    protected static function newFactory(): Factory
    {
        return NewsFactory::new();
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'author_id',
        'title',
        'slug',
        'cover_path',
        'excerpt',
        'body',
        'status',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function images(): MorphMany
    {
        return $this->morphMany(ContentImage::class, 'imageable');
    }

    /**
     * Solo noticias publicadas cuya fecha de publicación ya llegó (o sin fecha).
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Published->value)
            ->where(function ($q) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }

    public function bodyHtml(): string
    {
        return Markdown::toHtml($this->body);
    }
}
