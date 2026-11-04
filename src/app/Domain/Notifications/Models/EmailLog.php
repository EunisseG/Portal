<?php

namespace App\Domain\Notifications\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Registro de un correo enviado (o intentado) a través del MailGateway.
 * Permite inspeccionar los mensajes individuales y auditar los envíos.
 */
class EmailLog extends Model
{
    public const UPDATED_AT = null;

    public const CREATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'recipient',
        'subject',
        'body',
        'context',
        'success',
        'error',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'success' => 'boolean',
            'sent_at' => 'datetime',
        ];
    }
}
