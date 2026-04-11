        # Accounting Plugin for CakePHP

        [![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE)
        [![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%208.3-8892BF.svg)](https://php.net/)
        [![Status](https://img.shields.io/badge/status-0.x%20unstable-orange.svg?style=flat-square)](#status)

        > **Status: 0.x unstable.** API may break before 1.0. Pin to `^0.1` in production and read the CHANGELOG before upgrading minor versions. Cut to `1.0` once the API has stabilized across two or more real consumers.

        A focused CakePHP 5.x plugin bundling two German accounting workflow primitives that virtually every DACH invoicing application needs:

1. **Mahnwesen** — German dunning per §286 BGB (30-day default rule), §288 BGB Verzugszinsen (base rate + 9 pp for B2B / + 5 pp for B2C), §288 Abs. 5 Verzugspauschale (€40 flat for B2B), with a 3-stage escalation framework and a cron job that pulls the current Bundesbank base rate semi-annually.

2. **DATEV Export** — DATEV "Rechnungswesen" CSV format with configurable SKR03 / SKR04 account chart mapping, for clean handoff to Steuerberater who use DATEV (which is roughly 80% of the German tax-advisor market).

Both concerns live under sub-namespaces (`Accounting\\Mahnwesen`, `Accounting\\Datev`) so the merge doesn't blur the internal boundaries. Template rendering for dunning emails is behind an interface, so the polished German-language copy lives in the consuming app rather than in the plugin.

        ## Features

        - **Mahnwesen**: `DunningLevelCalculator` (§286 BGB 30-day default), `InterestCalculator` (§288 BGB Verzugszinsen), `VerzugspauschaleCalculator` (§288 Abs. 5 flat €40 B2B), `BaseRateFetcher` cron job, `DunningCycleRunner` queue-driven daily job.
- **DATEV**: `DatevCsvBuilder` emitting DATEV-certified CSV (column headers, encoding, line termination are all picky), `SkrMapper` for SKR03 and SKR04 account charts, `DatevExportProfile` for per-app wiring, full audit-logged export runs.
- Template rendering interface — polished dunning copy stays in the consuming app.
- DATEV-Marktplatz metadata generator for listing submission.
- Both concerns are co-versioned under one plugin.

        ## Structure

This plugin is internally organized into focused sub-areas under the main namespace:

### `Accounting\Mahnwesen`

- `Mahnwesen/Calculator/DunningLevelCalculator`
- `Mahnwesen/Calculator/InterestCalculator`
- `Mahnwesen/Calculator/VerzugspauschaleCalculator`
- `Mahnwesen/Service/BaseRateFetcher`
- `Mahnwesen/Service/DunningCycleRunner`

### `Accounting\Datev`

- `Datev/Export/DatevCsvBuilder`
- `Datev/Export/SkrMapper`
- `Datev/Export/DatevExportProfile`
- `Datev/Service/DatevExportService`


        ## Installation

        Install via [composer](https://getcomposer.org):

        ```bash
        composer require dereuromark/cakephp-accounting
        bin/cake plugin load Accounting
        ```

        ## Usage

        > This is a 0.x skeleton. Usage examples will appear here as the API stabilizes. See the `docs/` folder for architecture notes and the `tests/` folder for working examples.

        ## Motivation

        This plugin is part of a three-plugin family extracted from real DACH vertical-SaaS products (landlord billing, freelancer invoicing, Vereinsverwaltung) where German legal and tax requirements shape the architecture:

        - **`dereuromark/cakephp-compliance`** — GoBD retention, multi-tenant scoping, gap-free numbering, dual-approval workflows. Every-request compliance plumbing.
        - **`dereuromark/cakephp-accounting`** — §286 / §288 BGB dunning calculators and DATEV CSV export. German accounting workflow.
        - **`dereuromark/cakephp-sepa`** — IBAN / BIC / Creditor ID validation and CAMT.053 / CAMT.054 parsing with German bank-quirk normalization. SEPA banking primitives.

        Each plugin bundles tightly-cohesive sub-concerns under sub-namespaces so installation is one `composer require` per domain area rather than a scattershot of micro-packages.

        ## Contributing

        PRs welcome. Please include tests, run PHPStan (`composer stan`) and PHPCS (`composer cs-check`) before submitting, and sign off commits per the DCO.

        ## License

        MIT. See [LICENSE](LICENSE).
