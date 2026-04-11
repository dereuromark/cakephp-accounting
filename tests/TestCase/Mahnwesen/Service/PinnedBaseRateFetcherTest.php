<?php

declare(strict_types=1);

namespace Accounting\Test\TestCase\Mahnwesen\Service;

use Accounting\Mahnwesen\Service\PinnedBaseRateFetcher;
use Cake\I18n\DateTime;
use PHPUnit\Framework\TestCase;

class PinnedBaseRateFetcherTest extends TestCase
{
    public function testReturnsCurrentRateWhenOnlyOnePeriodPinned(): void
    {
        $fetcher = new PinnedBaseRateFetcher([
            '2026-01-01' => 3.62,
        ]);
        $this->assertSame(3.62, $fetcher->rateAt(new DateTime('2026-06-15')));
    }

    public function testReturnsCorrectRateForHistoricalDate(): void
    {
        $fetcher = new PinnedBaseRateFetcher([
            '2022-07-01' => -0.88,
            '2023-01-01' => 1.62,
            '2023-07-01' => 3.12,
            '2024-01-01' => 3.62,
            '2024-07-01' => 3.37,
        ]);
        $this->assertSame(-0.88, $fetcher->rateAt(new DateTime('2022-12-15')));
        $this->assertSame(1.62, $fetcher->rateAt(new DateTime('2023-04-01')));
        $this->assertSame(3.37, $fetcher->rateAt(new DateTime('2024-12-31')));
    }

    public function testReturnsZeroBeforeFirstPinnedDate(): void
    {
        $fetcher = new PinnedBaseRateFetcher([
            '2023-01-01' => 1.62,
        ]);
        $this->assertSame(0.0, $fetcher->rateAt(new DateTime('2022-06-01')));
    }

    public function testReturnsMostRecentRateForFutureDate(): void
    {
        $fetcher = new PinnedBaseRateFetcher([
            '2024-01-01' => 3.62,
        ]);
        $this->assertSame(3.62, $fetcher->rateAt(new DateTime('2099-01-01')));
    }

    public function testBoundaryDateReturnsNewRate(): void
    {
        $fetcher = new PinnedBaseRateFetcher([
            '2023-01-01' => 1.62,
            '2023-07-01' => 3.12,
        ]);
        $this->assertSame(3.12, $fetcher->rateAt(new DateTime('2023-07-01')));
    }

    public function testInsertsMaintainOrdering(): void
    {
        // Constructor takes unsorted input; internal storage should sort
        $fetcher = new PinnedBaseRateFetcher([
            '2024-01-01' => 3.62,
            '2022-07-01' => -0.88,
            '2023-07-01' => 3.12,
            '2023-01-01' => 1.62,
        ]);
        $this->assertSame(-0.88, $fetcher->rateAt(new DateTime('2022-12-01')));
        $this->assertSame(3.62, $fetcher->rateAt(new DateTime('2024-05-01')));
    }
}
