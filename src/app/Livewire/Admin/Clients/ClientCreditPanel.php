<?php

namespace App\Livewire\Admin\Clients;

use App\Domain\Clients\Enums\CreditMovementType;
use App\Domain\Clients\Models\Client;
use App\Domain\Clients\Services\ClientCreditService;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Gestión del saldo a favor de un cliente: muestra el saldo actual, permite
 * abonar o cargar (con motivo) y lista el historial de movimientos. Solo para
 * quien tenga el permiso client_credit.manage.
 */
class ClientCreditPanel extends Component
{
    use WithPagination;

    public Client $client;

    /** Tipo de movimiento: credit (abono) o debit (cargo). */
    public string $type = 'credit';

    public string $amount = '';

    public string $reason = '';

    public function mount(Client $client): void
    {
        Gate::authorize('manageCredit', $client);
        $this->client = $client->load('user');
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(CreditMovementType::class)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function save(ClientCreditService $credit): void
    {
        Gate::authorize('manageCredit', $this->client);

        $data = $this->validate([
            'type' => ['required', Rule::enum(CreditMovementType::class)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:255'],
        ], [], [
            'amount' => 'monto',
            'reason' => 'motivo',
        ]);

        $credit->adjust(
            $this->client,
            CreditMovementType::from($data['type']),
            Money::fromDecimal($data['amount'])->amountInCents,
            $data['reason'],
        );

        $this->reset('amount', 'reason');
        $this->type = 'credit';
        $this->resetPage();
        $this->client->refresh();

        session()->flash('status', 'Saldo a favor actualizado.');
    }

    public function render(): View
    {
        $movements = $this->client->creditMovements()->with('author')->paginate(10);

        return view('livewire.admin.clients.client-credit-panel', [
            'movements' => $movements,
            'typeOptions' => CreditMovementType::options(),
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
