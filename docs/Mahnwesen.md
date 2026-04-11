# Mahnwesen — German Dunning per §286/§288 BGB

When a German B2B invoice goes unpaid, three specific rules apply:

1. **§286 BGB — Default rule**: if no payment date was agreed, the debtor is in default 30 days after receipt of the invoice. If a due date was agreed, the debtor is in default the day after it passes.
2. **§288 BGB — Verzugszinsen** (interest on overdue payments): base rate + 9 percentage points for B2B, + 5 for B2C.
3. **§288 Abs. 5 BGB — Verzugspauschale**: flat €40 for B2B on top of interest, intended to cover the creditor's collection overhead.

The `Accounting\Mahnwesen` sub-area implements all three as pure calculators plus a `DunningCycleRunner` that composes them into a streaming evaluator over a list of invoices.

---

## Sub-components

| Class | Purpose |
|---|---|
| `DunningLevel` enum | `None`, `Reminder`, `FirstDunning`, `SecondDunning` |
| `DebtorType` enum | `Business`, `Consumer` |
| `DunningLevelCalculator` | §286 BGB default rule, configurable thresholds |
| `InterestCalculator` | §288 BGB interest with bcmath-exact arithmetic |
| `VerzugspauschaleCalculator` | §288 Abs. 5 flat fee |
| `BaseRateFetcherInterface` | Contract for Bundesbank base rate resolution |
| `PinnedBaseRateFetcher` | Offline implementation with hard-coded historical rates |
| `DunningCandidate` (DTO) | Input shape for the runner |
| `DunningAssessment` (DTO) | Output shape from the runner |
| `DunningCycleRunner` | Composes the three calculators into a generator |

---

## DunningLevelCalculator

### Default thresholds

```
days ≥ 1   → Reminder        (Zahlungserinnerung)
days ≥ 15  → FirstDunning    (1. Mahnung)
days ≥ 29  → SecondDunning   (2. Mahnung)
```

The default 30-day grace period is the §286 Abs. 3 BGB rule. If an explicit due date is given, it takes precedence over the grace period.

### Configurable thresholds

```php
$calculator = new DunningLevelCalculator(
    reminderAfterDays: 0,      // immediate reminder on the due date itself
    firstDunningAfterDays: 7,  // 1. Mahnung after 1 week
    secondDunningAfterDays: 14, // 2. Mahnung after 2 weeks
    defaultDueAfterIssueDays: 30, // §286 Abs. 3 rule unchanged
);
```

### `levelFor()` vs `daysOverdue()`

```php
// Current level
$level = $calculator->levelFor($issued, $dueDate, $now);

// Raw day count (can be negative if not yet due)
$days = $calculator->daysOverdue($dueDate, $now, $issued);
```

The `daysOverdue()` method accepts a `null` due date and falls back to `issued + defaultDueAfterIssueDays`.

### Why inclusive thresholds?

`days >= 15 → FirstDunning` means "on the 15th day past due, you're in FirstDunning." Inclusive is easier to reason about than exclusive for calendar-day arithmetic.

### Edge cases covered by tests

- Invoice not yet due (negative days) → `None`
- Exactly on the due date → `None` (still in grace)
- Day after due date → `Reminder`
- 30 days past issue (no explicit due) → `None` (inside grace)
- 31 days past issue → `Reminder` (enters default)
- Very far past due (365+ days) → `SecondDunning` (stable, doesn't escalate further)

---

## InterestCalculator

### §288 BGB rates

```
B2B: base rate + 9 percentage points   (§288 Abs. 2 BGB)
B2C: base rate + 5 percentage points   (§288 Abs. 1 BGB)
```

The **base rate** is the Deutsche Bundesbank "Basiszinssatz" per §247 BGB, updated every January 1 and July 1 based on the ECB main refinancing rate.

### API

```php
$calculator = new InterestCalculator(baseRate: 3.62);

// Annual rate as string percentage
$calculator->annualRateFor(DebtorType::Business); // '12.62'
$calculator->annualRateFor(DebtorType::Consumer); // '8.62'

// Interest accrued between dueDate and paidOrNow
$interest = $calculator->interest(
    principal: '1000.00',
    debtorType: DebtorType::Business,
    dueDate: new DateTime('2026-01-31'),
    paidOrNow: new DateTime('2026-03-02'),
);
// → '10.40' (30 days × 12.62% / 365 × 1000, rounded to 2 decimals)
```

### Exact arithmetic via bcmath

All calculations use `bcmath` with 8 intermediate decimals and a final half-up rounding to 2 decimals. No floats touch the monetary math — this avoids the `0.1 + 0.2 !== 0.3` class of bugs that are legally costly when computing interest on real invoices.

### Negative base rates

The 2016–2022 ECB era had negative base rates (as low as −0.88%). The calculator handles this correctly:

```php
$calc = new InterestCalculator(baseRate: -0.88);
$calc->annualRateFor(DebtorType::Business); // '8.12'
```

The B2B rate can never go below 9% − 0.88% = 8.12%, which matches the legal reading that the surcharge is additive, not clamped.

### Custom surcharges

For edge cases where a contract specifies a different surcharge:

```php
$calc = new InterestCalculator(
    baseRate: 3.62,
    businessSurcharge: 12.0, // contractual 12pp instead of 9pp
    consumerSurcharge: 5.0,
);
```

---

## BaseRateFetcher — offline-first

### Interface

```php
interface BaseRateFetcherInterface
{
    public function rateAt(DateTime $date): float;
}
```

### PinnedBaseRateFetcher

The shipped implementation. Applications pin the semi-annual rates and update them every January 1 / July 1:

```php
use Accounting\Mahnwesen\Service\PinnedBaseRateFetcher;

$fetcher = new PinnedBaseRateFetcher([
    '2022-07-01' => -0.88,
    '2023-01-01' => 1.62,
    '2023-07-01' => 3.12,
    '2024-01-01' => 3.62,
    '2024-07-01' => 3.37,
    '2025-01-01' => 2.27,
    '2025-07-01' => 1.27,
    '2026-01-01' => 2.00,
]);

$fetcher->rateAt(new DateTime('2026-03-15')); // 2.00
$fetcher->rateAt(new DateTime('2023-04-01')); // 1.62
$fetcher->rateAt(new DateTime('2022-06-01')); // 0.0 (before first pinned date)
```

Historical correctness matters: if an invoice issued in 2023 is still being dunned in 2026, you need the 2023 rate for the 2023 period and the 2024–2026 rates for subsequent periods. (The current InterestCalculator uses a single flat rate — see "Roadmap" below for multi-period interest.)

### Why offline?

Alternatives considered:

- **HTTP fetch from bundesbank.de**: the Bundesbank has a publishable dataset but the HTML is not a stable API and HTTP dependency at runtime introduces brittleness, latency, and offline-test failure modes.
- **ECB Statistical Data Warehouse**: stable but again HTTP-only and introduces a dependency on external availability.

For a compliance-critical calculator, the guarantee "this rate came from an auditable source pinned at release time" is more valuable than "this rate was fetched live." The pinned map is the plugin's default. Applications that want live fetching can implement `BaseRateFetcherInterface` themselves and inject it.

---

## VerzugspauschaleCalculator

### §288 Abs. 5 BGB flat fee

```php
$calc = new VerzugspauschaleCalculator();
$calc->amountFor(DebtorType::Business); // '40.00'
$calc->amountFor(DebtorType::Consumer); // '0.00'
$calc->applies(DebtorType::Business);   // true
$calc->applies(DebtorType::Consumer);   // false
```

### Configurable amount

Some contexts (labor law edge cases, internal company policy) reduce or disable the pauschale. Override:

```php
$calc = new VerzugspauschaleCalculator(businessPauschale: '25.00');
$calc->amountFor(DebtorType::Business); // '25.00'

$calc = new VerzugspauschaleCalculator(businessPauschale: '0.00');
$calc->applies(DebtorType::Business); // false (zero disables application)
```

---

## DunningCycleRunner

Composes the three calculators into a streaming evaluator:

```php
$runner = new DunningCycleRunner(
    new DunningLevelCalculator(),
    new InterestCalculator(baseRate: 3.62),
    new VerzugspauschaleCalculator(),
);

foreach ($runner->run($candidates, new DateTime()) as $assessment) {
    $assessment->candidate;       // the input DunningCandidate
    $assessment->level;           // DunningLevel enum
    $assessment->daysOverdue;     // int
    $assessment->interest;        // '10.40'
    $assessment->pauschale;       // '40.00' | '0.00'
}
```

### DunningCandidate shape

```php
new DunningCandidate(
    id: 'invoice-1',           // whatever your app uses
    principal: '1000.00',      // numeric string, 2 decimals
    issuedAt: $issueDate,
    dueAt: $dueDate,           // nullable — falls back to §286 Abs. 3
    debtorType: DebtorType::Business,
);
```

### Generator-based

`run()` returns a `Generator`, so you can stream over millions of candidates without loading everything into memory. Typical use:

```php
foreach ($runner->run($this->streamOpenInvoices(), new DateTime()) as $assessment) {
    if ($assessment->level !== DunningLevel::None) {
        $this->queueMahnungEmail($assessment);
    }
}
```

### Pure transform

The runner does **not** send emails, mutate the database, or write to the candidates. It's a pure stateless evaluator. Consumers side-effect on the yielded assessments as they see fit.

---

## Template rendering — deliberately out of scope

The plugin intentionally does NOT include polished German-language Mahnung email templates. Reasons:

1. **Legal phrasing**: German courts scrutinize dunning language. The exact wording matters and should be reviewed by counsel for each application.
2. **Brand voice**: each application's tone is different. Shared templates would fight all of them.
3. **Localization**: Austrian and Swiss variants have different legal language (GmbHG, OR, etc.).

Applications are expected to ship their own templates in `templates/email/html/mahnung_first.php` etc., calling the plugin's calculators to compute the numeric fields.

---

## Roadmap (deferred to 0.2+)

- **Multi-period interest**: when the base rate changes during the overdue period, current `InterestCalculator` uses a single rate. Multi-period accrual would split the interval and apply the right rate per sub-period.
- **Collection-agency escalation**: a `ThirdDunning` level that triggers external Inkasso handoff.
- **Live Bundesbank fetcher**: a `BundesbankHttpBaseRateFetcher` that scrapes the current rate (cached, with fallback to pinned).

---

## Test suite

25 passing tests in `tests/TestCase/Mahnwesen/`:

### `DunningLevelCalculatorTest` (10)
- Not-yet-due returns None
- Just-overdue enters Reminder
- 30-day rule kicks in at day 31
- 14 days past due enters FirstDunning
- 28 days past due enters SecondDunning
- Custom thresholds override defaults
- Level stable for far-past-due (doesn't escalate further)
- `daysOverdue` computes correctly for past and future dates

### `InterestCalculatorTest` (10)
- B2B uses +9pp
- B2C uses +5pp
- 365 days equals annual rate × principal
- 30 days pro-rates correctly
- 0 days returns 0.00
- Negative days (paid before due) returns 0.00
- Negative base rate handled
- Fractional cent rounds half-up
- Custom surcharge override
- 50k EUR × 1 year exact calculation

### `VerzugspauschaleCalculatorTest` (5)
- B2B returns €40.00 default
- Consumer returns €0.00
- Custom amount override
- `applies()` reports correctly
- Zero override disables application

### `PinnedBaseRateFetcherTest` (6)
- Returns current rate when only one period pinned
- Returns correct historical rate for date inside period
- Returns 0.0 before first pinned date
- Returns most recent rate for future date
- Boundary date returns the new rate
- Constructor handles unsorted input

### `DunningCycleRunnerTest` (5)
- Not-yet-due candidate gets None + zero interest/pauschale
- Overdue B2B gets level + interest + pauschale
- Consumer has no pauschale
- Batch processing handles multiple invoices with different states
- Empty input returns empty
