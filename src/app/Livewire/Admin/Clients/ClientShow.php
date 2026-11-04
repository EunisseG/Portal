<?php

namespace App\Livewire\Admin\Clients;

use App\Domain\Clients\Models\Client;
use App\Domain\Clients\Services\ClientService;
use App\Domain\Clients\Services\SendAccountSetupLink;
use App\Domain\Tickets\Enums\TicketCriticality;
use App\Domain\Tickets\Models\ClientSlaPolicy;
use App\Domain\Tickets\Services\SlaResolver;
use App\Support\Countries;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

class ClientShow extends Component
{
    public Client $client;

    /** Pestaña activa: datos | servicios | dominios | facturas | tickets. */
    #[Url(as: 'tab')]
    public string $tab = 'datos';

    // ===== Campos editables de la pestaña "Datos" =====
    public string $name = '';
    public string $last_name = '';
    public string $email = '';
    public string $secondary_email = '';
    public string $company = '';
    public string $phone = '';
    public string $whatsapp = '';
    public string $tax_id = '';
    public string $billing_address = '';
    public string $address_line1 = '';
    public string $address_line2 = '';
    public string $city = '';
    public string $state = '';
    public string $postal_code = '';
    public string $country = 'MX';
    public string $billing_contact = 'owner';

    /**
     * Overrides de SLA del cliente, por criticidad:
     * [criticality => ['first' => ?int, 'resolution' => ?int]].
     * Vacío = usa el SLA global.
     *
     * @var array<string, array{first: ?int, resolution: ?int}>
     */
    public array $slaOverrides = [];

    public function setTab(string $tab): void
    {
        $allowed = ['datos', 'servicios', 'dominios', 'facturas', 'tickets', 'sla'];
        $this->tab = in_array($tab, $allowed, true) ? $tab : 'datos';
    }

    public function mount(Client $client): void
    {
        Gate::authorize('view', Client::class);
        $this->client = $client->load([
            'user',
            'members' => fn ($q) => $q->orderBy('name'),
            'members.roles',
            'services' => fn ($q) => $q->orderByDesc('id'),
            'services.addons',
            'domains' => fn ($q) => $q->orderBy('name'),
            'invoices' => fn ($q) => $q->orderByDesc('issued_at')->orderByDesc('id'),
            'tickets' => fn ($q) => $q->orderByDesc('last_reply_at'),
        ]);

        $this->fillFormFromClient();
    }

    /** Carga los campos editables desde el cliente actual. */
    private function fillFormFromClient(): void
    {
        $user = $this->client->user;

        $this->name = (string) $user->name;
        $this->last_name = (string) $user->last_name;
        $this->email = (string) $user->email;
        $this->secondary_email = (string) $user->secondary_email;

        $this->company = (string) $this->client->company;
        $this->phone = (string) $this->client->phone;
        $this->whatsapp = (string) $this->client->whatsapp;
        $this->tax_id = (string) $this->client->tax_id;
        $this->billing_address = (string) $this->client->billing_address;
        $this->address_line1 = (string) $this->client->address_line1;
        $this->address_line2 = (string) $this->client->address_line2;
        $this->city = (string) $this->client->city;
        $this->state = (string) $this->client->state;
        $this->postal_code = (string) $this->client->postal_code;
        $this->country = (string) ($this->client->country ?: 'MX');
        $this->billing_contact = (string) ($this->client->billing_contact ?: 'owner');

        $this->loadSlaOverrides();
    }

    /** Carga los overrides de SLA del cliente en el formulario (por criticidad). */
    private function loadSlaOverrides(): void
    {
        $existing = ClientSlaPolicy::where('client_id', $this->client->id)
            ->get()
            ->keyBy(fn (ClientSlaPolicy $p) => $p->criticality->value);

        $this->slaOverrides = [];
        foreach (TicketCriticality::cases() as $c) {
            $row = $existing->get($c->value);
            $this->slaOverrides[$c->value] = [
                'first' => $row?->first_response_minutes,
                'resolution' => $row?->resolution_minutes,
            ];
        }
    }

    /** Guarda (crea/actualiza) el override de SLA del cliente para una criticidad. */
    public function saveSlaOverride(string $criticality): void
    {
        Gate::authorize('sla.manage');

        $crit = TicketCriticality::tryFrom($criticality);
        if ($crit === null) {
            return;
        }

        $first = (int) ($this->slaOverrides[$criticality]['first'] ?? 0);
        $resolution = (int) ($this->slaOverrides[$criticality]['resolution'] ?? 0);

        if ($first < 1 || $resolution < 1) {
            $this->addError('sla_'.$criticality, 'Indica minutos válidos (mayores a 0) para respuesta y resolución.');

            return;
        }
        if ($resolution < $first) {
            $this->addError('sla_'.$criticality, 'La resolución no puede ser menor que la primera respuesta.');

            return;
        }

        ClientSlaPolicy::updateOrCreate(
            ['client_id' => $this->client->id, 'criticality' => $crit->value],
            ['first_response_minutes' => $first, 'resolution_minutes' => $resolution],
        );

        $this->resetErrorBag('sla_'.$criticality);
        $this->loadSlaOverrides();
        session()->flash('status', 'SLA del cliente actualizado para criticidad '.$crit->label().'.');
    }

    /** Elimina el override del cliente para una criticidad (vuelve al SLA global). */
    public function clearSlaOverride(string $criticality): void
    {
        Gate::authorize('sla.manage');

        $crit = TicketCriticality::tryFrom($criticality);
        if ($crit === null) {
            return;
        }

        ClientSlaPolicy::where('client_id', $this->client->id)
            ->where('criticality', $crit->value)
            ->delete();

        $this->loadSlaOverrides();
        session()->flash('status', 'SLA del cliente restablecido al global para criticidad '.$crit->label().'.');
    }

    /**
     * Opciones de "Contacto de facturación predeterminado": titular + sub-usuarios.
     *
     * @return array<string, string>
     */
    public function billingContactOptions(): array
    {
        $options = ['owner' => 'Titular ('.($this->client->user->fullName() ?: $this->client->user->name).')'];
        foreach ($this->client->members as $member) {
            $options[(string) $member->id] = $member->fullName().' ('.$member->email.')';
        }

        return $options;
    }

    /**
     * Guarda los datos del cliente editados en línea. Reutiliza la misma lógica
     * (y auditoría) que el formulario de alta/edición.
     */
    public function save(ClientService $clients): void
    {
        Gate::authorize('update', Client::class);

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->client->user->id),
            ],
            'secondary_email' => ['nullable', 'email', 'max:255', 'different:email'],
            'company' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'billing_address' => ['nullable', 'string', 'max:500'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', Rule::in(array_keys(Countries::options()))],
            'billing_contact' => ['nullable', 'string', Rule::in(array_map('strval', array_keys($this->billingContactOptions())))],
        ]);

        $clients->update($this->client, [
            'name' => $data['name'],
            'last_name' => $data['last_name'] ?: null,
            'email' => $data['email'],
            'secondary_email' => $data['secondary_email'] ?: null,
            'company' => $data['company'] ?: null,
            'phone' => $data['phone'] ?: null,
            'whatsapp' => $data['whatsapp'] ?: null,
            'tax_id' => $data['tax_id'] ?: null,
            'billing_address' => $data['billing_address'] ?: null,
            'address_line1' => $data['address_line1'] ?: null,
            'address_line2' => $data['address_line2'] ?: null,
            'city' => $data['city'] ?: null,
            'state' => $data['state'] ?: null,
            'postal_code' => $data['postal_code'] ?: null,
            'country' => $data['country'] ?: null,
            'billing_contact' => $data['billing_contact'] ?: 'owner',
        ]);

        // Refresca el modelo y los campos del formulario con lo guardado.
        $this->client->refresh()->load([
            'user',
            'members' => fn ($q) => $q->orderBy('name'),
            'members.roles',
        ]);
        $this->fillFormFromClient();

        session()->flash('status', 'Datos del cliente actualizados.');
    }

    /** Muestra el selector de destino para reenviar la contraseña. */
    public bool $showResendForm = false;

    /** Destino elegido: 'primary' o 'secondary'. */
    public string $resendTarget = 'primary';

    public function startResend(): void
    {
        Gate::authorize('update', Client::class);
        $this->resendTarget = 'primary';
        $this->showResendForm = true;
    }

    public function cancelResend(): void
    {
        $this->showResendForm = false;
    }

    /**
     * Reenvía al cliente el enlace para establecer/restablecer su contraseña,
     * al correo principal o al secundario (si lo tiene). Reutiliza el flujo
     * seguro de token de Fortify (la contraseña nunca viaja).
     */
    public function resendPasswordLink(SendAccountSetupLink $setupLink): void
    {
        Gate::authorize('update', Client::class);

        $user = $this->client->user;

        // Resuelve la dirección destino de forma segura (solo principal o
        // secundario del propio cliente).
        $to = $user->email;
        if ($this->resendTarget === 'secondary' && ! empty($user->secondary_email)) {
            $to = $user->secondary_email;
        }

        $setupLink->send($user, toEmail: $to, isReset: true);

        $this->showResendForm = false;
        session()->flash('status', 'Se envió el enlace de contraseña a '.$to.'.');
    }

    /**
     * Desactiva la autenticación en dos pasos (2FA) de un usuario de la cuenta
     * (el titular o uno de sus sub-usuarios). Pensado para cuando el cliente
     * pierde el acceso a su app de autenticación. Solo opera sobre usuarios que
     * pertenezcan a este cliente, nunca sobre un usuario arbitrario.
     */
    public function disableTwoFactor(int $userId): void
    {
        Gate::authorize('update', Client::class);

        // Resuelve el usuario garantizando que pertenece a esta cuenta: es el
        // titular o uno de sus sub-usuarios.
        $target = null;
        if ((int) $this->client->user->id === $userId) {
            $target = $this->client->user;
        } else {
            $target = $this->client->members->firstWhere('id', $userId);
        }

        abort_unless($target !== null, 403);

        $target->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        // Refresca las relaciones para que la vista muestre el nuevo estado.
        $this->client->refresh()->load(['user', 'members' => fn ($q) => $q->orderBy('name'), 'members.roles']);

        session()->flash('status', 'Se desactivó la autenticación en dos pasos de '.($target->fullName() ?: $target->name).'.');
    }

    public function render(): View
    {
        // SLA global (referencia) por criticidad, para mostrar el valor por
        // defecto cuando el cliente no tiene override.
        $resolver = app(SlaResolver::class);
        $slaRows = [];
        foreach (TicketCriticality::cases() as $c) {
            $global = $resolver->forClientCriticality(null, $c);
            $hasOverride = ($this->slaOverrides[$c->value]['first'] ?? null) !== null;
            $slaRows[] = [
                'value' => $c->value,
                'label' => $c->label(),
                'global_first' => $global['first_response_minutes'],
                'global_resolution' => $global['resolution_minutes'],
                'has_override' => $hasOverride,
            ];
        }

        return view('livewire.admin.clients.client-show', [
            'countries' => Countries::options(),
            'billingContacts' => $this->billingContactOptions(),
            'slaRows' => $slaRows,
            'canManageSla' => Gate::allows('sla.manage'),
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
