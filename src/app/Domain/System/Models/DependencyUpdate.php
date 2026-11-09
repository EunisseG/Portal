<?php

namespace App\Domain\System\Models;

use App\Domain\System\Enums\DependencyUpdateStatus;
use App\Domain\System\Enums\DependencyUpdateType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Operación sobre dependencias (actualización o restauración) lanzada desde el
 * panel. Las actualizaciones guardan la carpeta de su respaldo, que después
 * puede usarse para restaurar el código o la base de datos.
 *
 * @property DependencyUpdateType $type
 * @property DependencyUpdateStatus $status
 * @property ?string $backup_path
 */
class DependencyUpdate extends Model
{
    protected $table = 'dependency_updates';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'status',
        'source_update_id',
        'user_id',
        'packages',
        'backup_path',
        'backup_size_bytes',
        'backup_pruned_at',
        'bypass_secret',
        'maintenance_left_on',
        'audit_before_id',
        'audit_after_id',
        'summary',
        'log',
        'started_at',
        'finished_at',
    ];

    /** El secreto de mantenimiento nunca se serializa hacia la vista. */
    protected $hidden = ['bypass_secret'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DependencyUpdateType::class,
            'status' => DependencyUpdateStatus::class,
            'packages' => 'array',
            'backup_size_bytes' => 'integer',
            'backup_pruned_at' => 'datetime',
            'bypass_secret' => 'encrypted',
            'maintenance_left_on' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_update_id');
    }

    /** ¿Tiene un respaldo disponible para restaurar? */
    public function hasBackup(): bool
    {
        return $this->type === DependencyUpdateType::Update
            && $this->backup_path !== null
            && $this->backup_pruned_at === null;
    }

    /** Tamaño del respaldo legible (p. ej. "245.3 MB"). */
    public function backupSizeLabel(): string
    {
        $bytes = (int) ($this->backup_size_bytes ?? 0);
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $size = (float) $bytes;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return number_format($size, $i === 0 ? 0 : 1).' '.$units[$i];
    }
}
