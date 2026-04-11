<?php

declare(strict_types=1);

namespace Accounting\Mahnwesen\Calculator;

use Cake\I18n\DateTime;

/**
 * Compute the current dunning level for an overdue invoice per §286 BGB.
 *
 * Default schedule (configurable per instance):
 *
 *   days past due (inclusive) | level
 *   ----------------------------+-----------------
 *     0 | Reminder
 *    14 | FirstDunning
 *    28 | SecondDunning
 *
 * If no explicit due date is supplied, the §286 Abs. 3 BGB 30-day rule
 * applies: the debtor is in default 30 days after receipt of the invoice.
 * The calculator uses the invoice's issue date as a proxy for receipt —
 * consumers who track a separate `received_at` should pass that in as the
 * `dueDate` argument (= `issued + 30 days`) instead of relying on this proxy.
 */
class DunningLevelCalculator
{
    /**
     * @param int $reminderAfterDays First day (inclusive) on which the reminder level is active
     * @param int $firstDunningAfterDays First day (inclusive) on which the first dunning level is active
     * @param int $secondDunningAfterDays First day (inclusive) on which the second dunning level is active
     * @param int $defaultDueAfterIssueDays Grace period applied when no explicit due date is given
     */
    public function __construct(
        protected int $reminderAfterDays = 1,
        protected int $firstDunningAfterDays = 15,
        protected int $secondDunningAfterDays = 29,
        protected int $defaultDueAfterIssueDays = 30,
    ) {
    }

    public function levelFor(DateTime $issued, ?DateTime $dueDate, DateTime $now): DunningLevel
    {
        $days = $this->daysOverdue($dueDate, $now, $issued);

        if ($days >= $this->secondDunningAfterDays) {
            return DunningLevel::SecondDunning;
        }
        if ($days >= $this->firstDunningAfterDays) {
            return DunningLevel::FirstDunning;
        }
        if ($days >= $this->reminderAfterDays) {
            return DunningLevel::Reminder;
        }

        return DunningLevel::None;
    }

    public function daysOverdue(?DateTime $dueDate, DateTime $now, ?DateTime $issued = null): int
    {
        $effectiveDue = $dueDate ?? $this->defaultDueFromIssue($issued);

        return (int)floor(($now->getTimestamp() - $effectiveDue->getTimestamp()) / 86400);
    }

    protected function defaultDueFromIssue(?DateTime $issued): DateTime
    {
        if ($issued === null) {
            return new DateTime();
        }

        return $issued->addDays($this->defaultDueAfterIssueDays);
    }
}
