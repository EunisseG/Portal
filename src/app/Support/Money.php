<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Value Object para representar montos monetarios.
 *
 * El monto se almacena internamente en la unidad menor (centavos) para
 * evitar errores de coma flotante. La moneda por defecto del portal es MXN,
 * pero el VO admite otras monedas si en el futuro se requiere.
 */
final class Money
{
    public const DEFAULT_CURRENCY = 'MXN';

    public function __construct(
        public readonly int $amountInCents,
        public readonly string $currency = self::DEFAULT_CURRENCY,
    ) {
        if ($amountInCents < 0) {
            throw new InvalidArgumentException('El monto no puede ser negativo.');
        }
    }

    public static function fromDecimal(float|string $amount, string $currency = self::DEFAULT_CURRENCY): self
    {
        return new self((int) round(((float) $amount) * 100), $currency);
    }

    public function toDecimal(): float
    {
        return $this->amountInCents / 100;
    }

    public function format(): string
    {
        return number_format($this->toDecimal(), 2).' '.$this->currency;
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amountInCents + $other->amountInCents, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self(max(0, $this->amountInCents - $other->amountInCents), $this->currency);
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('No se pueden operar montos de monedas distintas.');
        }
    }
}
