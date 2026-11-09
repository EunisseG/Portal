<div>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Órdenes de pago</h1>
            <p class="mt-1 text-slate-600">Cobros de cada pedido. No son el CFDI: la factura se genera al confirmarse el pago.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($canManageInvoices)
                <button type="button" wire:click="toggleOrderForm"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Generar orden
                </button>
            @endif
            @if ($canManageProofs)
                <button type="button" wire:click="toggleProofForm"
                        class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                    Subir comprobante
                </button>
            @endif
        </div>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mt-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-600/10">{{ session('error') }}</div>
    @endif

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
            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-lg font-semibold text-slate-600">{{ $kpis['expired'] }}</span>
            <span class="text-sm font-medium text-slate-600">Expirada</span>
        </div>
        <div class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-red-50 text-lg font-semibold text-red-600">{{ $kpis['cancelled'] }}</span>
            <span class="text-sm font-medium text-slate-600">Cancelada</span>
        </div>
        <div class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-lg font-semibold text-blue-600">{{ $kpis['total'] }}</span>
            <span class="text-sm font-medium text-slate-600">Total</span>
        </div>
    </div>

    {{-- Gráfica de distribución de órdenes de pago por estado (barras) --}}
    <div class="mt-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
        <h2 class="text-sm font-semibold text-slate-900">Distribución de órdenes de pago por estado</h2>
        @if ($kpis['total'] > 0)
            <div class="mt-4 h-64">
                <canvas data-status-chart
                        data-labels='@json($chart['labels'])'
                        data-values='@json($chart['data'])'></canvas>
            </div>
        @else
            <p class="mt-3 text-sm text-slate-500">Aún no hay órdenes de pago para graficar.</p>
        @endif
    </div>

    {{-- Formulario: generar orden de pago (cliente + servicio) --}}
    @if ($showOrderForm)
        <form wire:submit="createOrder" class="mt-4 space-y-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-indigo-200">
            <h2 class="font-semibold text-slate-900">Generar orden de pago</h2>
            <p class="text-xs text-slate-500">Se crea el cobro del servicio elegido. En modo manual queda sin pasarela; en automático genera el enlace de pago.</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Cliente</label>
                    <select wire:model.live="orderClientId" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Selecciona un cliente</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}">{{ $client->user?->name }} — {{ $client->company ?: $client->user?->email }}</option>
                        @endforeach
                    </select>
                    @error('orderClientId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Servicio (pedido)</label>
                    <select wire:model="orderServiceId" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" @disabled($orderClientId === '')>
                        <option value="">{{ $orderClientId === '' ? 'Elige primero un cliente' : 'Selecciona un servicio' }}</option>
                        @foreach ($orderServices as $svc)
                            <option value="{{ $svc->id }}">{{ $svc->name }} — {{ $svc->total()->format() }}</option>
                        @endforeach
                    </select>
                    @error('orderServiceId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="flex items-center gap-3 border-t border-slate-100 pt-4">
                <button type="submit" wire:loading.attr="disabled" wire:target="createOrder"
                        class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                    <span wire:loading.remove wire:target="createOrder">Generar orden</span>
                    <span wire:loading wire:target="createOrder">Generando...</span>
                </button>
                <button type="button" wire:click="toggleOrderForm" class="text-sm font-medium text-slate-500 hover:text-slate-700">Cancelar</button>
            </div>
        </form>
    @endif

    {{-- Formulario: subir comprobante a nombre de un cliente --}}
    @if ($showProofForm)
        <form wire:submit="submitProof" class="mt-4 space-y-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/10">
            <h2 class="font-semibold text-slate-900">Subir comprobante de pago</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Cliente</label>
                    <select wire:model.live="proofClientId" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Selecciona un cliente</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}">{{ $client->user?->name }} — {{ $client->company ?: $client->user?->email }}</option>
                        @endforeach
                    </select>
                    @error('proofClientId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Orden de pago (opcional)</label>
                    <select wire:model.live="proofOrderId" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" @disabled($proofClientId === '')>
                        <option value="">Sin orden</option>
                        @foreach ($proofOrders as $po)
                            <option value="{{ $po->id }}">{{ $po->folio }} — {{ $po->amount()->format() }}</option>
                        @endforeach
                    </select>
                    @error('proofOrderId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-slate-700">Archivo (PDF, JPG o PNG)</label>
                    <input type="file" wire:model="proofFile" accept=".pdf,.jpg,.jpeg,.png"
                           class="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                    <div wire:loading wire:target="proofFile" class="mt-1 text-xs text-indigo-500">Subiendo...</div>
                    @error('proofFile') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Monto (MXN)</label>
                    <input type="number" step="0.01" min="0" wire:model="proofAmount"
                           class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('proofAmount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Método de pago</label>
                    <select wire:model="proofMethod" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach ($methodOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('proofMethod') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Fecha de pago</label>
                    <input type="date" wire:model="proofPaidAt" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('proofPaidAt') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Referencia (opcional)</label>
                    <input type="text" wire:model="proofReference" placeholder="No. de operación, folio, etc."
                           class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('proofReference') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            @if ($canMarkPaid)
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" wire:model="proofApproveNow" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    Aprobar de inmediato (si tiene orden, la marca pagada y genera su factura)
                </label>
            @endif
            <div class="flex items-center gap-3 border-t border-slate-100 pt-4">
                <button type="submit" wire:loading.attr="disabled" wire:target="submitProof,proofFile"
                        class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50">
                    <span wire:loading.remove wire:target="submitProof">Subir comprobante</span>
                    <span wire:loading wire:target="submitProof">Subiendo...</span>
                </button>
                <button type="button" wire:click="toggleProofForm" class="text-sm font-medium text-slate-500 hover:text-slate-700">Cancelar</button>
            </div>
        </form>
    @endif

    {{-- Confirmación para marcar una orden como pagada --}}
    @if ($markingOrder)
        <div class="mt-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-amber-300">
            <h2 class="text-sm font-semibold text-slate-900">Marcar {{ $markingOrder->folio }} como pagada</h2>
            <p class="mt-1 text-xs text-slate-500">
                Se generará la factura del pedido (pendiente de timbrar).
                @if ($markingOrder->amount_cents > 0)
                    Se registrará un pago de {{ $markingOrder->amount()->format() }}.
                @else
                    La orden no tiene costo, no se registra ningún pago.
                @endif
            </p>
            @if ($markingOrder->amount_cents > 0)
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium text-slate-700">Método de pago</label>
                        <select wire:model="markMethod" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            @foreach ($methodOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('markMethod') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700">Referencia (opcional)</label>
                        <input type="text" wire:model="markReference" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        @error('markReference') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            @endif
            <div class="mt-4 flex items-center gap-3">
                <button type="button" wire:click="confirmMark" wire:loading.attr="disabled" wire:target="confirmMark"
                        class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="confirmMark">Confirmar pago</span>
                    <span wire:loading wire:target="confirmMark">Procesando...</span>
                </button>
                <button type="button" wire:click="cancelMark" class="text-sm font-medium text-slate-500 hover:text-slate-700">Cancelar</button>
            </div>
        </div>
    @endif

    <div class="mt-6 flex flex-wrap gap-3">
        <x-admin.search-input placeholder="Buscar por folio, referencia o cliente..." :hint="false" />
        <select wire:model.live="statusFilter"
                class="rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
            <option value="">Todos los estados</option>
            @foreach ($statusOptions as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="mt-4 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Folio</th>
                    <th class="px-4 py-3">Cliente</th>
                    <th class="px-4 py-3">Pedido</th>
                    <th class="px-4 py-3 text-right">Monto</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3">Vence</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($orders as $order)
                    <tr wire:key="po-{{ $order->id }}" class="transition hover:bg-slate-50/70">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $order->folio }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $order->client?->user?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $order->concept ?: ($order->service?->name ?: '—') }}</td>
                        <td class="px-4 py-3 text-right text-slate-800">{{ $order->amount()->format() }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $order->status?->badgeClasses() }}">{{ $order->status?->label() }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $order->expires_at?->format('Y-m-d H:i') ?: '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                @if ($order->pending_proofs_count > 0)
                                    <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700" title="Hay un comprobante por revisar más abajo.">Comprobante en revisión</span>
                                @endif
                                @if ($canMarkPaid && $order->isPending())
                                    <button type="button" wire:click="startMark({{ $order->id }})"
                                            class="rounded-md bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700 transition hover:bg-green-100">Marcar pagada</button>
                                @endif
                                <a href="{{ route('admin.payment-orders.receipt.pdf', $order) }}"
                                   class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 transition hover:bg-slate-200">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                                    Recibo
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No hay órdenes de pago que coincidan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $orders->links('pagination::tailwind', ['pageName' => 'ordenes']) }}</div>

    {{-- Comprobantes recibidos --}}
    @if ($canManageProofs && $proofs !== null)
        <div class="mt-10 border-t border-slate-200 pt-6">
            <h2 class="text-lg font-semibold text-slate-900">Comprobantes recibidos</h2>
            <p class="mt-1 text-sm text-slate-500">Comprobantes enviados por los clientes (o subidos desde aquí). Revisa el archivo y aprueba o rechaza.</p>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-3">
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
                        <th class="px-4 py-3">Orden</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($proofs as $proof)
                        <tr wire:key="proof-{{ $proof->id }}" class="transition hover:bg-slate-50/70">
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $proof->client?->user?->name }}</div>
                                <div class="text-xs text-slate-500">{{ $proof->client?->user?->email }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-800">{{ $proof->amount()->format() }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $proof->method?->label() }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $proof->paymentOrder?->folio ?: '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $proof->status?->badgeClasses() }}">{{ $proof->status?->label() }}</span>
                                @if ($proof->status === \App\Domain\Billing\Enums\PaymentProofStatus::Rejected && $proof->review_note)
                                    <div class="mt-1 text-xs text-slate-500">{{ $proof->review_note }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('admin.payment-proofs.download', $proof) }}" class="text-slate-600 hover:underline">Ver archivo</a>
                                    @if ($proof->status !== \App\Domain\Billing\Enums\PaymentProofStatus::Approved)
                                        <button type="button" wire:click="approveProof({{ $proof->id }})"
                                                class="text-green-600 hover:underline">{{ $proof->payment_order_id ? 'Aprobar y marcar pagada' : 'Aprobar' }}</button>
                                    @endif
                                    @if ($proof->status !== \App\Domain\Billing\Enums\PaymentProofStatus::Rejected)
                                        <button type="button" wire:click="startReject({{ $proof->id }})" class="text-red-600 hover:underline">Rechazar</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @if ($rejectingId === $proof->id)
                            <tr wire:key="reject-{{ $proof->id }}" class="bg-red-50/40">
                                <td colspan="6" class="px-4 py-3">
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
                        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No hay comprobantes que coincidan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $proofs->links('pagination::tailwind', ['pageName' => 'proofs']) }}</div>
    @endif

    @push('scripts')
        @include('partials.status-bar-chart-script')
    @endpush
</div>
