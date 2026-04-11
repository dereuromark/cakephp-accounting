<?php

declare(strict_types=1);

namespace Accounting\Mahnwesen\Calculator;

/**
 * Compute the §288 Abs. 5 BGB flat-rate Verzugspauschale.
 *
 * German law grants a creditor a €40 flat fee on top of interest when a
 * business debtor is in default. Consumers (B2C) are explicitly excluded.
 *
 * The flat amount is configurable because the €40 figure has been challenged
 * in court in edge cases (e.g., labour law) and some consumers want to apply
 * a reduced or zero amount for internal policy reasons.
 */
class VerzugspauschaleCalculator
{
    public function __construct(
        protected string $businessPauschale = '40.00',
    ) {
    }

    public function amountFor(DebtorType $debtor): string
    {
        return match ($debtor) {
            DebtorType::Business => $this->businessPauschale,
            DebtorType::Consumer => '0.00',
        };
    }

    public function applies(DebtorType $debtor): bool
    {
        return $debtor === DebtorType::Business && (float)$this->businessPauschale > 0.0;
    }
}
