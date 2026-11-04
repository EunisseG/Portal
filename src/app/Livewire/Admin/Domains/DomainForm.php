<?php

namespace App\Livewire\Admin\Domains;

use App\Domain\Clients\Models\Client;
use App\Domain\Services\Enums\DomainStatus;
use App\Domain\Services\Models\Domain;
use App\Domain\Services\Models\Service;
use App\Domain\Services\Services\DomainService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Livewire\Component;

class DomainForm extends Component
{
    public ?Domain $domain = null;

    public string $client_id = '';
    public string $service_id = '';
    public string $name = '';
    public string $status = DomainStatus::Active->value;
    public string $registered_at = '';
    public string $expires_at = '';
    public bool $auto_renew = false;
    public string $notes = '';

    public function mount(?Domain $domain = null): void
    {
        if ($domain && $domain->exists) {
            Gate::authorize('update', Domain::class);
            $this->domain = $domain;
            $this->client_id = (string) $domain->client_id;
            $this->service_id = (string) ($domain->service_id ?? '');
            $this->name = $domain->name;
            $this->status = $domain->status->value;
            $this->registered_at = $domain->registered_at?->toDateString() ?? '';
            $this->expires_at = $domain->expires_at?->toDateString() ?? '';
            $this->auto_renew = $domain->auto_renew;
            $this->notes = (string) $domain->notes;
        } else {
            Gate::authorize('create', Domain::class);
        }
    }

    public function isEditing(): bool
    {
        return $this->domain !== null;
    }

    // Al cambiar de cliente, se limpia el servicio (debe pertenecer al cliente).
    public function updatedClientId(): void
    {
        $this->service_id = '';
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'client_id' => ['required', Rule::exists('clients', 'id')],
            'service_id' => [
                'nullable',
                Rule::exists('services', 'id')->where(fn ($q) => $q->where('client_id', $this->client_id)),
            ],
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('domains', 'name')->ignore($this->domain?->id),
            ],
            'status' => ['required', new Enum(DomainStatus::class)],
            'registered_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'auto_renew' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function save(DomainService $domains): mixed
    {
        $data = $this->validate();

        $payload = [
            'client_id' => $data['client_id'],
            'service_id' => $data['service_id'] ?: null,
            'name' => $data['name'],
            'status' => $data['status'],
            'registered_at' => $data['registered_at'] ?: null,
            'expires_at' => $data['expires_at'] ?: null,
            'auto_renew' => $data['auto_renew'],
            'notes' => $data['notes'] ?: null,
        ];

        if ($this->isEditing()) {
            $domains->update($this->domain, $payload);
            session()->flash('status', 'Dominio actualizado.');
        } else {
            $domains->create($payload);
            session()->flash('status', 'Dominio creado.');
        }

        return $this->redirect(route('admin.domains.index'), navigate: true);
    }

    public function render(): View
    {
        $services = $this->client_id !== ''
            ? Service::where('client_id', $this->client_id)->orderBy('name')->get()
            : collect();

        return view('livewire.admin.domains.domain-form', [
            'clients' => Client::with('user')->get()->sortBy(fn ($c) => $c->user->name)->values(),
            'services' => $services,
            'statusOptions' => DomainStatus::options(),
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
