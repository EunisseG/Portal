<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recibo {{ $receiptNumber }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { margin: 0; color: #334155; font-size: 12px; }
        .wrap { padding: 28px 32px; }
        h1, h2, h3 { margin: 0; }
        .muted { color: #94a3b8; }
        .label { color: #94a3b8; font-size: 9px; text-transform: uppercase; letter-spacing: .05em; }
        table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; }
        .brand { font-size: 20px; font-weight: bold; color: #0f172a; }
        .logo { height: 44px; }
        .receipt-no { font-size: 16px; font-weight: bold; color: #0f172a; }
        .total-big { font-size: 26px; font-weight: bold; color: #0f172a; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 10px; font-weight: 600; }
        .section { margin-top: 20px; }
        .section-title { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #94a3b8; margin-bottom: 6px; }
        .party td { vertical-align: top; width: 50%; }
        .party .name { font-weight: 700; color: #0f172a; }
        .line { margin: 1px 0; }
        .detail th { text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: .05em; color: #94a3b8; padding: 6px 8px; border-bottom: 1px solid #e2e8f0; }
        .detail td { padding: 7px 8px; border-bottom: 1px solid #f1f5f9; }
        .detail .num { text-align: right; }
        .detail .center { text-align: center; }
        .totals td { padding: 4px 8px; }
        .totals .k { text-align: right; color: #64748b; }
        .totals .v { text-align: right; }
        .totals .strong { font-weight: 700; color: #0f172a; }
        .rule { border-top: 2px solid #e2e8f0; }
        .foot { margin-top: 28px; padding-top: 12px; border-top: 1px solid #e2e8f0; text-align: center; }
        .foot .note { font-size: 9px; text-transform: uppercase; letter-spacing: .05em; color: #94a3b8; }
    </style>
</head>
<body>
<div class="wrap">
    {{-- ===================== Encabezado ===================== --}}
    <table class="header">
        <tr>
            <td style="width: 55%;">
                @if (! empty($logoSrc))
                    <img class="logo" src="{{ $logoSrc }}" alt="{{ $brandName }}">
                @else
                    <span class="brand">{{ $brandName }}</span>
                @endif
            </td>
            <td style="width: 45%; text-align: right;">
                <div class="label">Recibo</div>
                <div class="receipt-no">#{{ $receiptNumber }}</div>
                <div class="muted" style="margin-top: 3px;">
                    Fecha: {{ $order->created_at?->translatedFormat('d M Y') ?: '—' }}
                </div>
                <div class="label" style="margin-top: 8px;">Total</div>
                <div class="total-big">{{ $total->format() }}</div>
                <div style="margin-top: 4px;">
                    <span class="badge" style="background: #f1f5f9; color: #475569;">{{ $order->status?->label() }}</span>
                </div>
            </td>
        </tr>
    </table>

    {{-- ===================== Datos cliente / emisor ===================== --}}
    <div class="section">
        <table class="party">
            <tr>
                <td>
                    <div class="section-title">Datos del cliente</div>
                    <div class="line name">{{ $clientName }}</div>
                    @if ($order->client?->company && $order->client?->company !== $clientName)
                        <div class="line">{{ $order->client->company }}</div>
                    @endif
                    @if ($order->client?->tax_id)
                        <div class="line"><span class="muted">RFC:</span> {{ $order->client->tax_id }}</div>
                    @endif
                    @if ($clientAddress)
                        <div class="line muted">{{ $clientAddress }}</div>
                    @endif
                    @if ($order->client?->phone)
                        <div class="line"><span class="muted">Tel:</span> {{ $order->client->phone }}</div>
                    @endif
                    @if ($order->client?->user?->email)
                        <div class="line muted">{{ $order->client->user->email }}</div>
                    @endif
                </td>
                <td style="text-align: right;">
                    <div class="section-title">Datos del emisor</div>
                    <div class="line name">{{ $company->legalName() }}</div>
                    @if ($company->taxId())
                        <div class="line"><span class="muted">RFC:</span> {{ $company->taxId() }}</div>
                    @endif
                    @if ($company->address())
                        <div class="line muted">{{ $company->address() }}</div>
                    @endif
                    @if ($company->phone())
                        <div class="line"><span class="muted">Tel:</span> {{ $company->phone() }}</div>
                    @endif
                    @if ($company->email())
                        <div class="line muted">{{ $company->email() }}</div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- ===================== Detalles de tu compra ===================== --}}
    <div class="section">
        <div class="section-title" style="color: #0f172a; font-size: 12px;">Detalles de tu compra</div>
        <table class="detail">
            <thead>
                <tr>
                    <th>Concepto</th>
                    <th class="center">Cantidad</th>
                    <th class="num">Importe</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td>{{ $item->description }}@if ($item->isDiscount()) <span class="muted">(Descuento)</span>@endif</td>
                        <td class="center">{{ $item->quantity }}</td>
                        <td class="num">{{ $item->isDiscount() ? '-' : '' }}{{ $item->amount()->format() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td>{{ $concept }}</td>
                        <td class="center">1</td>
                        <td class="num">{{ $subtotal->format() }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <table class="totals" style="margin-top: 6px;">
            <tr class="rule"><td style="width: 60%;"></td><td class="k">Sub Total</td><td class="v">{{ $subtotal->format() }}</td></tr>
            @if ($order->hasDiscount())
                <tr><td></td><td class="k">Descuento</td><td class="v">-{{ $discount->format() }}</td></tr>
            @endif
            <tr><td></td><td class="k">{{ $taxLabel ?? $taxRatePercent.'% IVA' }}</td><td class="v">{{ $tax->format() }}</td></tr>
            <tr><td></td><td class="k strong">Total</td><td class="v strong">{{ $total->format() }}</td></tr>
        </table>
    </div>

    {{-- ===================== Pie ===================== --}}
    <div class="foot">
        <div class="note">Este recibo no es una factura fiscal</div>
    </div>
</div>
</body>
</html>
