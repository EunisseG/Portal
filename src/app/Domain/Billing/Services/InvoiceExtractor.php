<?php

namespace App\Domain\Billing\Services;

use Smalot\PdfParser\Parser as PdfParser;
use Throwable;

/**
 * Extrae datos sugeridos (folio, UUID, total, fecha) de un XML CFDI o un PDF.
 *
 * - XML CFDI: extracción exacta desde los atributos estructurados del comprobante.
 * - PDF: best-effort mediante patrones sobre el texto. Puede no encontrar nada.
 *
 * No realiza llamadas externas. Nunca lanza: ante cualquier fallo devuelve
 * datos vacíos para que el flujo de subida continúe y el admin capture a mano.
 */
class InvoiceExtractor
{
    public function fromXml(string $xmlContents): ExtractedInvoiceData
    {
        try {
            $xml = @simplexml_load_string($xmlContents);
            if ($xml === false) {
                return new ExtractedInvoiceData(source: 'none');
            }

            $attrs = $xml->attributes();
            $folio = isset($attrs['Folio']) ? (string) $attrs['Folio'] : null;
            $total = isset($attrs['Total']) ? (float) $attrs['Total'] : null;
            $fecha = isset($attrs['Fecha']) ? substr((string) $attrs['Fecha'], 0, 10) : null;

            // UUID del Timbre Fiscal Digital (TFD) en el complemento.
            $uuid = $this->extractUuid($xml);

            return new ExtractedInvoiceData(
                folio: $folio ?: $uuid,
                uuid: $uuid,
                total: $total,
                issuedAt: $fecha,
                source: 'xml',
            );
        } catch (Throwable) {
            return new ExtractedInvoiceData(source: 'none');
        }
    }

    public function fromPdf(string $pdfPath): ExtractedInvoiceData
    {
        try {
            $parser = new PdfParser;
            $text = $parser->parseFile($pdfPath)->getText();

            $folio = $this->matchFirst($text, [
                '/folio\s*fiscal\s*[:\-]?\s*([A-F0-9\-]{36})/i',
                '/(?:folio|no\.?\s*de\s*factura|factura\s*no\.?)\s*[:\-]?\s*([A-Z0-9\-]{3,})/i',
            ]);

            $uuid = $this->matchFirst($text, [
                '/([A-F0-9]{8}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{12})/i',
            ]);

            $totalRaw = $this->matchFirst($text, [
                '/total\s*a\s*pagar\s*[:\-]?\s*\$?\s*([\d,]+\.?\d{0,2})/i',
                '/total\s*[:\-]?\s*\$?\s*([\d,]+\.?\d{0,2})/i',
                '/importe\s*[:\-]?\s*\$?\s*([\d,]+\.?\d{0,2})/i',
            ]);
            $total = $totalRaw !== null
                ? (float) str_replace(',', '', $totalRaw)
                : null;

            return new ExtractedInvoiceData(
                folio: $folio ?: $uuid,
                uuid: $uuid,
                total: $total,
                issuedAt: null,
                source: ($folio || $uuid || $total) ? 'pdf' : 'none',
            );
        } catch (Throwable) {
            return new ExtractedInvoiceData(source: 'none');
        }
    }

    private function extractUuid(\SimpleXMLElement $xml): ?string
    {
        foreach ($xml->getNamespaces(true) as $ns) {
            foreach ($xml->xpath('//*[local-name()="TimbreFiscalDigital"]') ?: [] as $tfd) {
                $a = $tfd->attributes();
                if (isset($a['UUID'])) {
                    return (string) $a['UUID'];
                }
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $patterns
     */
    private function matchFirst(string $text, array $patterns): ?string
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m) && isset($m[1])) {
                return trim($m[1]);
            }
        }

        return null;
    }
}
