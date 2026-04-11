<?php

declare(strict_types=1);

namespace Accounting\Test\TestCase\Mahnwesen\Service;

use Accounting\Mahnwesen\Calculator\DebtorType;
use Accounting\Mahnwesen\Calculator\DunningLevel;
use Accounting\Mahnwesen\Calculator\DunningLevelCalculator;
use Accounting\Mahnwesen\Calculator\InterestCalculator;
use Accounting\Mahnwesen\Calculator\VerzugspauschaleCalculator;
use Accounting\Mahnwesen\Service\DunningCandidate;
use Accounting\Mahnwesen\Service\DunningCycleRunner;
use Cake\I18n\DateTime;
use PHPUnit\Framework\TestCase;

class DunningCycleRunnerTest extends TestCase
{
    protected DunningCycleRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runner = new DunningCycleRunner(
            new DunningLevelCalculator(),
            new InterestCalculator(baseRate: 2.5),
            new VerzugspauschaleCalculator(),
        );
    }

    public function testNotOverdueInvoiceIsSkipped(): void
    {
        $candidate = new DunningCandidate(
            id: 'inv-1',
            principal: '1000.00',
            issuedAt: new DateTime('2026-01-01'),
            dueAt: new DateTime('2026-03-01'),
            debtorType: DebtorType::Business,
        );

        $results = iterator_to_array(
            $this->runner->run([$candidate], new DateTime('2026-02-15')),
        );

        $this->assertCount(1, $results);
        $this->assertSame(DunningLevel::None, $results[0]->level);
        $this->assertSame('0.00', $results[0]->interest);
        $this->assertSame('0.00', $results[0]->pauschale);
    }

    public function testOverdueB2BInvoiceCarriesInterestAndPauschale(): void
    {
        $candidate = new DunningCandidate(
            id: 'inv-1',
            principal: '1000.00',
            issuedAt: new DateTime('2026-01-01'),
            dueAt: new DateTime('2026-01-31'),
            debtorType: DebtorType::Business,
        );

        $results = iterator_to_array(
            $this->runner->run([$candidate], new DateTime('2026-03-02')),
        );

        /** @var \Accounting\Mahnwesen\Service\DunningAssessment $assessment */
        $assessment = $results[0];
        $this->assertSame(DunningLevel::SecondDunning, $assessment->level);
        $this->assertSame('40.00', $assessment->pauschale);
        // Roughly 30 days of 11.5% annual interest on 1000 → ~9.45
        $this->assertSame('9.45', $assessment->interest);
    }

    public function testConsumerInvoiceHasNoPauschale(): void
    {
        $candidate = new DunningCandidate(
            id: 'inv-1',
            principal: '1000.00',
            issuedAt: new DateTime('2026-01-01'),
            dueAt: new DateTime('2026-01-31'),
            debtorType: DebtorType::Consumer,
        );

        $results = iterator_to_array(
            $this->runner->run([$candidate], new DateTime('2026-02-15')),
        );

        $this->assertSame('0.00', $results[0]->pauschale);
    }

    public function testBatchProcessingHandlesMultipleInvoices(): void
    {
        $candidates = [
            new DunningCandidate('inv-1', '100.00', new DateTime('2026-01-01'), new DateTime('2026-01-31'), DebtorType::Business),
            new DunningCandidate('inv-2', '200.00', new DateTime('2026-01-01'), new DateTime('2026-03-01'), DebtorType::Business),
            new DunningCandidate('inv-3', '300.00', new DateTime('2026-01-01'), new DateTime('2026-01-15'), DebtorType::Consumer),
        ];

        $results = iterator_to_array(
            $this->runner->run($candidates, new DateTime('2026-02-15')),
        );

        $this->assertCount(3, $results);
        $this->assertSame('inv-1', $results[0]->candidate->id);
        $this->assertSame(DunningLevel::FirstDunning, $results[0]->level);
        $this->assertSame('inv-2', $results[1]->candidate->id);
        $this->assertSame(DunningLevel::None, $results[1]->level);
        $this->assertSame('inv-3', $results[2]->candidate->id);
        $this->assertSame(DunningLevel::SecondDunning, $results[2]->level);
    }

    public function testReturnsEmptyForEmptyInput(): void
    {
        $results = iterator_to_array($this->runner->run([], new DateTime()));
        $this->assertSame([], $results);
    }
}
