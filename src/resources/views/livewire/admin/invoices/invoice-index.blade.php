<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">Facturas</h1>
        @if ($tab === 'invoices')
            @can('create', App\Domain\Billing\Models\Invoice::class)
                <a href="{{ route('admin.invoices.create') }}"
                   class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                    Subir factura
                </a>
            @endcan
        @endif
    </div>

    {{-- Pestañas: Facturas / Comprobantes de pago --}}
    <div class="mt-4 border-b border-slate-200">
        <nav class="-mb-px flex gap-6 text-sm font-medium">
            <button type="button" wire:click="setTab('invoices')"
                    class="border-b-2 px-1 pb-3 {{ $tab === 'invoices' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                Facturas
            </button>
            @if ($canManageProofs)
                <button type="button" wire:click="setTab('proofs')"
                        class="border-b-2 px-1 pb-3 {{ $tab === 'proofs' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                    Comprobantes de pago
                </button>
            @endif
        </nav>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mt-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-600/10">{{ session('error') }}</div>
    @endif

    @if ($tab === 'invoices')
        {{-- Tarjetas KPI por estado --}}
        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50 text-lg font-semibold text-amber-600">{{ $kpis['pending'] }}</span>
                <span class="text-sm font-medium text-slate-600">Pendiente</span>
            </div>
            <div class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-50 text-lg font-semibold text-green-600">{{ $kpis['paid'] }}</span>
                <span class="text-sm font-medium text-slate-600">Pagada</span>
            </div>
            <div class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-red-50 text-lg font-semibold text-red-600">{{ $kpis['overdue'] }}</span>
                <span class="text-sm font-medium text-slate-600">Vencida</span>
            </div>
            <div class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-lg font-semibold text-slate-600">{{ $kpis['cancelled'] }}</span>
                <span class="text-sm font-medium text-slate-600">Cancelada</span>
            </div>
            <div class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-lg font-semibold text-blue-600">{{ $kpis['total'] }}</span>
                <span class="text-sm font-medium text-slate-600">Total</span>
            </div>
        </div>

        {{-- Gráfica de distribución de facturas por estado (barras) --}}
        <div class="mt-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
            <h2 class="text-sm font-semibold text-slate-900">Distribución de facturas por estado</h2>
            @if ($kpis['total'] > 0)
                <div class="mt-4 h-64">
                    <canvas data-status-chart
                            data-labels='@json($chart['labels'])'
                            data-values='@json($chart['data'])'></canvas>
                </div>
            @else
                <p class="mt-3 text-sm text-slate-500">Aún no hay facturas para graficar.</p>
            @endif
        </div>

        <p class="mt-4 text-sm text-slate-500">Sube el PDF de la factura (y opcionalmente su XML CFDI). El cliente podrá consultarla y descargarla.</p>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <x-admin.search-input placeholder="Buscar por folio, UUID o cliente..." :hint="false" />
            <select wire:model.live="statusFilter"
                    class="rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                <option value="">Todos los estados</option>
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" wire:model.live="onlyPending"
                       class="rounded border-slate-300 text-slate-800 focus:ring-slate-500">
                Solo pendientes
            </label>
        </div>

        <div class="mt-4 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Folio</th>
                        <th class="px-4 py-3">Cliente</th>
                        <th class="px-4 py-3">Total</th>
                        <th class="px-4 py-3">Emisión</th>
                        <th class="px-4 py-3">Vence</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($invoices as $invoice)
                        <tr wire:key="invoice-{{ $invoice->id }}" class="transition hover:bg-slate-50/70">
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $invoice->folio ?: '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $invoice->client->user->name }}</td>
                            <td class="px-4 py-3 text-slate-800">{{ $invoice->total()->format() }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $invoice->issued_at?->format('Y-m-d') ?: '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $invoice->due_at?->format('Y-m-d') ?: '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $invoice->status?->badgeClasses() }}">
                                    {{ $invoice->status?->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-3">
                                    @if ($invoice->pdf_path)
                                        <a href="{{ route('admin.invoices.download', $invoice) }}" class="text-slate-600 hover:underline">PDF</a>
                                    @endif
                                    @can('update', App\Domain\Billing\Models\Invoice::class)
                                        <a href="{{ route('admin.invoices.edit', $invoice) }}" class="text-slate-600 hover:text-slate-900 hover:underline">Editar</a>
                                        @if ($invoice->status !== \App\Domain\Billing\Enums\InvoiceStatus::Paid)
                                            <button type="button"
                                                    wire:click="changeStatus({{ $invoice->id }}, 'paid')"
                                                    class="text-green-600 hover:underline">Marcar pagada</button>
                                        @else
                                            <button type="button"
                                                    wire:click="changeStatus({{ $invoice->id }}, 'pending')"
                                                    class="text-amber-600 hover:underline">Marcar pendiente</button>
                                        @endif
                                    @endcan
                                    @can('delete', App\Domain\Billing\Models\Invoice::class)
                                        <button type="button" wire:click="delete({{ $invoice->id }})"
                                                wire:confirm="¿Eliminar esta factura y su archivo?"
                                                class="text-red-600 hover:underline">Eliminar</button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-slate-500">No hay facturas que coincidan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $invoices->links() }}</div>
    @elseif ($canManageProofs)
        <p class="mt-4 text-sm text-slate-500">Comprobantes enviados por los clientes. Revisa el archivo y aprueba o rechaza cada uno.</p>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <x-admin.search-input placeholder="Buscar por referencia, archivo o cliente..." :hint="false" />
            <select wire:model.live="proofStatusFilter"
                    class="rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                <option value="">Todos los estados</option>
                @foreach ($proofStatusOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="mt-4 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Cliente</th>
                        <th class="px-4 py-3">Monto</th>
                        <th class="px-4 py-3">Método</th>
                        <th class="px-4 py-3">Pago</th>
                        <th class="px-4 py-3">Factura</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($proofs as $proof)
                        <tr wire:key="proof-{{ $proof->id }}" class="transition hover:bg-slate-50/70">
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $proof->client->user->name }}</div>
                                <div class="text-xs text-slate-500">{{ $proof->client->user->email }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-800">{{ $proof->amount()->format() }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $proof->method?->label() }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $proof->paid_at?->format('Y-m-d') ?: '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $proof->invoice?->folio ?: ($proof->invoice_id ? '#'.$proof->invoice_id : '—') }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $proof->status?->badgeClasses() }}">
                                    {{ $proof->status?->label() }}
                                </span>
                                @if ($proof->status === \App\Domain\Billing\Enums\PaymentProofStatus::Rejected && $proof->review_note)
                                    <div class="mt-1 text-xs text-slate-500">{{ $proof->review_note }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('admin.payment-proofs.download', $proof) }}" class="text-slate-600 hover:underline">Ver archivo</a>
                                    @can('manage', App\Domain\Billing\Models\PaymentProof::class)
                                        @if ($proof->status !== \App\Domain\Billing\Enums\PaymentProofStatus::Approved)
                                            <button type="button" wire:click="approveProof({{ $proof->id }})"
                                                    class="text-green-600 hover:underline">Aprobar</button>
                                            @if ($proof->invoice_id && auth()->user()?->can('payments.record'))
                                                <button type="button" wire:click="approveProof({{ $proof->id }}, true)"
                                                        class="text-green-700 hover:underline">Aprobar y registrar pago</button>
                                            @endif
                                        @endif
                                        @if ($proof->status !== \App\Domain\Billing\Enums\PaymentProofStatus::Rejected)
                                            <button type="button" wire:click="startReject({{ $proof->id }})"
                                                    class="text-red-600 hover:underline">Rechazar</button>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                        @if ($rejectingId === $proof->id)
                            <tr wire:key="reject-{{ $proof->id }}" class="bg-red-50/40">
                                <td colspan="7" class="px-4 py-3">
                                    <form wire:submit="rejectProof" class="flex flex-wrap items-end gap-3">
                                        <div class="flex-1 min-w-[16rem]">
                                            <label class="block text-xs font-medium text-slate-600">Motivo del rechazo</label>
                                            <textarea wire:model="rejectNote" rows="2"
                                                      class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500"
                                                      placeholder="Explica al cliente por qué se rechaza el comprobante..."></textarea>
                                            @error('rejectNote') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <button type="submit" class="rounded-md bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-700">Confirmar rechazo</button>
                                            <button type="button" wire:click="cancelReject" class="rounded-md px-3 py-2 text-sm text-slate-600 hover:text-slate-900">Cancelar</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-slate-500">No hay comprobantes que coincidan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $proofs->links() }}</div>
    @endif

    @push('scripts')
        @include('partials.status-bar-chart-script')
    @endpush
</div>
