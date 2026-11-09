<?php

namespace App\Livewire\Admin\Clients;

use App\Domain\Clients\Models\Client;
use App\Domain\Clients\Services\ClientService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ClientForm extends Component
{
    public ?Client $client = null;

    public string $name = '';
    public string $last_name = '';
    public string $email = '';
    public string $secondary_email = '';
    public string $company = '';
    public string $phone = '';
    public string $whatsapp = '';
    public string $tax_id = '';
    public string $billing_address = '';

    // Dirección estructurada + contacto de facturación.
    public string $address_line1 = '';
    public string $address_line2 = '';
    public string $city = '';
    public string $state = '';
    public string $postal_code = '';
    public string $country = 'MX';
    public string $billing_contact = 'owner';

    /** Al crear: opción de ligar un producto del catálogo como servicio inicial. */
    public bool $addProduct = false;

    public ?int $productId = null;

    /** Ciclo elegido para el servicio inicial cuando el producto es recurrente. */
    public string $productCycle = 'monthly';

    public function mount(?Client $client = null): void
    {
        if ($client && $client->exists) {
            Gate::authorize('update', Client::class);
            $this->client = $client->load(['user', 'members']);
            $this->name = $client->user->name;
            $this->last_name = (string) $client->user->last_name;
            $this->email = $client->user->email;
            $this->secondary_email = (string) $client->user->secondary_email;
            $this->company = (string) $client->company;
            $this->phone = (string) $client->phone;
            $this->whatsapp = (string) $client->whatsapp;
            $this->tax_id = (string) $client->tax_id;
            $this->billing_address = (string) $client->billing_address;
            $this->address_line1 = (string) $client->address_line1;
            $this->address_line2 = (string) $client->address_line2;
            $this->city = (string) $client->city;
            $this->state = (string) $client->state;
            $this->postal_code = (string) $client->postal_code;
            $this->country = (string) ($client->country ?: 'MX');
            $this->billing_contact = (string) ($client->billing_contact ?: 'owner');
        } else {
            Gate::authorize('create', Client::class);
        }
    }

    public function isEditing(): bool
    {
        return $this->client !== null;
    }

    /**
     * Opciones de "Contacto de facturación predeterminado": el titular y, si el
     * cliente ya existe, sus sub-usuarios.
     *
     * @return array<string, string>
     */
    public function billingContactOptions(): array
    {
        if (! $this->client) {
            return ['owner' => 'Titular'];
        }

        $options = ['owner' => 'Titular ('.($this->client->user->fullName() ?: $this->client->user->name).')'];
        foreach ($this->client->members as $member) {
            $options[(string) $member->id] = $member->fullName().' ('.$member->email.')';
        }

        return $options;
    }

    /**
     * Valores válidos para billing_contact (claves de las opciones).
     *
     * @return list<string>
     */
    private function billingContactValues(): array
    {
        return array_map('strval', array_keys($this->billingContactOptions()));
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $userId = $this->client?->user?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($userId),
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
            'country' => ['nullable', 'string', Rule::in(array_keys(\App\Support\Countries::options()))],
            'billing_contact' => ['nullable', 'string', Rule::in($this->billingContactValues())],
            'addProduct' => ['boolean'],
            'productId' => [
                Rule::requiredIf(fn () => ! $this->isEditing() && $this->addProduct),
                'nullable', 'integer', 'exists:products,id',
            ],
            'productCycle' => ['nullable', Rule::enum(\App\Domain\Services\Enums\BillingCycle::class)],
        ];
    }

    /**
     * Mensajes claros para la validación (en especial el email ya registrado).
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'email.unique' => 'Ya existe un usuario registrado con este correo. Usa uno distinto.',
            'secondary_email.different' => 'El correo secundario debe ser distinto del principal.',
        ];
    }

    public function save(ClientService $clients): mixed
    {
        $data = $this->validate();

        $payload = [
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
        ];

        if ($this->isEditing()) {
            $clients->update($this->client, $payload);
            session()->flash('status', 'Cliente actualizado.');
        } else {
            $client = $clients->create($payload);

            // Servicio inicial opcional a partir de un producto del catálogo.
            if ($this->addProduct && ! empty($data['productId'])) {
                $this->createInitialService($client, (int) $data['productId'], $data['productCycle'] ?? 'monthly');
                session()->flash('status', 'Cliente creado con su servicio inicial. Se le envió un enlace para establecer su contraseña.');
            } else {
                session()->flash('status', 'Cliente creado. Se le envió un enlace para establecer su contraseña.');
            }
        }

        return $this->redirect(route('admin.clients.index'), navigate: true);
    }

    /**
     * Crea un servicio inicial para el cliente a partir de un producto del
     * catálogo, junto con su orden de pago (ver ProductOrderService).
     */
    private function createInitialService(Client $client, int $productId, string $cycleValue): void
    {
        $product = \App\Domain\Services\Models\Product::with('prices')->find($productId);

        if (! $product) {
            return;
        }

        // Un fallo (p. ej. de la pasarela) no debe impedir dar de alta al cliente.
        try {
            app(\App\Domain\Services\Services\ProductOrderService::class)->place(
                $client,
                $product,
                $cycleValue,
                \App\Domain\Services\Enums\ServiceStatus::Active,
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function render(): View
    {
        $products = $this->isEditing()
            ? collect()
            : \App\Domain\Services\Models\Product::where('is_active', true)->orderBy('name')->get();

        // Producto seleccionado (para saber si mostrar el selector de ciclo).
        $selectedProduct = $this->productId
            ? $products->firstWhere('id', (int) $this->productId)
            : null;

        return view('livewire.admin.clients.client-form', [
            'products' => $products,
            'selectedProduct' => $selectedProduct,
            'cycleOptions' => \App\Domain\Services\Enums\BillingCycle::options(),
            'countries' => \App\Support\Countries::options(),
            'billingContacts' => $this->billingContactOptions(),
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
