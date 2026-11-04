<?php

namespace App\Livewire\Admin\Domains;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Services\Enums\DomainStatus;
use App\Domain\Services\Models\Domain;
use App\Domain\Services\Services\DomainService;
use App\Integrations\Actions\RecordIntegrationLog;
use App\Integrations\Contracts\DomainGateway;
use App\Support\OperationMode;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class DomainIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = '';

    /** Dominio para el que se abre el modal de renovación (null = cerrado). */
    public ?int $renewingId = null;

    /** Años a renovar (1-5). */
    public int $renewYears = 1;

    public function mount(): void
    {
        Gate::authorize('viewAny', Domain::class);
    }

    /** Abre el modal de renovación para un dominio. */
    public function startRenew(int $domainId): void
    {
        Gate::authorize('update', Domain::class);

        $this->renewingId = $domainId;
        $this->renewYears = 1;
    }

    public function cancelRenew(): void
    {
        $this->renewingId = null;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function delete(int $domainId, DomainService $domains): void
    {
        Gate::authorize('delete', Domain::class);

        $domain = Domain::findOrFail($domainId);
        $domains->delete($domain);

        session()->flash('status', 'Dominio eliminado.');
    }

    /**
     * Renueva un dominio un año. En modo MANUAL (hoy) solo avanza la fecha de
     * vencimiento y lo deja activo. En modo AUTOMÁTICO intenta renovarlo contra
     * el proveedor de dominios; si la llamada falla, no avanza la fecha.
     */
    public function renew(OperationMode $mode, RecordIntegrationLog $log, AuditLogger $audit): void
    {
        Gate::authorize('update', Domain::class);

        $this->validate([
            'renewingId' => ['required', 'integer'],
            'renewYears' => ['required', 'integer', 'between:1,5'],
        ], [], ['renewYears' => 'años']);

        $domain = Domain::findOrFail($this->renewingId);
        $years = (int) $this->renewYears;

        if ($mode->domainRenewalAutomatic()) {
            // Modo automático: renovar contra el proveedor configurado.
            $gateway = app(DomainGateway::class);
            $result = $gateway->renew($domain->name, $years);

            $log->record('domain.renew', [
                'success' => $result->success,
                'message' => $domain->name.': '.$result->message.' ('.$years.' año(s))',
                'internal_reference' => 'domain:'.$domain->id,
            ]);

            if (! $result->success) {
                session()->flash('error', 'No se pudo renovar con el proveedor: '.$result->message);

                return;
            }

            // Usa la fecha devuelta por el proveedor si viene; si no, +N años.
            $newExpiry = isset($result->data['expires_at'])
                ? \Illuminate\Support\Carbon::parse($result->data['expires_at'])->toDateString()
                : $this->nextExpiry($domain, $years)->toDateString();
        } else {
            // Modo manual: el admin confirma el pago/renovación fuera del portal.
            $newExpiry = $this->nextExpiry($domain, $years)->toDateString();
        }

        $old = ['expires_at' => $domain->expires_at?->toDateString(), 'status' => $domain->status?->value];

        $domain->update([
            'expires_at' => $newExpiry,
            'status' => DomainStatus::Active->value,
        ]);

        $audit->log(AuditAction::DomainUpdated, $domain, $old, [
            'expires_at' => $newExpiry,
            'status' => DomainStatus::Active->value,
            'renewed' => true,
            'years' => $years,
        ]);

        // Registra el evento comercial de renovación para el ejecutivo del cliente.
        app(\App\Domain\Sales\Services\SalesEventRecorder::class)->recordRenewal($domain);

        $this->renewingId = null;
        session()->flash('status', 'Dominio renovado '.$years.' año(s), hasta '.$newExpiry.'.');
    }

    /** Nueva fecha de vencimiento: parte de la actual si sigue vigente, o de hoy. */
    private function nextExpiry(Domain $domain, int $years = 1): \Illuminate\Support\Carbon
    {
        $base = $domain->expires_at && $domain->expires_at->isFuture()
            ? $domain->expires_at->copy()
            : now();

        return $base->addYears($years);
    }

    public function render(): View
    {
        $domains = Domain::query()
            ->with(['client.user', 'service'])
            ->when($this->search !== '', function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', "%{$this->search}%")
                        ->orWhereHas('client.user', function ($uq) {
                            $uq->where('name', 'like', "%{$this->search}%")
                                ->orWhere('email', 'like', "%{$this->search}%");
                        });
                });
            })
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('id')
            ->paginate(10);

        $renewingDomain = $this->renewingId
            ? Domain::find($this->renewingId)
            : null;

        return view('livewire.admin.domains.domain-index', [
            'domains' => $domains,
            'statusOptions' => DomainStatus::options(),
            'renewingDomain' => $renewingDomain,
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
