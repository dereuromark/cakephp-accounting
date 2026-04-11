<?php

declare(strict_types=1);

namespace Accounting\Test\TestCase\Mahnwesen\Calculator;

use Accounting\Mahnwesen\Calculator\DebtorType;
use Accounting\Mahnwesen\Calculator\VerzugspauschaleCalculator;
use PHPUnit\Framework\TestCase;

class VerzugspauschaleCalculatorTest extends TestCase
{
    public function testB2BReceivesFortyEuroFlat(): void
    {
        $calculator = new VerzugspauschaleCalculator();
        $this->assertSame('40.00', $calculator->amountFor(DebtorType::Business));
    }

    public function testConsumerReceivesNoPauschale(): void
    {
        $calculator = new VerzugspauschaleCalculator();
        $this->assertSame('0.00', $calculator->amountFor(DebtorType::Consumer));
    }

    public function testCustomAmountOverridesDefault(): void
    {
        $calculator = new VerzugspauschaleCalculator(businessPauschale: '25.00');
        $this->assertSame('25.00', $calculator->amountFor(DebtorType::Business));
    }

    public function testAppliesReportsTrueOnlyForBusiness(): void
    {
        $calculator = new VerzugspauschaleCalculator();
        $this->assertTrue($calculator->applies(DebtorType::Business));
        $this->assertFalse($calculator->applies(DebtorType::Consumer));
    }

    public function testZeroPauschaleOverrideDisablesApplication(): void
    {
        $calculator = new VerzugspauschaleCalculator(businessPauschale: '0.00');
        $this->assertFalse($calculator->applies(DebtorType::Business));
    }
}
