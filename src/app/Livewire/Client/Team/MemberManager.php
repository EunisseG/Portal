<?php

namespace App\Livewire\Client\Team;

use App\Domain\Clients\Models\Client;
use App\Domain\Clients\Services\ClientMemberService;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Permite al titular de una cuenta de cliente gestionar su (único) sub-usuario:
 * crearlo, editar su nombre y permisos, bloquear/reactivar y eliminar.
 * Solo el titular (no un sub-usuario) puede acceder.
 */
class MemberManager extends Component
{
    public Client $client;

    public bool $showForm = false;

    /** Sub-usuario en edición (null = alta). */
    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    /** @var array<int, string> */
    public array $permissions = [];

    public function mount(): void
    {
        $user = auth()->user();
        // Solo el titular gestiona el equipo; un sub-usuario no puede.
        abort_if($user->isSubUser(), 403);

        $client = $user->client;
        abort_unless($client !== null, 403);

        $this->client = $client;
    }

    public function startCreate(): void
    {
        $this->reset('editingId', 'name', 'email', 'permissions');
        // Por defecto, todos los permisos acotados activados.
        $this->permissions = RolesAndPermissionsSeeder::CLIENT_MEMBER_PERMISSIONS;
        $this->showForm = true;
    }

    public function edit(int $memberId): void
    {
        $member = $this->resolveMember($memberId);

        $this->editingId = $member->id;
        $this->name = $member->name;
        $this->email = $member->email;
        $this->permissions = $member->getPermissionNames()->all();
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->reset('editingId', 'name', 'email', 'permissions', 'showForm');
    }

    public function save(ClientMemberService $members): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                Rule::requiredIf($this->editingId === null),
                'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->editingId),
            ],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(RolesAndPermissionsSeeder::CLIENT_MEMBER_PERMISSIONS)],
        ]);

        if ($this->editingId) {
            $member = $this->resolveMember($this->editingId);
            $members->update($member, [
                'name' => $data['name'],
                'permissions' => $data['permissions'] ?? [],
            ]);
            session()->flash('status', 'Sub-usuario actualizado.');
        } else {
            $members->create($this->client, [
                'name' => $data['name'],
                'email' => $data['email'],
                'permissions' => $data['permissions'] ?? [],
            ]);
            session()->flash('status', 'Sub-usuario creado. Se le envió un enlace para establecer su contraseña.');
        }

        $this->cancel();
    }

    public function toggleBlock(int $memberId, ClientMemberService $members): void
    {
        $member = $this->resolveMember($memberId);

        if ($member->isBlocked()) {
            $members->unblock($member);
            session()->flash('status', 'Sub-usuario reactivado.');
        } else {
            $members->block($member);
            session()->flash('status', 'Sub-usuario bloqueado.');
        }
    }

    public function delete(int $memberId, ClientMemberService $members): void
    {
        $member = $this->resolveMember($memberId);
        $members->delete($member);
        session()->flash('status', 'Sub-usuario eliminado.');
    }

    /** Obtiene un sub-usuario garantizando que pertenece a este cliente. */
    private function resolveMember(int $memberId): User
    {
        return $this->client->members()->whereKey($memberId)->firstOrFail();
    }

    public function render(): View
    {
        $labels = [
            'client.invoices.view' => 'Ver facturas',
            'client.proofs.upload' => 'Subir comprobantes de pago',
            'client.tickets.view' => 'Ver tickets',
            'client.tickets.create' => 'Crear tickets',
            'client.services.view' => 'Ver servicios',
            'client.domains.view' => 'Ver dominios',
        ];

        return view('livewire.client.team.member-manager', [
            'members' => $this->client->members()->with('roles')->get(),
            'canAddMore' => $this->client->members()->count() < ClientMemberService::MAX_MEMBERS,
            'permissionLabels' => $labels,
        ])->layout('layouts.app', ['area' => 'client']);
    }
}
