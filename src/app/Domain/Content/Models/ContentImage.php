<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class ContentImage extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'imageable_type',
        'imageable_id',
        'path',
        'original_name',
    ];

    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * URL pública de la imagen (disco público).
     */
    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
