<?php

declare(strict_types=1);

namespace Accounting\Mahnwesen\Calculator;

use Cake\I18n\DateTime;
use InvalidArgumentException;

/**
 * Compute Verzugszinsen per §288 BGB.
 *
 * The annual rate is derived from the current Deutsche Bundesbank base rate
 * (`baseRate`) plus a surcharge that depends on the debtor type:
 *
 * - `Business` (§288 Abs. 2): base rate + 9 percentage points
 * - `Consumer` (§288 Abs. 1): base rate + 5 percentage points
 *
 * The base rate is pulled externally (see `BaseRateFetcher`) because it
 * changes semi-annually and must be stored with historical accuracy for any
 * invoice that is paid months after going into default. For one-shot
 * calculations, the base rate is supplied directly to the constructor.
 *
 * Interest is pro-rated on a 365-day year (standard commercial convention).
 * All monetary amounts are handled as strings to avoid float precision loss;
 * consumers who have `dereuromark/cakephp-decimal` can pass/receive `Decimal`
 * instances instead via the caller-side conversion.
 */
class InterestCalculator
{
    public function __construct(
        protected float $baseRate,
        protected float $businessSurcharge = 9.0,
        protected float $consumerSurcharge = 5.0,
    ) {
    }

    /**
     * Return the annual interest rate for the given debtor type as a decimal
     * string like `'11.5'` (meaning 11.5%).
     */
    public function annualRateFor(DebtorType $debtor): string
    {
        $rate = $this->baseRate + match ($debtor) {
            DebtorType::Business => $this->businessSurcharge,
            DebtorType::Consumer => $this->consumerSurcharge,
        };

        return $this->formatRate($rate);
    }

    /**
     * Compute interest accrued on the given principal between `$dueDate` and
     * `$paidOrNow`. Returns a string with 2 decimals (e.g. `'115.00'`).
     *
     * @param numeric-string|string $principal
     * @param \Accounting\Mahnwesen\Calculator\DebtorType $debtorType
     * @param \Cake\I18n\DateTime $dueDate
     * @param \Cake\I18n\DateTime $paidOrNow
     *
     * @throws \InvalidArgumentException
     */
    public function interest(
        string $principal,
        DebtorType $debtorType,
        DateTime $dueDate,
        DateTime $paidOrNow,
    ): string {
        if (!is_numeric($principal)) {
            throw new InvalidArgumentException(sprintf('Principal must be numeric, got "%s".', $principal));
        }
        $days = (int)floor(($paidOrNow->getTimestamp() - $dueDate->getTimestamp()) / 86400);
        if ($days <= 0) {
            return '0.00';
        }

        $rate = (float)$this->annualRateFor($debtorType);
        /** @var numeric-string $rateStr */
        $rateStr = sprintf('%.8F', $rate / 100);
        /** @var numeric-string $annual */
        $annual = bcmul($principal, $rateStr, 8);
        /** @var numeric-string $prorated */
        $prorated = bcmul($annual, (string)$days, 8);
        /** @var numeric-string $divided */
        $divided = bcdiv($prorated, '365', 8);

        return $this->roundHalfUp($divided, 2);
    }

    protected function formatRate(float $rate): string
    {
        $formatted = rtrim(rtrim(sprintf('%.4f', $rate), '0'), '.');

        return $formatted === '' || $formatted === '-' ? '0' : $formatted;
    }

    /**
     * @param numeric-string|string $value
     * @param int $scale
     *
     * @throws \InvalidArgumentException
     */
    protected function roundHalfUp(string $value, int $scale): string
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException(sprintf('Value must be numeric, got "%s".', $value));
        }
        /** @var numeric-string $factor */
        $factor = bcpow('10', (string)$scale, 0);
        /** @var numeric-string $shifted */
        $shifted = bcmul($value, $factor, 8);
        /** @var numeric-string $halfAdjusted */
        $halfAdjusted = bcadd($shifted, $shifted[0] === '-' ? '-0.5' : '0.5', 0);

        return bcdiv($halfAdjusted, $factor, $scale);
    }
}
