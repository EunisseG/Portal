<?php

namespace App\Domain\Services\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Categoría de producto (Hosting, Correo, etc.) para organizar el catálogo.
 */
class ProductCategory extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'icon_url',
        'description',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}
