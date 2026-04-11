<?php

declare(strict_types=1);

namespace Accounting\Test\TestCase\Mahnwesen\Calculator;

use Accounting\Mahnwesen\Calculator\DunningLevel;
use Accounting\Mahnwesen\Calculator\DunningLevelCalculator;
use Cake\I18n\DateTime;
use PHPUnit\Framework\TestCase;

class DunningLevelCalculatorTest extends TestCase
{
    protected DunningLevelCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new DunningLevelCalculator();
    }

    public function testInvoiceNotYetDueReturnsNone(): void
    {
        $issued = new DateTime('2026-01-01');
        $dueDate = new DateTime('2026-03-01');
        $now = new DateTime('2026-02-15');
        $level = $this->calculator->levelFor($issued, $dueDate, $now);
        $this->assertSame(DunningLevel::None, $level);
    }

    public function testJustOverdueWithExplicitDueDateEntersFirstReminderLevel(): void
    {
        $issued = new DateTime('2026-01-01');
        $dueDate = new DateTime('2026-01-31');
        $now = new DateTime('2026-02-01');
        $level = $this->calculator->levelFor($issued, $dueDate, $now);
        $this->assertSame(DunningLevel::Reminder, $level);
    }

    public function testDefaultsToThirtyDayRuleWhenNoDueDateGiven(): void
    {
        $issued = new DateTime('2026-01-01');
        $now = new DateTime('2026-01-31');
        $level = $this->calculator->levelFor($issued, null, $now);
        $this->assertSame(DunningLevel::None, $level);

        $now = new DateTime('2026-02-01');
        $level = $this->calculator->levelFor($issued, null, $now);
        $this->assertSame(DunningLevel::Reminder, $level);
    }

    public function testFourteenDaysAfterReminderEntersFirstMahnung(): void
    {
        $issued = new DateTime('2026-01-01');
        $dueDate = new DateTime('2026-01-31');
        $now = new DateTime('2026-02-15');
        $level = $this->calculator->levelFor($issued, $dueDate, $now);
        $this->assertSame(DunningLevel::FirstDunning, $level);
    }

    public function testTwentyEightDaysAfterDueDateEntersSecondMahnung(): void
    {
        $issued = new DateTime('2026-01-01');
        $dueDate = new DateTime('2026-01-31');
        $now = new DateTime('2026-03-01');
        $level = $this->calculator->levelFor($issued, $dueDate, $now);
        $this->assertSame(DunningLevel::SecondDunning, $level);
    }

    public function testCustomThresholdsOverrideDefaults(): void
    {
        $calculator = new DunningLevelCalculator(
            reminderAfterDays: 0,
            firstDunningAfterDays: 7,
            secondDunningAfterDays: 14,
        );
        $issued = new DateTime('2026-01-01');
        $dueDate = new DateTime('2026-01-31');

        $this->assertSame(
            DunningLevel::Reminder,
            $calculator->levelFor($issued, $dueDate, new DateTime('2026-01-31')),
        );
        $this->assertSame(
            DunningLevel::FirstDunning,
            $calculator->levelFor($issued, $dueDate, new DateTime('2026-02-07')),
        );
        $this->assertSame(
            DunningLevel::SecondDunning,
            $calculator->levelFor($issued, $dueDate, new DateTime('2026-02-14')),
        );
    }

    public function testLevelIsStableOverManyDaysOnceReached(): void
    {
        $issued = new DateTime('2026-01-01');
        $dueDate = new DateTime('2026-01-31');
        $now = new DateTime('2027-01-01'); // Very far past due
        $level = $this->calculator->levelFor($issued, $dueDate, $now);
        $this->assertSame(DunningLevel::SecondDunning, $level);
    }

    public function testDaysOverdueIsCalculatedFromDueDate(): void
    {
        $issued = new DateTime('2026-01-01');
        $dueDate = new DateTime('2026-01-31');
        $now = new DateTime('2026-02-10');
        $this->assertSame(10, $this->calculator->daysOverdue($dueDate, $now));
    }

    public function testDaysOverdueIsNegativeForFutureDueDate(): void
    {
        $dueDate = new DateTime('2026-03-01');
        $now = new DateTime('2026-02-15');
        $this->assertLessThan(0, $this->calculator->daysOverdue($dueDate, $now));
    }

    public function testDaysOverdueFromIssueWithDefaultThirtyDayRule(): void
    {
        $issued = new DateTime('2026-01-01');
        $now = new DateTime('2026-02-15');
        $this->assertSame(15, $this->calculator->daysOverdue(null, $now, $issued));
    }
}
