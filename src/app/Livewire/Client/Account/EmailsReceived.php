<?php

namespace App\Livewire\Client\Account;

use App\Domain\Notifications\Models\EmailLog;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Sección "Emails Recibidos" de Mi Cuenta: muestra, solo lectura, los correos
 * que el portal envió al usuario. Se filtra por sus direcciones (principal y
 * secundaria) para que cada quien vea únicamente los suyos.
 */
class EmailsReceived extends Component
{
    use WithPagination;

    /** Correo cuyo detalle se está visualizando (null = ninguno). */
    public ?int $viewingId = null;

    public function view(int $id): void
    {
        // Garantiza que el correo pertenece al usuario antes de mostrarlo.
        if ($this->ownedQuery()->whereKey($id)->exists()) {
            $this->viewingId = $id;
        }
    }

    public function closeDetail(): void
    {
        $this->viewingId = null;
    }

    /** Consulta base acotada a las direcciones del usuario autenticado. */
    private function ownedQuery()
    {
        $user = auth()->user();

        $addresses = array_values(array_filter([
            $user->email,
            $user->secondary_email,
        ]));

        return EmailLog::query()->whereIn('recipient', $addresses);
    }

    public function render(): View
    {
        $logs = $this->ownedQuery()
            ->orderByDesc('sent_at')
            ->paginate(15);

        $detail = $this->viewingId
            ? $this->ownedQuery()->whereKey($this->viewingId)->first()
            : null;

        return view('livewire.client.account.emails-received', [
            'logs' => $logs,
            'detail' => $detail,
        ])->layout('layouts.app', ['area' => 'client']);
    }
}
