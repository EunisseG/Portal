<?php

namespace App\Domain\Tickets\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Respuesta preparada (plantilla) reutilizable por el personal al contestar
 * tickets de soporte.
 */
class CannedResponse extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'body',
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
}
