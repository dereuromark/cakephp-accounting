# Accounting Plugin for CakePHP

[![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%208.3-8892BF.svg)](https://php.net/)
[![CakePHP](https://img.shields.io/badge/cakephp-%3E%3D%205.2-red.svg?style=flat-square)](https://cakephp.org/)
[![Status](https://img.shields.io/badge/status-0.x%20unstable-orange.svg?style=flat-square)](#status)

German accounting helpers for CakePHP: §286 / §288 BGB dunning calculators and DATEV CSV export with SKR03 / SKR04 mapping.

> **Status: 0.x unstable.** API may break before 1.0. Pin to `^0.1` in production and read [CHANGELOG.md](CHANGELOG.md) before upgrading. Cut to 1.0 once the API has stabilized across two or more real consumers.

## What's in the box

Two sub-concerns that German invoicing apps need after an invoice is issued:

| Sub-area | Purpose | Key classes |
|---|---|---|
| **Mahnwesen** | German dunning: §286 BGB default rule, §288 BGB Verzugszinsen, §288 Abs. 5 Verzugspauschale | `DunningLevelCalculator`, `InterestCalculator`, `VerzugspauschaleCalculator`, `DunningCycleRunner`, `PinnedBaseRateFetcher` |
| **Datev** | DATEV-compatible CSV export with SKR03 / SKR04 account chart mapping | `DatevCsvBuilder`, `DatevBooking`, `SkrMapper` |

Each concern lives under its own sub-namespace (`Accounting\Mahnwesen\…`, `Accounting\Datev\…`) so internal boundaries stay clean.

## Why bundled?

Both concerns are workflow helpers that live between "I sent an invoice" and "my Steuerberater gets the data". They use the same `Decimal`-based arithmetic conventions, the same date/money handling, and are typically needed in the same apps. Splitting them would just force two `composer require` calls for no benefit.

## Installation

```bash
composer require dereuromark/cakephp-accounting
bin/cake plugin load Accounting
```

Requires **PHP 8.3+** and **CakePHP 5.2+**.

## Quick start

### Compute the current dunning level for an overdue invoice

```php
use Accounting\Mahnwesen\Calculator\DunningLevelCalculator;
use Cake\I18n\DateTime;

$calculator = new DunningLevelCalculator();
$level = $calculator->levelFor(
    issued: new DateTime('2026-01-01'),
    dueDate: new DateTime('2026-01-31'),
    now: new DateTime('2026-02-20'),
);
// → DunningLevel::Reminder (20 days overdue, before 1. Mahnung threshold)
```

### Compute §288 BGB Verzugszinsen

```php
use Accounting\Mahnwesen\Calculator\DebtorType;
use Accounting\Mahnwesen\Calculator\InterestCalculator;
use Cake\I18n\DateTime;

$calculator = new InterestCalculator(baseRate: 3.62);

$interest = $calculator->interest(
    principal: '1000.00',
    debtorType: DebtorType::Business,
    dueDate: new DateTime('2026-01-31'),
    paidOrNow: new DateTime('2026-03-02'),
);
// → '10.40' (1000 × (3.62+9)% × 30/365 rounded to 2 decimals)
```

### Compute §288 Abs. 5 Verzugspauschale

```php
use Accounting\Mahnwesen\Calculator\VerzugspauschaleCalculator;

$calculator = new VerzugspauschaleCalculator();
$calculator->amountFor(DebtorType::Business); // '40.00'
$calculator->amountFor(DebtorType::Consumer); // '0.00'
```

### Run a dunning cycle over open invoices

```php
use Accounting\Mahnwesen\Service\{DunningCandidate, DunningCycleRunner};

$runner = new DunningCycleRunner(
    new DunningLevelCalculator(),
    new InterestCalculator(baseRate: 3.62),
    new VerzugspauschaleCalculator(),
);

$candidates = [
    new DunningCandidate(
        id: 'invoice-1',
        principal: '1000.00',
        issuedAt: new DateTime('2026-01-01'),
        dueAt: new DateTime('2026-01-31'),
        debtorType: DebtorType::Business,
    ),
    // ... more candidates ...
];

foreach ($runner->run($candidates, new DateTime()) as $assessment) {
    if ($assessment->level === DunningLevel::None) {
        continue;
    }
    $this->queueMahnungEmail($assessment);
}
```

### Export to DATEV

```php
use Accounting\Datev\Export\{DatevBooking, DatevCsvBuilder};
use Cake\I18n\Date;

$builder = new DatevCsvBuilder(
    consultantNumber: 1001,
    clientNumber: 12345,
    fiscalYearStart: new Date('2026-01-01'),
);

$bookings = [
    new DatevBooking(
        amount: '119.00',
        creditDebit: 'S',
        account: '1400',  // Forderungen
        counterAccount: '8400', // Erlöse 19%
        taxKey: '9',
        date: new Date('2026-03-15'),
        documentNumber: 'RE-2026-0001',
        description: 'Invoice RE-2026-0001',
    ),
];

$csv = $builder->build($bookings);
// Hand to the Steuerberater as ISO-8859-1:
file_put_contents('datev.csv', $builder->toLatin1($csv));
```

### Map domain categories to SKR accounts

```php
use Accounting\Datev\Export\SkrMapper;

$mapper = new SkrMapper('SKR03');
$mapper->accountFor('revenue', 19.0);  // '8400'
$mapper->accountFor('revenue', 7.0);   // '8300'
$mapper->accountFor('expense', 19.0);  // '4980'
```

## Documentation

- [docs/Mahnwesen.md](docs/Mahnwesen.md) — §286 BGB rules, §288 BGB interest, Verzugspauschale, cycle runner, base rate handling
- [docs/Datev.md](docs/Datev.md) — DATEV CSV format spec, SKR03 / SKR04 mapping, booking conventions

## Testing

```bash
composer install
composer test      # PHPUnit — 55 tests, no external dependencies
composer stan      # PHPStan level 8
composer cs-check  # PhpCollective code style
```

## Related plugins

Part of a family of focused DACH-compliance plugins for CakePHP 5.x:

- **`dereuromark/cakephp-compliance`** — GoBD retention, multi-tenant scoping, gap-free numbering, dual-approval.
- **`dereuromark/cakephp-accounting`** — this plugin. German dunning + DATEV CSV export.
- **`dereuromark/cakephp-sepa`** — IBAN / BIC / Creditor ID validation + CAMT parsing.

## Contributing

PRs welcome. Please include tests, run PHPStan (`composer stan`) and PHPCS (`composer cs-check`) before submitting, and sign off commits per the DCO.

## License

MIT. See [LICENSE](LICENSE).
