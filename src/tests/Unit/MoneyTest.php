<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_it_creates_from_decimal_and_formats(): void
    {
        $money = Money::fromDecimal(1234.5);

        $this->assertSame(123450, $money->amountInCents);
        $this->assertSame('MXN', $money->currency);
        $this->assertSame(1234.5, $money->toDecimal());
        $this->assertSame('1,234.50 MXN', $money->format());
    }

    public function test_it_adds_and_subtracts_same_currency(): void
    {
        $a = Money::fromDecimal(100);
        $b = Money::fromDecimal(30);

        $this->assertSame(13000, $a->add($b)->amountInCents);
        $this->assertSame(7000, $a->subtract($b)->amountInCents);
    }

    public function test_subtract_does_not_go_negative(): void
    {
        $a = Money::fromDecimal(10);
        $b = Money::fromDecimal(30);

        $this->assertSame(0, $a->subtract($b)->amountInCents);
    }

    public function test_it_rejects_operations_with_different_currencies(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Money(100, 'MXN'))->add(new Money(100, 'USD'));
    }

    public function test_it_rejects_negative_amounts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Money(-1);
    }
}
