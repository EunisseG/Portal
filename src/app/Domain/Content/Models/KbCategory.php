<?php

namespace App\Domain\Content\Models;

use Database\Factories\KbCategoryFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KbCategory extends Model
{
    /** @use HasFactory<KbCategoryFactory> */
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return KbCategoryFactory::new();
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function articles(): HasMany
    {
        return $this->hasMany(KbArticle::class);
    }
}
