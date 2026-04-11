<?php

declare(strict_types=1);

namespace Accounting\Mahnwesen\Service;

use Accounting\Mahnwesen\Calculator\DunningLevelCalculator;
use Accounting\Mahnwesen\Calculator\InterestCalculator;
use Accounting\Mahnwesen\Calculator\VerzugspauschaleCalculator;
use Cake\I18n\DateTime;
use Generator;

/**
 * Drives the dunning cycle: for each candidate invoice, compute the current
 * dunning level, accrued §288 BGB interest, and §288 Abs. 5 pauschale, and
 * yield a `DunningAssessment` value object.
 *
 * The runner is a pure transform: it reads candidates, computes results,
 * and yields. It does NOT send emails, write to the database, or mutate the
 * candidates. Consumers wire it into their own queue job / background worker
 * and side-effect on the yielded assessments.
 *
 * Typical usage:
 *
 * ```php
 * $runner = new DunningCycleRunner(
 *     new DunningLevelCalculator(),
 *     new InterestCalculator(baseRate: 3.62),
 *     new VerzugspauschaleCalculator(),
 * );
 *
 * foreach ($runner->run($candidates, new DateTime()) as $assessment) {
 *     if ($assessment->level === DunningLevel::None) {
 *         continue;
 *     }
 *     $this->enqueueReminderEmail($assessment);
 * }
 * ```
 */
class DunningCycleRunner
{
    public function __construct(
        protected DunningLevelCalculator $levelCalculator,
        protected InterestCalculator $interestCalculator,
        protected VerzugspauschaleCalculator $pauschaleCalculator,
    ) {
    }

    /**
     * @param iterable<\Accounting\Mahnwesen\Service\DunningCandidate> $candidates
     * @param \Cake\I18n\DateTime $now
     *
     * @return \Generator<int, \Accounting\Mahnwesen\Service\DunningAssessment>
     */
    public function run(iterable $candidates, DateTime $now): Generator
    {
        foreach ($candidates as $candidate) {
            yield $this->assess($candidate, $now);
        }
    }

    protected function assess(DunningCandidate $candidate, DateTime $now): DunningAssessment
    {
        $level = $this->levelCalculator->levelFor($candidate->issuedAt, $candidate->dueAt, $now);
        $days = $this->levelCalculator->daysOverdue($candidate->dueAt, $now, $candidate->issuedAt);

        if ($days <= 0) {
            return new DunningAssessment($candidate, $level, $days, '0.00', '0.00');
        }

        $interest = $this->interestCalculator->interest(
            $candidate->principal,
            $candidate->debtorType,
            $candidate->dueAt ?? $candidate->issuedAt,
            $now,
        );

        $pauschale = $this->pauschaleCalculator->amountFor($candidate->debtorType);

        return new DunningAssessment($candidate, $level, $days, $interest, $pauschale);
    }
}
