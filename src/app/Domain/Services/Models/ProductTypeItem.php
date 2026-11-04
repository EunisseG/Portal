<?php

namespace App\Domain\Services\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Tipo de producto del catálogo administrable (hosting, correo, plugin, ...).
 */
class ProductTypeItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'label',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** Genera un slug estable a partir de una etiqueta. */
    public static function slugFor(string $label): string
    {
        return Str::slug($label, '_') ?: 'tipo';
    }

    /**
     * Mapa slug => label de los tipos activos, para selects.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return static::where('is_active', true)
            ->orderBy('label')
            ->pluck('label', 'slug')
            ->all();
    }
}
