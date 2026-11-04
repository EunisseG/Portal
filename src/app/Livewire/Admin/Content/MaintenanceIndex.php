<?php

namespace App\Livewire\Admin\Content;

use App\Domain\Content\Enums\MaintenanceStatus;
use App\Domain\Content\Jobs\SendMaintenanceWhatsApp;
use App\Domain\Content\Models\Maintenance;
use App\Domain\Notifications\Actions\NotifyClients;
use App\Domain\Services\Enums\ServiceType;
use App\Domain\Services\Models\Server;
use App\Notifications\MaintenanceScheduledNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

class MaintenanceIndex extends Component
{
    public ?int $editingId = null;

    /** Tipo de servicio afectado. Vacío = todos (mantenimiento global). */
    public string $service_type = '';

    /** Servidor afectado (solo aplica si service_type = hosting). Vacío = todos. */
    public ?int $server_id = null;

    public string $title = '';

    public string $description = '';

    public string $starts_at = '';

    public ?string $ends_at = null;

    public string $status = 'scheduled';

    public bool $is_published = false;

    public function mount(): void
    {
        Gate::authorize('news.manage');
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'service_type' => ['nullable', Rule::enum(ServiceType::class)],
            'server_id' => ['nullable', 'integer', 'exists:servers,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', Rule::enum(MaintenanceStatus::class)],
            'is_published' => ['boolean'],
        ];
    }

    public function edit(int $id): void
    {
        Gate::authorize('news.manage');

        $m = Maintenance::findOrFail($id);
        $this->editingId = $m->id;
        $this->service_type = $m->service_type?->value ?? '';
        $this->server_id = $m->server_id;
        $this->title = $m->title;
        $this->description = (string) $m->description;
        $this->starts_at = $m->starts_at?->format('Y-m-d\TH:i') ?? '';
        $this->ends_at = $m->ends_at?->format('Y-m-d\TH:i');
        $this->status = $m->status->value;
        $this->is_published = $m->is_published;
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        Gate::authorize('news.manage');

        $data = $this->validate();

        // Normaliza el alcance: tipo vacío = global (null). El servidor solo
        // tiene sentido con hosting; en cualquier otro caso se descarta.
        $data['service_type'] = $data['service_type'] ?: null;
        if ($data['service_type'] !== ServiceType::Hosting->value) {
            $data['server_id'] = null;
        }

        if ($this->editingId) {
            Maintenance::whereKey($this->editingId)->update($data);
            session()->flash('status', 'Mantenimiento actualizado.');
        } else {
            $maintenance = Maintenance::create($data);
            session()->flash('status', 'Mantenimiento creado.');

            // Avisos solo a los clientes afectados (todos si es global; si es de
            // un servidor, solo quienes tienen servicios ahí):
            // notificación in-app (campana) + aviso por WhatsApp.
            app(NotifyClients::class)->sendTo(
                $maintenance->recipientUsers(),
                new MaintenanceScheduledNotification($maintenance),
            );
            SendMaintenanceWhatsApp::dispatch($maintenance->id)->afterCommit();
        }

        $this->resetForm();
    }

    public function delete(int $id): void
    {
        Gate::authorize('news.manage');

        Maintenance::whereKey($id)->delete();

        if ($this->editingId === $id) {
            $this->resetForm();
        }

        session()->flash('status', 'Mantenimiento eliminado.');
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'service_type', 'server_id', 'title', 'description', 'starts_at', 'ends_at');
        $this->status = 'scheduled';
        $this->is_published = false;
        $this->resetValidation();
    }

    public function render(): View
    {
        $maintenances = Maintenance::query()->with('server')->orderByDesc('starts_at')->get();

        return view('livewire.admin.content.maintenance-index', [
            'maintenances' => $maintenances,
            'statusOptions' => MaintenanceStatus::options(),
            'serviceTypeOptions' => ServiceType::options(),
            'servers' => Server::orderBy('name')->get(['id', 'name']),
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
