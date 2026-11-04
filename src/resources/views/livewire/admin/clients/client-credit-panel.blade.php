<div class="mx-auto max-w-4xl">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Saldo a favor</h1>
            <p class="mt-1 text-sm text-slate-500">Cliente: {{ $client->user->name }} @if ($client->company)<span class="text-slate-400">({{ $client->company }})</span>@endif</p>
        </div>
        <a href="{{ route('admin.clients.show', $client) }}"
           class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            ← Volver al detalle
        </a>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">{{ session('status') }}</div>
    @endif

    {{-- Saldo actual --}}
    <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
        <div class="text-sm font-semibold uppercase tracking-wide text-slate-400">Saldo actual</div>
        <div class="mt-1 text-3xl font-semibold text-slate-900">{{ $client->creditBalance()->format() }}</div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        {{-- Formulario de ajuste --}}
        <div class="lg:col-span-1">
            <form wire:submit="save" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
                <h2 class="font-semibold text-slate-900">Ajustar saldo</h2>

                <div class="mt-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Tipo de movimiento</label>
                        <select wire:model="type" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            @foreach ($typeOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-400">Abono suma al saldo; cargo lo descuenta (nunca baja de cero).</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Monto (MXN)</label>
                        <input type="number" step="0.01" min="0" wire:model="amount"
                               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        @error('amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Motivo</label>
                        <input type="text" wire:model="reason" placeholder="Anticipo, nota de crédito, ajuste..."
                               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        @error('reason') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-5">
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                        Aplicar ajuste
                    </button>
                </div>
            </form>
        </div>

        {{-- Historial de movimientos --}}
        <div class="lg:col-span-2">
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="font-semibold text-slate-900">Historial de movimientos</h2>
                </div>
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Fecha</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3 text-right">Monto</th>
                            <th class="px-4 py-3 text-right">Saldo</th>
                            <th class="px-4 py-3">Motivo</th>
                            <th class="px-4 py-3">Por</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($movements as $movement)
                            <tr wire:key="mov-{{ $movement->id }}" class="transition hover:bg-slate-50/70">
                                <td class="px-4 py-3 text-slate-600">{{ $movement->created_at?->format('Y-m-d H:i') }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $movement->type?->badgeClasses() }}">
                                        {{ $movement->type?->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right {{ $movement->type === \App\Domain\Clients\Enums\CreditMovementType::Credit ? 'text-green-700' : 'text-amber-700' }}">
                                    {{ $movement->type === \App\Domain\Clients\Enums\CreditMovementType::Credit ? '+' : '−' }} {{ $movement->amount()->format() }}
                                </td>
                                <td class="px-4 py-3 text-right font-medium text-slate-800">{{ $movement->balanceAfter()->format() }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $movement->reason }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $movement->author?->name ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-slate-500">Sin movimientos registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $movements->links() }}</div>
        </div>
    </div>
</div>
