<?php

namespace App\Domain\Audit\Services;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Models\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Servicio central de auditoría. Registra acciones administrativas relevantes
 * sin acoplar la lógica de registro a los controladores.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function log(
        AuditAction $action,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): Audit {
        return Audit::create([
            'user_id' => Auth::id(),
            'action' => $action->value,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255) ?: null,
        ]);
    }
}
