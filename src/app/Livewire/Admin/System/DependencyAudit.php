<?php

namespace App\Livewire\Admin\System;

use App\Domain\System\Enums\DependencyUpdateType;
use App\Domain\System\Models\DependencyAudit as DependencyAuditModel;
use App\Domain\System\Models\DependencyUpdate;
use App\Domain\System\Services\DependencyAuditService;
use App\Domain\System\Services\DependencyUpdateService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Panel "Dependencias / Seguridad": muestra la última auditoría de paquetes
 * (Composer y npm), permite revisar en el momento y —cuando hay
 * vulnerabilidades— lanzar una actualización con respaldo previo y reversión
 * automática. También permite restaurar código o base de datos desde un
 * respaldo (la base de datos solo con confirmación explícita).
 *
 * Solo para administradores (permiso `security.manage`).
 */
class DependencyAudit extends Component
{
    /** Id de la operación que se está siguiendo en vivo (o null). */
    public ?int $trackingId = null;

    /** Confirmación escrita para restaurar la base de datos. */
    public ?int $restoreDbSourceId = null;

    public string $restoreDbConfirm = '';

    public function mount(): void
    {
        Gate::authorize('security.manage');

        // Si al entrar hay una operación en curso, se sigue mostrando su avance.
        $active = DependencyUpdate::whereIn('status', ['queued', 'running'])->latest('id')->first();
        $this->trackingId = $active?->id;
    }

    /** Lanza una auditoría en el momento y guarda el snapshot. */
    public function runNow(DependencyAuditService $service): void
    {
        Gate::authorize('security.manage');

        $audit = $service->runAndStore();

        session()->flash('status', $audit->has_errors
            ? 'Auditoría completada con avisos: alguna herramienta no pudo ejecutarse (revisa el detalle).'
            : sprintf('Auditoría completada: %d vulnerabilidad(es) detectada(s).', $audit->total_vulnerabilities));
    }

    /** Encola la actualización de los paquetes vulnerables (respalda antes). */
    public function updateNow(DependencyUpdateService $service): void
    {
        Gate::authorize('security.manage');

        try {
            $operation = $service->queueUpdate(auth()->user());
            $this->trackingId = $operation->id;
            session()->flash('status', 'Actualización iniciada. Se respaldará la base de datos y el código antes de actualizar. '
                .'La app entrará en mantenimiento unos minutos.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /** Restaura el código (vendor, lockfiles y assets) desde un respaldo. */
    public function restoreCode(int $sourceId, DependencyUpdateService $service): void
    {
        Gate::authorize('security.manage');

        $source = DependencyUpdate::findOrFail($sourceId);

        try {
            $operation = $service->queueRestore($source, DependencyUpdateType::RestoreCode, auth()->user());
            $this->trackingId = $operation->id;
            session()->flash('status', 'Restauración de código iniciada.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /** Abre la confirmación para restaurar la base de datos. */
    public function startRestoreDatabase(int $sourceId): void
    {
        Gate::authorize('security.manage');

        $this->restoreDbSourceId = $sourceId;
        $this->restoreDbConfirm = '';
    }

    public function cancelRestoreDatabase(): void
    {
        $this->restoreDbSourceId = null;
        $this->restoreDbConfirm = '';
    }

    /** Restaura la base de datos (destructivo): exige escribir RESTAURAR. */
    public function confirmRestoreDatabase(DependencyUpdateService $service): void
    {
        Gate::authorize('security.manage');

        if (trim($this->restoreDbConfirm) !== 'RESTAURAR') {
            $this->addError('restoreDbConfirm', 'Escribe RESTAURAR para confirmar.');

            return;
        }

        $source = DependencyUpdate::findOrFail($this->restoreDbSourceId);

        try {
            $operation = $service->queueRestore($source, DependencyUpdateType::RestoreDatabase, auth()->user());
            $this->trackingId = $operation->id;
            $this->cancelRestoreDatabase();
            session()->flash('status', 'Restauración de base de datos iniciada.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render(DependencyUpdateService $service): View
    {
        Gate::authorize('security.manage');

        $latest = DependencyAuditModel::query()->latest('id')->first();
        $history = DependencyAuditModel::query()->latest('id')->limit(10)->get();

        $tracking = $this->trackingId ? DependencyUpdate::find($this->trackingId) : null;
        // Deja de refrescar en vivo cuando la operación termina.
        if ($tracking && ! $tracking->status->isActive()) {
            $this->trackingId = null;
        }

        $backups = DependencyUpdate::where('type', DependencyUpdateType::Update->value)
            ->whereNotNull('backup_path')
            ->whereNull('backup_pruned_at')
            ->with('user')
            ->orderByDesc('id')
            ->get();

        $restoreDbSource = $this->restoreDbSourceId ? DependencyUpdate::find($this->restoreDbSourceId) : null;

        return view('livewire.admin.system.dependency-audit', [
            'latest' => $latest,
            'history' => $history,
            'operation' => $tracking,
            'isBusy' => $tracking?->status->isActive() ?? $service->hasActiveOperation(),
            'backups' => $backups,
            'restoreDbSource' => $restoreDbSource,
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
