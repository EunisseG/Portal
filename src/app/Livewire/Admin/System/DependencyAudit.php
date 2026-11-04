<?php

namespace App\Livewire\Admin\System;

use App\Domain\System\Models\DependencyAudit as DependencyAuditModel;
use App\Domain\System\Services\DependencyAuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Panel "Dependencias / Seguridad": muestra la última auditoría de paquetes
 * (Composer y npm) con el conteo de vulnerabilidades por severidad y los
 * paquetes afectados. Permite lanzar una revisión en el momento. La auditoría
 * también corre sola cada semana (comando `dependencies:check`).
 *
 * Solo para administradores (permiso `security.manage`, que solo tienen
 * admin y super_admin).
 */
class DependencyAudit extends Component
{
    public function mount(): void
    {
        Gate::authorize('security.manage');
    }

    /** Lanza una auditoría en el momento y guarda el snapshot. */
    public function runNow(DependencyAuditService $service): void
    {
        Gate::authorize('security.manage');

        $audit = $service->runAndStore();

        if ($audit->has_errors) {
            session()->flash('status', 'Auditoría completada con avisos: alguna herramienta no pudo ejecutarse (revisa el detalle).');
        } else {
            session()->flash('status', sprintf(
                'Auditoría completada: %d vulnerabilidad(es) detectada(s).',
                $audit->total_vulnerabilities,
            ));
        }
    }

    public function render(): View
    {
        Gate::authorize('security.manage');

        $latest = DependencyAuditModel::query()->latest('id')->first();
        $history = DependencyAuditModel::query()
            ->latest('id')
            ->limit(10)
            ->get();

        return view('livewire.admin.system.dependency-audit', [
            'latest' => $latest,
            'history' => $history,
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
