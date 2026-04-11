<?php

declare(strict_types=1);

namespace Accounting\Test\TestCase\Mahnwesen\Calculator;

use Accounting\Mahnwesen\Calculator\DebtorType;
use Accounting\Mahnwesen\Calculator\InterestCalculator;
use Cake\I18n\DateTime;
use PHPUnit\Framework\TestCase;

class InterestCalculatorTest extends TestCase
{
    public function testB2BInterestUsesBaseRatePlusNinePoints(): void
    {
        // Base rate 2.5% → B2B rate 11.5%
        $calculator = new InterestCalculator(baseRate: 2.5);
        $this->assertSame('11.5', $calculator->annualRateFor(DebtorType::Business));
    }

    public function testB2CInterestUsesBaseRatePlusFivePoints(): void
    {
        $calculator = new InterestCalculator(baseRate: 2.5);
        $this->assertSame('7.5', $calculator->annualRateFor(DebtorType::Consumer));
    }

    public function testInterestFor365DaysEqualsAnnualRateTimesAmount(): void
    {
        // 1000 EUR, 11.5% annual B2B, 365 days → 115.00 EUR
        $calculator = new InterestCalculator(baseRate: 2.5);
        $interest = $calculator->interest(
            principal: '1000.00',
            debtorType: DebtorType::Business,
            dueDate: new DateTime('2026-01-01'),
            paidOrNow: new DateTime('2027-01-01'),
        );
        $this->assertSame('115.00', $interest);
    }

    public function testInterestFor30DaysProRatesCorrectly(): void
    {
        // 1000 EUR, 11.5% annual, 30 days → 1000 * 0.115 * 30/365 = 9.45 EUR
        $calculator = new InterestCalculator(baseRate: 2.5);
        $interest = $calculator->interest(
            principal: '1000.00',
            debtorType: DebtorType::Business,
            dueDate: new DateTime('2026-01-01'),
            paidOrNow: new DateTime('2026-01-31'),
        );
        $this->assertSame('9.45', $interest);
    }

    public function testInterestFor0DaysIsZero(): void
    {
        $calculator = new InterestCalculator(baseRate: 2.5);
        $interest = $calculator->interest(
            principal: '1000.00',
            debtorType: DebtorType::Business,
            dueDate: new DateTime('2026-01-01'),
            paidOrNow: new DateTime('2026-01-01'),
        );
        $this->assertSame('0.00', $interest);
    }

    public function testInterestForNegativeDaysIsZero(): void
    {
        // Paid before due date
        $calculator = new InterestCalculator(baseRate: 2.5);
        $interest = $calculator->interest(
            principal: '1000.00',
            debtorType: DebtorType::Business,
            dueDate: new DateTime('2026-01-31'),
            paidOrNow: new DateTime('2026-01-01'),
        );
        $this->assertSame('0.00', $interest);
    }

    public function testNegativeBaseRateIsHandled(): void
    {
        // 2016-2022 ECB negative regime: base rate was −0.88% for years
        // B2B rate = -0.88 + 9 = 8.12%
        $calculator = new InterestCalculator(baseRate: -0.88);
        $this->assertSame('8.12', $calculator->annualRateFor(DebtorType::Business));
    }

    public function testB2BInterestOn50kEurosForOneFullYear(): void
    {
        // 50000 EUR, baseRate 2.5 → rate 11.5%, 365 days → 5750.00
        $calculator = new InterestCalculator(baseRate: 2.5);
        $interest = $calculator->interest(
            principal: '50000.00',
            debtorType: DebtorType::Business,
            dueDate: new DateTime('2026-01-01'),
            paidOrNow: new DateTime('2027-01-01'),
        );
        $this->assertSame('5750.00', $interest);
    }

    public function testFractionalCentsRoundHalfUp(): void
    {
        // Pick an amount that produces a 0.5 cent boundary:
        // 333.34 EUR * 11.5% * 7/365 = 0.7355... → rounds to 0.74
        $calculator = new InterestCalculator(baseRate: 2.5);
        $interest = $calculator->interest(
            principal: '333.34',
            debtorType: DebtorType::Business,
            dueDate: new DateTime('2026-01-01'),
            paidOrNow: new DateTime('2026-01-08'),
        );
        $this->assertSame('0.74', $interest);
    }

    public function testCustomSurchargeOverridesDefault(): void
    {
        // Override: B2B surcharge of 8pp instead of 9pp
        $calculator = new InterestCalculator(
            baseRate: 2.5,
            businessSurcharge: 8.0,
            consumerSurcharge: 5.0,
        );
        $this->assertSame('10.5', $calculator->annualRateFor(DebtorType::Business));
    }
}
