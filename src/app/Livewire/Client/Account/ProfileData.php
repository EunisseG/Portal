<?php

namespace App\Livewire\Client\Account;

use App\Domain\Clients\Models\Client;
use App\Models\User;
use App\Support\Countries;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * "Mis Datos": el cliente edita su perfil (datos personales + dirección).
 * Guarda en users (nombre/apellido/email) y en su Client (empresa, teléfono,
 * dirección estructurada, contacto de facturación).
 */
class ProfileData extends Component
{
    // Datos de la cuenta (users).
    public string $name = '';
    public string $last_name = '';
    public string $email = '';

    // Datos del cliente (clients).
    public string $company = '';
    public string $phone = '';
    public string $tax_id = '';
    public string $address_line1 = '';
    public string $address_line2 = '';
    public string $city = '';
    public string $state = '';
    public string $postal_code = '';
    public string $country = '';
    public string $billing_contact = 'owner';

    public function mount(): void
    {
        $user = auth()->user();
        $client = $user->clientAccount();
        abort_unless($client !== null, 403);

        $this->name = (string) $user->name;
        $this->last_name = (string) $user->last_name;
        $this->email = (string) $user->email;

        $this->company = (string) $client->company;
        $this->phone = (string) $client->phone;
        $this->tax_id = (string) $client->tax_id;
        $this->address_line1 = (string) $client->address_line1;
        $this->address_line2 = (string) $client->address_line2;
        $this->city = (string) $client->city;
        $this->state = (string) $client->state;
        $this->postal_code = (string) $client->postal_code;
        $this->country = (string) ($client->country ?: 'MX');
        $this->billing_contact = (string) ($client->billing_contact ?: 'owner');
    }

    public function save(): void
    {
        $user = auth()->user();
        $client = $user->clientAccount();
        abort_unless($client !== null, 403);

        // Un sub-usuario solo puede editar sus datos de cuenta, no los del
        // titular (empresa/dirección de facturación de la cuenta).
        $isSubUser = $user->isSubUser();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ];

        if (! $isSubUser) {
            $rules += [
                'company' => ['nullable', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:50'],
                'tax_id' => ['nullable', 'string', 'max:50'],
                'address_line1' => ['nullable', 'string', 'max:255'],
                'address_line2' => ['nullable', 'string', 'max:255'],
                'city' => ['nullable', 'string', 'max:120'],
                'state' => ['nullable', 'string', 'max:120'],
                'postal_code' => ['nullable', 'string', 'max:20'],
                'country' => ['nullable', 'string', Rule::in(array_keys(Countries::options()))],
                'billing_contact' => ['nullable', 'string', 'max:50'],
            ];
        }

        $data = $this->validate($rules);

        // Si cambió el email de login y el usuario requiere verificación, se
        // reinicia la verificación (mismo criterio que Fortify).
        $emailChanged = $data['email'] !== $user->email;

        $user->forceFill([
            'name' => $data['name'],
            'last_name' => $data['last_name'] ?: null,
            'email' => $data['email'],
            'email_verified_at' => $emailChanged ? null : $user->email_verified_at,
        ])->save();

        if ($emailChanged && $user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail) {
            $user->sendEmailVerificationNotification();
        }

        if (! $isSubUser) {
            $client->fill([
                'company' => $data['company'] ?: null,
                'phone' => $data['phone'] ?: null,
                'tax_id' => $data['tax_id'] ?: null,
                'address_line1' => $data['address_line1'] ?: null,
                'address_line2' => $data['address_line2'] ?: null,
                'city' => $data['city'] ?: null,
                'state' => $data['state'] ?: null,
                'postal_code' => $data['postal_code'] ?: null,
                'country' => $data['country'] ?: null,
                'billing_contact' => $data['billing_contact'] ?: 'owner',
            ])->save();
        }

        session()->flash('status', 'Tus datos se actualizaron correctamente.');
    }

    public function render(): View
    {
        $user = auth()->user();
        $client = $user->clientAccount();

        // Opciones de contacto de facturación: titular + sub-usuarios.
        $billingContacts = ['owner' => 'Titular ('.$client->user->fullName().')'];
        foreach ($client->members as $member) {
            $billingContacts[(string) $member->id] = $member->fullName().' ('.$member->email.')';
        }

        return view('livewire.client.account.profile-data', [
            'countries' => Countries::options(),
            'billingContacts' => $billingContacts,
            'isSubUser' => $user->isSubUser(),
        ])->layout('layouts.app', ['area' => 'client']);
    }
}
