# DATEV — CSV Export for German Tax Advisors

**DATEV** is the dominant accounting software ecosystem in Germany: roughly 80% of Steuerberater use it for their client bookkeeping. Any SaaS that wants to be "Steuerberater-compatible" has to emit DATEV-readable CSV.

The `Accounting\Datev` sub-area builds a v7.0 "Buchungsstapel" CSV from `DatevBooking` value objects, with a minimal SKR03 / SKR04 mapping seed that applications can extend.

---

## The DATEV Buchungsstapel format

DATEV Rechnungswesen (the bookkeeping software) imports flat CSV files with a very specific shape:

```
Line 1: Metadata header (27 fields, pipe-separated-ish with quoted strings)
Line 2: Column headers (14 fields for the minimum booking set, 126 for the full format)
Line 3+: One booking per line
```

### Line-level quirks that trip up newcomers

1. **Amounts use comma as decimal separator**. `119.00` must become `119,00`. Periods are interpreted as thousands separators.
2. **Dates are `DDMM`**. No year, no separator. `15 March 2026` → `1503`.
3. **Encoding is ISO-8859-1**. Newer DATEV versions accept UTF-8, but the safe default for legacy imports is Latin-1.
4. **Fields are semicolon-delimited**, with all non-numeric fields enclosed in double quotes.
5. **Line endings are `\r\n`** in DATEV's own exports; this plugin emits `\n` and leaves conversion to the filesystem layer.

All of these are handled inside `DatevCsvBuilder` — callers pass cleanly-typed `DatevBooking` value objects and receive a valid CSV string.

---

## API

### DatevCsvBuilder

```php
use Accounting\Datev\Export\{DatevBooking, DatevCsvBuilder};
use Cake\I18n\Date;

$builder = new DatevCsvBuilder(
    consultantNumber: 1001,   // Berater-Nummer assigned by DATEV
    clientNumber: 12345,      // Mandant-Nummer assigned by the Steuerberater
    fiscalYearStart: new Date('2026-01-01'),
    accountLength: 4,         // 4-digit or 8-digit Sachkonten
);

$bookings = [/* DatevBooking instances */];
$csv = $builder->build($bookings);

// Convert to ISO-8859-1 for DATEV Rechnungswesen legacy imports:
$latin1 = $builder->toLatin1($csv);
file_put_contents('export.csv', $latin1);
```

### DatevBooking value object

```php
new DatevBooking(
    amount: '119.00',          // string, dot decimal — builder converts to comma
    creditDebit: 'S',          // 'S' (Soll/debit) or 'H' (Haben/credit)
    account: '1400',           // Sachkonto (primary account)
    counterAccount: '8400',    // Gegenkonto (counter account)
    taxKey: '9',               // DATEV tax key (Buchungsschluessel)
    date: new Date('2026-03-15'),
    documentNumber: 'RE-2026-0001',
    description: 'Invoice RE-2026-0001',
);
```

### DATEV tax keys (Buchungsschluessel) quick reference

| Key | Meaning |
|---|---|
| `1` | 7% input VAT (reduced rate) |
| `2` | 7% output VAT |
| `8` | 19% input VAT (full rate, reverse) |
| `9` | 19% input VAT (full rate) |

The exact key depends on whether you're the recipient or the issuer and on the nature of the booking. A Steuerberater will tell you which keys apply to your account chart.

---

## SkrMapper — domain category → SKR account

Applications typically think in terms of "revenue", "expense", "VAT received", "trade receivables" — not SKR03 account numbers. `SkrMapper` bridges the gap.

```php
use Accounting\Datev\Export\SkrMapper;

$mapper = new SkrMapper('SKR03');
$mapper->accountFor('revenue', 19.0);           // '8400'
$mapper->accountFor('revenue', 7.0);            // '8300'
$mapper->accountFor('revenue', 0.0);            // '8120'
$mapper->accountFor('expense', 19.0);           // '4980'
$mapper->accountFor('vat_received', 19.0);      // '1776'
$mapper->accountFor('trade_receivables', 0.0);  // '1400'

$mapper04 = new SkrMapper('SKR04');
$mapper04->accountFor('revenue', 19.0);         // '4400'
$mapper04->accountFor('revenue', 7.0);          // '4300'
```

### What's in the seed?

The plugin ships a **minimal** seed covering the most common small-business cases: `revenue`, `expense`, `vat_received`, `trade_receivables` at the 0/7/19% rates. This is explicitly a starting point, **not** a complete account chart.

### Extending with overrides

Applications with more exotic bookings (construction reverse charge, intracommunity supplies, special investment deductions, …) pass per-category overrides to the constructor:

```php
$mapper = new SkrMapper('SKR03', [
    'revenue' => [
        '19' => '8401',   // custom revenue account
    ],
    'reverse_charge_construction' => [
        '0' => '8337',
    ],
    'eu_zero_rated' => [
        '0' => '8125',
    ],
]);
```

Overrides merge into the seed: if the seed defines `revenue.19` and you override it, your value wins; if you add `reverse_charge_construction`, it's added as a new category.

### Unknown categories throw

```php
$mapper->accountFor('not_a_category', 19.0);
// → InvalidArgumentException
```

This is deliberate: silent fallback would put bookings in the wrong account. Fail fast.

---

## Example: exporting invoices for a quarter

```php
use Accounting\Datev\Export\{DatevBooking, DatevCsvBuilder, SkrMapper};
use Cake\I18n\{Date, DateTime};

$builder = new DatevCsvBuilder(
    consultantNumber: (int)env('DATEV_CONSULTANT'),
    clientNumber: (int)env('DATEV_CLIENT'),
    fiscalYearStart: new Date('2026-01-01'),
);
$mapper = new SkrMapper('SKR03');

$bookings = [];
foreach ($this->Invoices->find()->where(['finalized_at >=' => new DateTime('2026-01-01')]) as $invoice) {
    foreach ($invoice->line_items as $line) {
        $bookings[] = new DatevBooking(
            amount: $line->gross_amount,
            creditDebit: 'S',
            account: $mapper->accountFor('trade_receivables', 0.0),
            counterAccount: $mapper->accountFor('revenue', (float)$line->vat_rate),
            taxKey: $this->taxKeyFor((float)$line->vat_rate),
            date: $invoice->issued_at->toDate(),
            documentNumber: $invoice->invoice_number,
            description: $line->description,
        );
    }
}

$csv = $builder->build($bookings);
file_put_contents(
    sprintf('datev-q%d-%d.csv', $quarter, $year),
    $builder->toLatin1($csv),
);
```

---

## What the plugin deliberately does NOT do

- **Double-entry posting logic**: the builder emits whatever bookings the caller hands it. Deciding *which* counter account each line item posts to is the application's job, not the plugin's. The `SkrMapper` is a lookup helper, not a booking engine.
- **DATEV XML format**: DATEV also accepts an XML-based format (DATEV-Format) that is more structured but harder to emit. This plugin supports CSV only. XML support can be added in 0.2 if a consumer needs it.
- **Direct DATEV Unternehmen online upload**: the generated CSV is ready for manual upload or for a separate automation that hands it to the Steuerberater. Direct HTTP upload requires DATEV API credentials and a separate agreement.
- **DATEV-Marktplatz listing metadata**: a future `DatevMarktplatzManifest` generator can produce the JSON required for listing your SaaS as a partner in DATEV Marktplatz. Not in 0.1.

---

## Testing strategy

Tests assert structural correctness of the generated CSV, not a byte-exact match against a reference fixture. This is because the DATEV v7.0 metadata header contains a timestamp (field 6) that changes on every run — asserting equality would make the test time-dependent. Instead, each test verifies a specific invariant:

- First line starts with `"EXTF"` (format marker)
- First line contains the version `700`
- First line contains the consultant and client numbers
- Second line contains the column headers by name
- Booking lines use comma-decimal format (`"119,00"`)
- Booking lines use `DDMM` date format (`"1503"`)
- Multiple bookings produce multiple rows
- Fields are semicolon-delimited
- `'H'` indicator is preserved for credit bookings
- Description containing commas is enclosed in quotes

---

## Test suite

18 passing tests in `tests/TestCase/Datev/`:

### `SkrMapperTest` (8)
- SKR03 maps revenue at 19% / 7% / 0%
- SKR04 maps revenue at 19% / 7%
- Unknown category throws
- Unknown chart throws
- Custom override replaces default

### `DatevCsvBuilderTest` (10)
- Line 1 is EXTF header
- Line 2 is column headers
- Amount format uses comma decimal
- Date format uses `DDMM`
- Multiple bookings produce multiple rows
- Fields are semicolon-delimited
- `'H'` indicator preserved
- Description with commas is quoted
- Fiscal year in header matches config
- Consultant and client numbers in header
