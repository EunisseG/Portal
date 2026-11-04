<?php

namespace App\Domain\System\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tipo de moneda del catálogo de sistema. Por ahora es solo un catálogo
 * administrativo: no participa en la conversión ni en los cálculos de precios.
 */
class Currency extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'symbol',
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
}
