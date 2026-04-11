<?php

declare(strict_types=1);

namespace Accounting\Datev\Export;

use Cake\I18n\Date;

/**
 * Immutable value object representing one DATEV booking line (Buchungssatz).
 *
 * Maps to the DATEV "Buchungsstapel" CSV format columns 1–14 which are
 * required for a minimum-viable booking row. The remaining 100+ optional
 * columns are emitted empty by the builder.
 *
 * Field shapes:
 *
 * - `amount` string decimal with dot (e.g. `"119.00"`) — builder converts to comma
 * - `creditDebit` either `"S"` (Soll/debit) or `"H"` (Haben/credit)
 * - `account` the primary account (Konto)
 * - `counterAccount` the counter account (Gegenkonto)
 * - `taxKey` DATEV tax key (Buchungsschluessel), e.g. `"9"` for 19% input VAT
 * - `date` the booking date (Belegdatum)
 * - `documentNumber` the invoice/document number (Belegfeld 1)
 * - `description` the booking narrative (Buchungstext)
 */
final class DatevBooking
{
    public function __construct(
        public readonly string $amount,
        public readonly string $creditDebit,
        public readonly string $account,
        public readonly string $counterAccount,
        public readonly string $taxKey,
        public readonly Date $date,
        public readonly string $documentNumber,
        public readonly string $description,
    ) {
    }
}
