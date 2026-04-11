<?php

declare(strict_types=1);

namespace Accounting\Mahnwesen\Service;

use Accounting\Mahnwesen\Calculator\DebtorType;
use Cake\I18n\DateTime;

/**
 * Immutable value object describing one invoice the DunningCycleRunner
 * should evaluate.
 *
 * Consumers map their own invoice entities to `DunningCandidate` in their
 * application layer — the plugin intentionally does not couple to any
 * specific ORM shape.
 */
final class DunningCandidate
{
    /**
     * @param string $id
     * @param numeric-string|string $principal
     * @param \Accounting\Mahnwesen\Calculator\DebtorType $debtorType
     * @param \Cake\I18n\DateTime|null $dueAt
     * @param \Cake\I18n\DateTime $issuedAt
     */
    public function __construct(
        public readonly string $id,
        public readonly string $principal,
        public readonly DateTime $issuedAt,
        public readonly ?DateTime $dueAt,
        public readonly DebtorType $debtorType,
    ) {
    }
}
