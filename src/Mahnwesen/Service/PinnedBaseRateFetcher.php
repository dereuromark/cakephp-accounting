<?php

declare(strict_types=1);

namespace Accounting\Mahnwesen\Service;

use Cake\I18n\DateTime;

/**
 * Base rate fetcher backed by an in-memory map of pinned values.
 *
 * This is the offline-first implementation: consumers who don't want to
 * talk to the Bundesbank over HTTP can pin the semi-annual rates by hand
 * and update the map on each January 1 / July 1. Suitable for tests, CI,
 * batch jobs, and any deployment where network-free operation is desired.
 *
 * For the full historical series back to 2002, see
 * <https://www.bundesbank.de/de/statistiken/geld-und-kapitalmaerkte/zinssaetze-und-renditen/basiszinssatz-nach-247-bgb-607820>.
 */
class PinnedBaseRateFetcher implements BaseRateFetcherInterface
{
    /**
     * @var array<string, float> ISO-date-sorted map of effective dates to rates
     */
    protected array $rates;

    /**
     * @param array<string, float> $rates Map of `YYYY-MM-DD` → rate in percent
     */
    public function __construct(array $rates)
    {
        ksort($rates);
        $this->rates = $rates;
    }

    public function rateAt(DateTime $date): float
    {
        $needle = $date->format('Y-m-d');
        $current = 0.0;
        foreach ($this->rates as $effective => $rate) {
            if ($effective <= $needle) {
                $current = $rate;

                continue;
            }

            break;
        }

        return $current;
    }
}
