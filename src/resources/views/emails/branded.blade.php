<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0"
                   style="width:100%;max-width:600px;background-color:#ffffff;border-radius:12px;overflow:hidden;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
                {{-- Cabecera con la marca --}}
                <tr>
                    <td style="background-color:{{ $accent }};padding:20px 28px;">
                        @if ($logoUrl)
                            <img src="{{ $logoUrl }}" alt="{{ $brandName }}" height="36"
                                 style="display:block;height:36px;width:auto;border:0;">
                        @else
                            <span style="font-size:20px;font-weight:700;color:{{ $accentText }};">{{ $brandName }}</span>
                        @endif
                    </td>
                </tr>

                {{-- Contenido --}}
                <tr>
                    <td style="padding:28px 28px 8px 28px;color:#334155;font-size:15px;line-height:1.6;">
                        <h1 style="margin:0 0 16px 0;font-size:18px;line-height:1.3;color:#0f172a;">{{ $subject }}</h1>
                        @foreach ($paragraphs as $paragraph)
                            <p style="margin:0 0 14px 0;">{!! $paragraph !!}</p>
                        @endforeach
                    </td>
                </tr>

                {{-- Botón de acción --}}
                @if ($cta)
                    <tr>
                        <td style="padding:6px 28px 24px 28px;">
                            <a href="{{ $cta['url'] }}"
                               style="display:inline-block;background-color:{{ $accent }};color:{{ $accentText }};text-decoration:none;font-weight:600;font-size:15px;padding:12px 24px;border-radius:8px;">
                                {{ $cta['label'] }}
                            </a>
                        </td>
                    </tr>
                @endif

                {{-- Pie --}}
                <tr>
                    <td style="border-top:1px solid #e2e8f0;padding:18px 28px;color:#94a3b8;font-size:12px;line-height:1.5;">
                        <p style="margin:0 0 4px 0;">{{ $footer ?? 'Este mensaje fue enviado automáticamente. Por favor no respondas a este correo.' }}</p>
                        <p style="margin:0;">
                            {{ $companyName }}@if ($companyAddress) · {{ $companyAddress }}@endif
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
