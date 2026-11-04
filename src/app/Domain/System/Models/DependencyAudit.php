<?php

namespace App\Domain\System\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Snapshot del estado de seguridad de las dependencias (Composer + npm) en un
 * momento dado. Lo generan el comando programado `dependencies:check` y el
 * botón "Revisar ahora" del panel. Nunca contiene secretos: solo nombres de
 * paquetes, severidades, versiones y enlaces a los avisos.
 *
 * @property int $total_vulnerabilities
 * @property bool $has_errors
 * @property array<string, mixed>|null $detail
 */
class DependencyAudit extends Model
{
    protected $table = 'dependency_audits';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'composer_critical',
        'composer_high',
        'composer_medium',
        'composer_low',
        'composer_total',
        'npm_critical',
        'npm_high',
        'npm_medium',
        'npm_low',
        'npm_total',
        'total_vulnerabilities',
        'has_errors',
        'detail',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'composer_critical' => 'integer',
            'composer_high' => 'integer',
            'composer_medium' => 'integer',
            'composer_low' => 'integer',
            'composer_total' => 'integer',
            'npm_critical' => 'integer',
            'npm_high' => 'integer',
            'npm_medium' => 'integer',
            'npm_low' => 'integer',
            'npm_total' => 'integer',
            'total_vulnerabilities' => 'integer',
            'has_errors' => 'boolean',
            'detail' => 'array',
        ];
    }

    /** Conteo total de vulnerabilidades de severidad crítica o alta (las urgentes). */
    public function urgentCount(): int
    {
        return $this->composer_critical + $this->composer_high
            + $this->npm_critical + $this->npm_high;
    }
}
