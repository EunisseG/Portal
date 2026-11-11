<?php

namespace App\Livewire\Admin\Tickets;

use App\Domain\Clients\Models\Client;
use App\Domain\Services\Models\Service;
use App\Domain\Tickets\Enums\TicketCriticality;
use App\Domain\Tickets\Enums\TicketDepartment;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Services\TicketService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Enum;
use Livewire\Attributes\Url;
use Livewire\Component;

class TicketCreate extends Component
{
    /** @var array<int, int> IDs de clientes destinatarios. */
    public array $clientIds = [];

    public string $clientSearch = '';

    /** Cliente preseleccionado al abrir desde el detalle de un cliente. */
    #[Url(as: 'client')]
    public ?int $presetClientId = null;

    /** Servicio del cliente preseleccionado (opcional, para contexto). */
    public ?int $serviceId = null;

    public string $subject = '';

    public string $department = TicketDepartment::Support->value;

    public string $priority = TicketPriority::Normal->value;

    public string $criticality = TicketCriticality::Medium->value;

    public string $body = '';

    public function mount(): void
    {
        Gate::authorize('tickets.manage');

        // Si se abre desde el detalle de un cliente, preseleccionar ese cliente
        // y autollenar el nombre en el mensaje.
        if ($this->presetClientId && Client::whereKey($this->presetClientId)->exists()) {
            $this->clientIds = [$this->presetClientId];
            $client = Client::with('user')->find($this->presetClientId);
            if ($client) {
                $this->body = 'Hola '.$client->user->name.",\n\n";
            }
        }
    }

    /**
     * Al elegir un servicio del cliente preseleccionado, autollena el asunto y
     * añade el nombre del servicio al mensaje como referencia.
     */
    public function updatedServiceId(mixed $value): void
    {
        $service = $value ? Service::find((int) $value) : null;

        if ($service) {
            $this->subject = 'Sobre tu servicio: '.$service->name;
            $client = $this->presetClientId ? Client::with('user')->find($this->presetClientId) : null;
            $greeting = $client ? ('Hola '.$client->user->name.",\n\n") : '';
            $this->body = $greeting.'En relación con tu servicio "'.$service->name.'": ';
        }
    }

    public function toggleClient(int $id): void
    {
        if (in_array($id, $this->clientIds, true)) {
            $this->clientIds = array_values(array_diff($this->clientIds, [$id]));
        } else {
            $this->clientIds[] = $id;
        }
    }

    public function applyCanned(int $cannedId): void
    {
        $canned = \App\Domain\Tickets\Models\CannedResponse::find($cannedId);

        if ($canned) {
            $this->body = $canned->body;
        }
    }

    public function save(TicketService $tickets): mixed
    {
        Gate::authorize('tickets.manage');

        $data = $this->validate([
            'clientIds' => ['required', 'array', 'min:1'],
            'clientIds.*' => ['integer', 'exists:clients,id'],
            'subject' => ['required', 'string', 'max:255'],
            'department' => ['required', new Enum(TicketDepartment::class)],
            'priority' => ['required', new Enum(TicketPriority::class)],
            'criticality' => ['required', new Enum(TicketCriticality::class)],
            'body' => ['required', 'string', new \App\Rules\RichTextBody],
        ], [
            'clientIds.required' => 'Selecciona al menos un cliente.',
            'clientIds.min' => 'Selecciona al menos un cliente.',
        ]);

        $author = auth()->user();
        $count = 0;

        // Un ticket individual por cliente (cada uno ve solo el suyo).
        foreach ($data['clientIds'] as $clientId) {
            $tickets->create(
                (int) $clientId,
                $author,
                [
                    'subject' => $data['subject'],
                    'department' => $data['department'],
                    'priority' => $data['priority'],
                    'criticality' => $data['criticality'],
                ],
                $data['body'],
                [],
                isStaff: true,
            );
            $count++;
        }

        session()->flash('status', $count === 1
            ? 'Ticket creado y notificado al cliente.'
            : "Se crearon {$count} tickets (uno por cliente) y se notificó a cada uno.");

        return $this->redirect(route('admin.tickets.index'), navigate: true);
    }

    public function render(): View
    {
        $clients = Client::query()
            ->with('user')
            ->when($this->clientSearch !== '', function ($q) {
                $q->where(function ($sub) {
                    $sub->where('company', 'like', '%'.$this->clientSearch.'%')
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.$this->clientSearch.'%')
                            ->orWhere('email', 'like', '%'.$this->clientSearch.'%'));
                });
            })
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        // Servicios del cliente preseleccionado (para dar contexto al ticket).
        $presetServices = $this->presetClientId
            ? Service::where('client_id', $this->presetClientId)->orderByDesc('id')->get()
            : collect();

        return view('livewire.admin.tickets.ticket-create', [
            'clients' => $clients,
            'presetServices' => $presetServices,
            'departmentOptions' => TicketDepartment::options(),
            'priorityOptions' => TicketPriority::options(),
            'criticalityOptions' => TicketCriticality::options(),
            'cannedResponses' => \App\Domain\Tickets\Models\CannedResponse::query()->orderBy('sort_order')->orderBy('title')->get(),
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
