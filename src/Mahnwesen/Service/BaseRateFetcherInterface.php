<?php

declare(strict_types=1);

namespace Accounting\Mahnwesen\Service;

use Cake\I18n\DateTime;

/**
 * Contract for implementations that return the Deutsche Bundesbank
 * "Basiszinssatz" (base rate) effective on a given date.
 *
 * The base rate changes semi-annually (January 1 and July 1) based on the
 * ECB main refinancing rate. Any §288 BGB interest calculation must use the
 * rate that was in effect on the day the default occurred (not the day of
 * calculation) — which is why the fetcher takes a date argument.
 */
interface BaseRateFetcherInterface
{
    /**
     * Return the Bundesbank base rate effective on the given date, as a
     * percentage (e.g. `3.62` means 3.62%).
     */
    public function rateAt(DateTime $date): float;
}
