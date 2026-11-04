<div>
    @include('partials.client-page-header', [
        'title' => 'Mis facturas',
        'subtitle' => 'Consulta tus facturas y envía tus comprobantes de pago.',
    ])

    {{-- Pestañas --}}
    <div class="mt-4 flex gap-1 border-b border-slate-200">
        <button type="button" wire:click="setTab('invoices')"
                class="border-b-2 px-4 py-2 text-sm font-medium {{ $tab === 'invoices' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
            Facturas
        </button>
        <button type="button" wire:click="setTab('proofs')"
                class="border-b-2 px-4 py-2 text-sm font-medium {{ $tab === 'proofs' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
            Comprobantes de pago
        </button>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mt-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-600/10">{{ session('error') }}</div>
    @endif

    {{-- ===================== Pestaña: Facturas ===================== --}}
    @if ($tab === 'invoices')
        <div class="mt-5 flex items-center justify-between rounded-xl bg-white px-4 py-3 shadow-sm ring-1 ring-slate-900/5">
            <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-700">
                <input type="checkbox" wire:model.live="onlyPending"
                       class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                Ver solo pendientes de pago
            </label>
            <span class="text-xs text-slate-400">{{ $invoices->total() }} {{ $invoices->total() === 1 ? 'factura' : 'facturas' }}</span>
        </div>

        <div class="mt-4 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Folio</th>
                            <th class="px-5 py-3 text-right">Total</th>
                            <th class="px-5 py-3">Emisión</th>
                            <th class="px-5 py-3">Vence</th>
                            <th class="px-5 py-3">Estado</th>
                            <th class="px-5 py-3 text-right">Descargar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($invoices as $invoice)
                            <tr wire:key="cinv-{{ $invoice->id }}" class="transition hover:bg-slate-50/70">
                                <td class="px-5 py-3.5 font-medium text-slate-900">{{ $invoice->folio ?: '—' }}</td>
                                <td class="px-5 py-3.5 text-right font-medium text-slate-800">{{ $invoice->total()->format() }}</td>
                                <td class="px-5 py-3.5 text-slate-600">{{ $invoice->issued_at?->translatedFormat('d M Y') ?: '—' }}</td>
                                <td class="px-5 py-3.5 text-slate-600">{{ $invoice->due_at?->translatedFormat('d M Y') ?: '—' }}</td>
                                <td class="px-5 py-3.5">
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $invoice->status?->badgeClasses() }}">
                                        {{ $invoice->status?->label() }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center justify-end gap-2">
                                        @php
                                            $payable = in_array($invoice->status, [
                                                \App\Domain\Billing\Enums\InvoiceStatus::Pending,
                                                \App\Domain\Billing\Enums\InvoiceStatus::Overdue,
                                            ], true);
                                        @endphp
                                        @if ($paymentsAutomatic && $payable)
                                            <button type="button" wire:click="payOnline({{ $invoice->id }})"
                                                    class="inline-flex items-center gap-1 rounded-md bg-green-600 px-2.5 py-1 text-xs font-medium text-white transition hover:bg-green-700">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3M3.75 5.25h16.5A1.5 1.5 0 0 1 21.75 6.75v10.5a1.5 1.5 0 0 1-1.5 1.5H3.75a1.5 1.5 0 0 1-1.5-1.5V6.75a1.5 1.5 0 0 1 1.5-1.5Z"/></svg>
                                                Pagar en línea
                                            </button>
                                        @endif
                                        <a href="{{ route('client.invoices.download', $invoice) }}"
                                           class="inline-flex items-center gap-1 rounded-md bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 transition hover:bg-indigo-100">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                                            PDF
                                        </a>
                                        @if ($invoice->hasXml())
                                            <a href="{{ route('client.invoices.download.xml', $invoice) }}"
                                               class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 transition hover:bg-slate-200">
                                                XML
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12">
                                    <div class="flex flex-col items-center text-center">
                                        <svg class="h-10 w-10 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                                        <p class="mt-3 text-sm text-slate-500">Aún no tienes facturas.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">{{ $invoices->links() }}</div>
    @endif

    {{-- ===================== Pestaña: Comprobantes ===================== --}}
    @if ($tab === 'proofs')
        <div class="mt-5 flex items-center justify-between">
            <p class="text-sm text-slate-500">Envía tu comprobante de pago; lo revisaremos y te emitiremos tu factura.</p>
            @if (auth()->user()->canClient('client.proofs.upload'))
                <button type="button" wire:click="$toggle('showProofForm')"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    Enviar comprobante
                </button>
            @endif
        </div>

        @if ($showProofForm)
            <form wire:submit="submitProof" class="mt-4 space-y-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
                <h2 class="font-semibold text-slate-900">Nuevo comprobante de pago</h2>
                <div class="grid gap-4 sm:grid-cols-2">
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
                        <input type="date" wire:model="proofPaidAt"
                               class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('proofPaidAt') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Referencia (opcional)</label>
                        <input type="text" wire:model="proofReference" placeholder="No. de operación, folio, etc."
                               class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('proofReference') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700">Factura relacionada (opcional)</label>
                        <select wire:model="proofInvoiceId" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Sin factura</option>
                            @foreach ($clientInvoices as $inv)
                                <option value="{{ $inv->id }}">{{ $inv->folio ?: ('#'.$inv->id) }} — {{ $inv->total()->format() }}</option>
                            @endforeach
                        </select>
                        @error('proofInvoiceId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="flex items-center gap-3 border-t border-slate-100 pt-4">
                    <button type="submit" wire:loading.attr="disabled" wire:target="submitProof,proofFile"
                            class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-50">
                        Enviar comprobante
                    </button>
                    <button type="button" wire:click="$set('showProofForm', false)" class="text-sm font-medium text-slate-500 hover:text-slate-700">Cancelar</button>
                </div>
            </form>
        @endif

        <div class="mt-4 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Fecha de envío</th>
                            <th class="px-5 py-3 text-right">Monto</th>
                            <th class="px-5 py-3">Método</th>
                            <th class="px-5 py-3">Factura</th>
                            <th class="px-5 py-3">Estado</th>
                            <th class="px-5 py-3 text-right">Archivo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($proofs as $proof)
                            <tr wire:key="proof-{{ $proof->id }}" class="transition hover:bg-slate-50/70">
                                <td class="px-5 py-3.5 text-slate-600">{{ $proof->created_at?->translatedFormat('d M Y') }}</td>
                                <td class="px-5 py-3.5 text-right font-medium text-slate-800">{{ $proof->amount()->format() }}</td>
                                <td class="px-5 py-3.5 text-slate-600">{{ $proof->method?->label() ?: '—' }}</td>
                                <td class="px-5 py-3.5 text-slate-600">{{ $proof->invoice?->folio ?: '—' }}</td>
                                <td class="px-5 py-3.5">
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $proof->status?->badgeClasses() }}">
                                        {{ $proof->status?->label() }}
                                    </span>
                                    @if ($proof->status === \App\Domain\Billing\Enums\PaymentProofStatus::Rejected && $proof->review_note)
                                        <span class="mt-0.5 block text-xs text-red-500">{{ $proof->review_note }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <a href="{{ route('client.payment-proofs.download', $proof) }}"
                                       class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 transition hover:bg-slate-200">
                                        Ver
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12">
                                    <div class="flex flex-col items-center text-center">
                                        <svg class="h-10 w-10 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                        <p class="mt-3 text-sm text-slate-500">Aún no has enviado comprobantes.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">{{ $proofs->links() }}</div>
    @endif
</div>
