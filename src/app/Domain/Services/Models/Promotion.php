<?php

namespace App\Domain\Services\Models;

use App\Domain\Services\Enums\DiscountType;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;

/**
 * Promoción / cupón de descuento para productos. Por ahora es solo un catálogo
 * administrativo: no se aplica automáticamente a facturas ni servicios.
 */
class Promotion extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'discount_type',
        'discount_value',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_type' => DiscountType::class,
            'discount_value' => 'integer',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Descuento legible: "10%" o el monto formateado si es fijo.
     */
    public function discountLabel(): string
    {
        if ($this->discount_type === DiscountType::Percent) {
            return $this->discount_value.'%';
        }

        return (new Money((int) $this->discount_value))->format();
    }

    /**
     * ¿La promoción está vigente hoy? (activa y dentro del rango de fechas si
     * las tiene). Si no hay fechas, solo importa is_active.
     */
    public function isValidNow(?\DateTimeInterface $now = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = $now ? \Illuminate\Support\Carbon::instance($now) : \Illuminate\Support\Carbon::now();

        if ($this->starts_at && $now->lt($this->starts_at->startOfDay())) {
            return false;
        }

        if ($this->ends_at && $now->gt($this->ends_at->endOfDay())) {
            return false;
        }

        return true;
    }

    /**
     * Calcula el descuento (en centavos) que aplica esta promoción a un
     * subtotal dado. Nunca excede el subtotal.
     */
    public function discountCentsFor(int $subtotalCents): int
    {
        if ($subtotalCents <= 0) {
            return 0;
        }

        $discount = $this->discount_type === DiscountType::Percent
            ? (int) round($subtotalCents * $this->discount_value / 100)
            : (int) $this->discount_value;

        return min(max(0, $discount), $subtotalCents);
    }
}
