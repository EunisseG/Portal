<?php

namespace App\Domain\Notifications\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Registro de recordatorios ya enviados, para evitar duplicados.
 */
class Reminder extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'remindable_type',
        'remindable_id',
        'days_before',
        'due_date',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'days_before' => 'integer',
            'due_date' => 'date',
            'sent_at' => 'datetime',
        ];
    }

    public function remindable(): MorphTo
    {
        return $this->morphTo();
    }
}
