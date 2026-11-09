<div class="mx-auto max-w-3xl">
    {{-- Volver + descargar --}}
    <div class="mb-4 flex items-center justify-between">
        <a href="{{ route('client.proofs.index') }}"
           class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 transition hover:text-slate-700">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
            Volver a comprobantes de pago
        </a>
        <a href="{{ route('client.payment-orders.receipt.pdf', $order) }}"
           class="inline-flex items-center gap-1.5 rounded-md bg-slate-100 px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-200">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
            Descargar PDF
        </a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-600/10">{{ session('error') }}</div>
    @endif

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-900/5">
        {{-- ===================== Encabezado ===================== --}}
        <div class="flex flex-col gap-4 border-b border-slate-100 p-6 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-center gap-3">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $brandName }}" class="h-10 w-auto">
                @else
                    <span class="text-xl font-bold text-slate-900">{{ $brandName }}</span>
                @endif
            </div>
            <div class="text-left sm:text-right">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Recibo</p>
                <p class="text-lg font-bold text-slate-900">#{{ $receiptNumber }}</p>
                <p class="mt-1 text-xs text-slate-500">
                    Fecha: {{ $order->created_at?->translatedFormat('d M Y') ?: '—' }}
                </p>
                <p class="mt-2 text-xs font-medium uppercase tracking-wide text-slate-400">Total</p>
                <p class="text-3xl font-extrabold text-slate-900">{{ $total->format() }}</p>
                <span class="mt-1 inline-block rounded-full px-2.5 py-0.5 text-xs font-medium {{ $order->status?->badgeClasses() }}">
                    {{ $order->status?->label() }}
                </span>
            </div>
        </div>

        {{-- ===================== Datos cliente / emisor ===================== --}}
        <div class="grid gap-6 border-b border-slate-100 p-6 sm:grid-cols-2">
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Datos del cliente</h3>
                <dl class="mt-2 space-y-1 text-sm text-slate-700">
                    <div class="font-medium text-slate-900">{{ $clientName }}</div>
                    @if ($order->client?->company && $order->client?->company !== $clientName)
                        <div>{{ $order->client->company }}</div>
                    @endif
                    @if ($order->client?->tax_id)
                        <div><span class="text-slate-400">RFC:</span> {{ $order->client->tax_id }}</div>
                    @endif
                    @if ($clientAddress)
                        <div class="text-slate-500">{{ $clientAddress }}</div>
                    @endif
                    @if ($order->client?->phone)
                        <div><span class="text-slate-400">Tel:</span> {{ $order->client->phone }}</div>
                    @endif
                    @if ($order->client?->user?->email)
                        <div class="text-slate-500">{{ $order->client->user->email }}</div>
                    @endif
                </dl>
            </div>
            <div class="sm:text-right">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Datos del emisor</h3>
                <dl class="mt-2 space-y-1 text-sm text-slate-700">
                    <div class="font-medium text-slate-900">{{ $company->legalName() }}</div>
                    @if ($company->taxId())
                        <div><span class="text-slate-400">RFC:</span> {{ $company->taxId() }}</div>
                    @endif
                    @if ($company->address())
                        <div class="text-slate-500">{{ $company->address() }}</div>
                    @endif
                    @if ($company->phone())
                        <div><span class="text-slate-400">Tel:</span> {{ $company->phone() }}</div>
                    @endif
                    @if ($company->email())
                        <div class="text-slate-500">{{ $company->email() }}</div>
                    @endif
                </dl>
            </div>
        </div>

        {{-- ===================== Método de pago ===================== --}}
        {{-- Solo se muestra con el pago en línea activado (modo automático). --}}
        @if ($paymentsAutomatic)
            <div class="flex flex-col gap-3 border-b border-slate-100 bg-slate-50/60 p-6 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Método de pago</h3>
                    <p class="mt-0.5 text-sm text-slate-500">
                        @if ($payable && $hasCheckout)
                            Paga en línea de forma segura con tarjeta u otros medios.
                        @elseif ($payable)
                            El enlace de pago aún no está disponible. Inténtalo más tarde.
                        @else
                            Esta orden no tiene saldo pendiente por pagar.
                        @endif
                    </p>
                </div>
                @if ($payable && $hasCheckout)
                    <button type="button" wire:click="pay" wire:loading.attr="disabled" wire:target="pay"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-green-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-green-700 disabled:opacity-50">
                        <svg wire:loading.remove wire:target="pay" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3M3.75 5.25h16.5A1.5 1.5 0 0 1 21.75 6.75v10.5a1.5 1.5 0 0 1-1.5 1.5H3.75a1.5 1.5 0 0 1-1.5-1.5V6.75a1.5 1.5 0 0 1 1.5-1.5Z"/></svg>
                        <span wire:loading.remove wire:target="pay">Realizar pago</span>
                        <span wire:loading wire:target="pay">Redirigiendo...</span>
                    </button>
                @endif
            </div>
        @endif

        {{-- ===================== Comprobante de pago ===================== --}}
        @if ($proofs->isNotEmpty() || $canUploadProof)
            <div class="border-b border-slate-100 p-6">
                <h3 class="text-sm font-semibold text-slate-900">Comprobante de pago</h3>

                @if ($proofs->isNotEmpty())
                    <ul class="mt-3 space-y-2 text-sm">
                        @foreach ($proofs as $proof)
                            <li wire:key="proof-{{ $proof->id }}" class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $proof->status?->badgeClasses() }}">{{ $proof->status?->label() }}</span>
                                <span class="text-slate-600">{{ $proof->created_at?->translatedFormat('d M Y') }} · {{ $proof->method?->label() }}</span>
                                <a href="{{ route('client.payment-proofs.download', $proof) }}" class="text-indigo-600 hover:underline">Ver archivo</a>
                                @if ($proof->status === \App\Domain\Billing\Enums\PaymentProofStatus::Rejected && $proof->review_note)
                                    <span class="w-full text-xs text-red-500">{{ $proof->review_note }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($canUploadProof)
                    <form wire:submit="submitProof" class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-slate-700">Archivo (PDF, JPG o PNG)</label>
                            <input type="file" wire:model="proofFile" accept=".pdf,.jpg,.jpeg,.png"
                                   class="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                            <div wire:loading wire:target="proofFile" class="mt-1 text-xs text-indigo-500">Subiendo...</div>
                            @error('proofFile') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
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
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-slate-700">Referencia (opcional)</label>
                            <input type="text" wire:model="proofReference" placeholder="No. de operación, folio, etc."
                                   class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('proofReference') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <button type="submit" wire:loading.attr="disabled" wire:target="submitProof,proofFile"
                                    class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-50">
                                <span wire:loading.remove wire:target="submitProof">Enviar comprobante</span>
                                <span wire:loading wire:target="submitProof">Enviando...</span>
                            </button>
                        </div>
                    </form>
                @elseif ($hasPendingProof)
                    <p class="mt-3 text-xs text-slate-500">Tu comprobante está en revisión. Cuando se apruebe, la orden quedará como pagada.</p>
                @endif
            </div>
        @endif

        {{-- ===================== Detalles de tu compra ===================== --}}
        <div class="p-6">
            <h3 class="text-sm font-semibold text-slate-900">Detalles de tu compra</h3>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="text-left text-xs font-medium uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="py-2 pr-4">Concepto</th>
                            <th class="py-2 px-4 text-center">Cantidad</th>
                            <th class="py-2 pl-4 text-right">Importe</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($items as $item)
                            <tr>
                                <td class="py-2.5 pr-4 text-slate-700">
                                    {{ $item->description }}
                                    @if ($item->isDiscount())
                                        <span class="ml-1 rounded bg-emerald-50 px-1.5 py-0.5 text-xs font-medium text-emerald-700">Descuento</span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-4 text-center text-slate-500">{{ $item->quantity }}</td>
                                <td class="py-2.5 pl-4 text-right text-slate-700">
                                    {{ $item->isDiscount() ? '−' : '' }}{{ $item->amount()->format() }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="py-2.5 pr-4 text-slate-700">{{ $concept }}</td>
                                <td class="py-2.5 px-4 text-center text-slate-500">1</td>
                                <td class="py-2.5 pl-4 text-right text-slate-700">{{ $subtotal->format() }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="border-t-2 border-slate-100 text-sm">
                        <tr>
                            <td></td>
                            <td class="py-2 px-4 text-right text-slate-500">Sub Total</td>
                            <td class="py-2 pl-4 text-right text-slate-700">{{ $subtotal->format() }}</td>
                        </tr>
                        @if ($order->hasDiscount())
                            <tr>
                                <td></td>
                                <td class="py-2 px-4 text-right text-slate-500">Descuento</td>
                                <td class="py-2 pl-4 text-right text-emerald-600">−{{ $discount->format() }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td></td>
                            <td class="py-2 px-4 text-right text-slate-500">{{ $taxRatePercent }}% IVA</td>
                            <td class="py-2 pl-4 text-right text-slate-700">{{ $tax->format() }}</td>
                        </tr>
                        <tr>
                            <td></td>
                            <td class="py-2 px-4 text-right font-semibold text-slate-900">Total</td>
                            <td class="py-2 pl-4 text-right font-bold text-slate-900">{{ $total->format() }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- ===================== Pie ===================== --}}
        <div class="border-t border-slate-100 bg-slate-50/60 px-6 py-4 text-center">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Este recibo no es una factura fiscal</p>
        </div>
    </div>
</div>
