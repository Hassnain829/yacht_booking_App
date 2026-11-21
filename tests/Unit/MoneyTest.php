<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_formats_cents_for_display(): void
    {
        $this->assertSame('$1,250', Money::format(125000));
        $this->assertSame('$1,250.50', Money::format(125050));
        $this->assertSame('$0.99', Money::format(99));
        $this->assertSame('$0', Money::format(0));
        $this->assertSame('-$50', Money::format(-5000));
        $this->assertSame('$1,250.00', Money::format(125000, 'always'));
        $this->assertSame('$1,251', Money::format(125050, 'never'));
        $this->assertSame('$1,650,000', Money::format(165000000));
    }

    public function test_formats_basis_points_as_percent(): void
    {
        $this->assertSame('20%', Money::percent(2000));
        $this->assertSame('12.5%', Money::percent(1250));
        $this->assertSame('12.05%', Money::percent(1205));
        $this->assertSame('9%', Money::percent(900));
        $this->assertSame('0%', Money::percent(0));
    }

    public function test_money_helper_matches_the_class(): void
    {
        require_once __DIR__.'/../../app/Support/helpers.php';
        $this->assertSame(Money::format(45000), money(45000));
    }
}
