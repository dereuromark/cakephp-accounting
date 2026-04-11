<?php

declare(strict_types=1);

namespace Accounting\Datev\Export;

use Cake\I18n\Date;

/**
 * Build a DATEV v7.0 "Buchungsstapel" CSV file from a list of `DatevBooking`
 * value objects.
 *
 * The generated CSV follows the DATEV standard format used by DATEV
 * Unternehmen online and all Steuerberater who import CSV via DATEV
 * Rechnungswesen:
 *
 *  - Line 1: metadata header (EXTF marker, version, consultant/client numbers,
 *    fiscal year, account length, format-specific flags)
 *  - Line 2: column headers (Umsatz, Soll/Haben-Kennzeichen, Konto, ...)
 *  - Lines 3+: one booking per line, semicolon-delimited, all non-numeric
 *    fields enclosed in double quotes
 *
 * Notes on format quirks that bit everyone who's tried this before:
 *
 *  - Amounts use **comma as decimal separator** (German locale). `119.00` →
 *    `"119,00"`. Periods in the number are treated as thousands separators
 *    and DATEV will reject the row.
 *  - Dates are `DDMM` (day-month, no year, no separator). Yes really.
 *  - Encoding should be **ISO-8859-1** for DATEV Rechnungswesen; newer DATEV
 *    versions accept UTF-8 but the safe default is Latin-1. The builder
 *    returns UTF-8 by default and exposes a `toLatin1()` helper for callers
 *    who need the legacy encoding.
 *  - Line endings are `\r\n` in DATEV's own exports; we emit `\n` and leave
 *    conversion to the filesystem layer if needed.
 */
class DatevCsvBuilder
{
    public function __construct(
        protected int $consultantNumber,
        protected int $clientNumber,
        protected Date $fiscalYearStart,
        protected int $accountLength = 4,
    ) {
    }

    /**
     * @param list<\Accounting\Datev\Export\DatevBooking> $bookings
     */
    public function build(array $bookings): string
    {
        $lines = [];
        $lines[] = $this->renderMetadataHeader();
        $lines[] = $this->renderColumnHeader();
        foreach ($bookings as $booking) {
            $lines[] = $this->renderBookingRow($booking);
        }

        return implode("\n", $lines) . "\n";
    }

    public function toLatin1(string $utf8): string
    {
        return mb_convert_encoding($utf8, 'ISO-8859-1', 'UTF-8');
    }

    protected function renderMetadataHeader(): string
    {
        $fields = [
            '"EXTF"',
            '700',
            '21',
            '"Buchungsstapel"',
            '12',
            '"' . date('Ymd') . date('His') . '000"',
            '',
            '"RE"',
            '""',
            '""',
            (string)$this->consultantNumber,
            (string)$this->clientNumber,
            '"' . $this->fiscalYearStart->format('Ymd') . '"',
            (string)$this->accountLength,
            '"' . $this->fiscalYearStart->format('Ymd') . '"',
            '"' . $this->fiscalYearStart->addYears(1)->subDays(1)->format('Ymd') . '"',
            '"' . $this->fiscalYearStart->format('Y') . '"',
            '""',
            '1',
            '0',
            '0',
            '"EUR"',
            '',
            '',
            '',
            '',
            '',
        ];

        return implode(';', $fields);
    }

    protected function renderColumnHeader(): string
    {
        $columns = [
            'Umsatz (ohne Soll/Haben-Kz)',
            'Soll/Haben-Kennzeichen',
            'WKZ Umsatz',
            'Kurs',
            'Basis-Umsatz',
            'WKZ Basis-Umsatz',
            'Konto',
            'Gegenkonto',
            'BU-Schluessel',
            'Belegdatum',
            'Belegfeld 1',
            'Belegfeld 2',
            'Skonto',
            'Buchungstext',
        ];

        return implode(';', array_map(
            static fn (string $c): string => '"' . $c . '"',
            $columns,
        ));
    }

    protected function renderBookingRow(DatevBooking $booking): string
    {
        $fields = [
            '"' . $this->formatAmount($booking->amount) . '"',
            '"' . $booking->creditDebit . '"',
            '"EUR"',
            '',
            '',
            '',
            $booking->account,
            $booking->counterAccount,
            $booking->taxKey,
            '"' . $booking->date->format('dm') . '"',
            '"' . $this->escape($booking->documentNumber) . '"',
            '',
            '',
            '"' . $this->escape($booking->description) . '"',
        ];

        return implode(';', $fields);
    }

    protected function formatAmount(string $amount): string
    {
        return str_replace('.', ',', $amount);
    }

    protected function escape(string $value): string
    {
        return str_replace('"', '""', $value);
    }
}
