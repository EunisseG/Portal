<?php

namespace App\Domain\Billing\Services;

/**
 * Datos sugeridos extraídos de un archivo de factura (XML CFDI o PDF).
 * Todos son opcionales: el admin revisa y completa lo que falte.
 */
final class ExtractedInvoiceData
{
    public function __construct(
        public readonly ?string $folio = null,
        public readonly ?string $uuid = null,
        public readonly ?float $total = null,      // en pesos (decimal)
        public readonly ?string $issuedAt = null,  // Y-m-d
        public readonly string $source = 'none',   // 'xml' | 'pdf' | 'none'
    ) {}
}
