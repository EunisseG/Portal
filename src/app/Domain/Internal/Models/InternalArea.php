<?php

namespace App\Domain\Internal\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Área interna de la empresa (RH, Sistemas, Finanzas, etc.). Clasifica a los
 * empleados y es el destino de los tickets internos.
 */
class InternalArea extends Model
{
    protected $table = 'internal_areas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'is_active',
        'notify_emails',
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

    /** Empleados que pertenecen a esta área. */
    public function employees(): HasMany
    {
        return $this->hasMany(User::class, 'internal_area_id');
    }

    /**
     * Lista de correos de notificación del área (agentes/buzones a avisar
     * cuando entra un ticket). Se almacenan separados por coma.
     *
     * @return list<string>
     */
    public function notifyEmailList(): array
    {
        if (empty($this->notify_emails)) {
            return [];
        }

        return collect(preg_split('/[,;\s]+/', (string) $this->notify_emails))
            ->map(fn ($e) => trim($e))
            ->filter(fn ($e) => $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }
}
