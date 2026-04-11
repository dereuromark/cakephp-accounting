<?php

declare(strict_types=1);

namespace Accounting\Mahnwesen\Calculator;

/**
 * Debtor category used to choose the §288 BGB surcharge:
 *
 * - `Business` (§288 Abs. 2 BGB): base rate + 9 percentage points
 * - `Consumer` (§288 Abs. 1 BGB): base rate + 5 percentage points
 */
enum DebtorType: string
{
    case Business = 'business';
    case Consumer = 'consumer';
}
