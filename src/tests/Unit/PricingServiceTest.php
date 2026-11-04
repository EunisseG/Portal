<?php

namespace Tests\Unit;

use App\Domain\Billing\Services\PricingService;
use PHPUnit\Framework\TestCase;

class PricingServiceTest extends TestCase
{
    private PricingService $pricing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricing = new PricingService();
    }

    public function test_base_plus_addons_with_tax(): void
    {
        // base 1000.00 + addons 200.00 = subtotal 1200.00; IVA 16% = 192.00; total 1392.00
        $b = $this->pricing->compute(baseCents: 100000, addonsCents: 20000, taxRate: 16.0);

        $this->assertSame(120000, $b['subtotal_cents']);
        $this->assertSame(0, $b['discount_cents']);
        $this->assertSame(19200, $b['tax_cents']);
        $this->assertSame(139200, $b['total_cents']);
    }

    public function test_percent_discount_applies_before_tax(): void
    {
        // subtotal 1000.00; -10% = 100.00 descuento; base gravable 900.00; IVA 16% = 144.00; total 1044.00
        $b = $this->pricing->compute(
            baseCents: 100000,
            addonsCents: 0,
            taxRate: 16.0,
            discountType: 'percent',
            discountValue: 10,
        );

        $this->assertSame(100000, $b['subtotal_cents']);
        $this->assertSame(10000, $b['discount_cents']);
        $this->assertSame(14400, $b['tax_cents']);
        $this->assertSame(104400, $b['total_cents']);
    }

    public function test_fixed_discount_in_cents(): void
    {
        // subtotal 500.00; -150.00 fijo; base gravable 350.00; IVA 16% = 56.00; total 406.00
        $b = $this->pricing->compute(
            baseCents: 50000,
            addonsCents: 0,
            taxRate: 16.0,
            discountType: 'fixed',
            discountValue: 15000,
        );

        $this->assertSame(15000, $b['discount_cents']);
        $this->assertSame(35000 + 5600, $b['total_cents']);
    }

    public function test_discount_never_exceeds_subtotal(): void
    {
        $b = $this->pricing->compute(
            baseCents: 10000,
            addonsCents: 0,
            taxRate: 0.0,
            discountType: 'fixed',
            discountValue: 999999,
        );

        $this->assertSame(10000, $b['discount_cents']);
        $this->assertSame(0, $b['total_cents']);
    }
}
