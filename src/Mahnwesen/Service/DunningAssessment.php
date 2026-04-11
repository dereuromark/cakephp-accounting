<?php

declare(strict_types=1);

namespace Accounting\Mahnwesen\Service;

use Accounting\Mahnwesen\Calculator\DunningLevel;

/**
 * Result of evaluating one candidate: which dunning level applies, how much
 * interest has accrued, and whether the Verzugspauschale applies.
 */
final class DunningAssessment
{
    public function __construct(
        public readonly DunningCandidate $candidate,
        public readonly DunningLevel $level,
        public readonly int $daysOverdue,
        public readonly string $interest,
        public readonly string $pauschale,
    ) {
    }
}
