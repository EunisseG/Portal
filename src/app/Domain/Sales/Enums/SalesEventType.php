<?php

namespace App\Domain\Sales\Enums;

/**
 * Tipo de evento comercial registrado para medir el desempeño de los
 * ejecutivos de ventas.
 */
enum SalesEventType: string
{
    case NewSale = 'new_sale';   // alta de un servicio/dominio/complemento
    case Renewal = 'renewal';    // renovación (avance de ciclo) de uno existente

    public function label(): string
    {
        return match ($this) {
            self::NewSale => 'Venta nueva',
            self::Renewal => 'Renovación',
        };
    }
}
