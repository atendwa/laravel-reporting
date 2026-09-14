# Laravel Reporting

A queue-based reporting system for Laravel with Filament UI integration.

## What it does

- Define report **generators** (auto-discovered classes) that query your data and export it as CSV and PDF
- Schedule automated runs via cron expressions
- Apply dynamic filters/parameters at runtime through a generated Filament form
- Track every execution with full history (status, duration, file paths, modifiers used)
- Prevent concurrent execution of heavy reports via cooldown checking
- Notify users on completion via Filament's notification system

## Installation

```bash
composer require atendwa/laravel-reporting
php artisan migrate
```

Publish the config if you need to change defaults:

```bash
php artisan vendor:publish --tag=reporting-config
```

Pick a PDF engine and require it (only one is needed):

```bash
composer require barryvdh/laravel-dompdf   # config('reporting.pdf_engine') = 'dompdf' (default)
composer require mpdf/mpdf                 # config('reporting.pdf_engine') = 'mpdf'
```

## Key concepts

**Generators** live in `App\Reports\Generators` (configurable via `reporting.generator_namespaces` /
`reporting.generator_paths`) and implement `GeneratorInterface`. The methods you'll actually write are
`generate()`, `getHeaders()`, `getModifiers()`, and `getDefaultParameters()`. Everything else (UI, queuing,
file export, cooldown checking) is handled by the package.

**Modifiers** are filters your generator declares — the package turns them into Filament form fields
automatically. Supported types: `date_range`, `select`, `multiselect`, `text`, `number`.

**Heavy reports** (`isHeavy(): true`) get a cooldown window (`reporting.default_cooldown_minutes`) so a
cached recent result is served instead of re-running the report. Set `estimateExecutionTime()` for
context in the UI.

**Schedules** are reusable cron configurations that can be attached to multiple reports via a relation
manager in Filament.

**Tenant-aware generators** — the `Reporting\Generator\Concerns\IsTenantAware` trait is opt-in and scopes
data/options to a team/tenant model. Set `reporting.team_model` to your tenant model's FQCN before using
it; the model must expose an `is_default` boolean column, and your user model must expose a `teams()`
relation.

## Getting started

```bash
php artisan reporting:make:generator                 # scaffolds app/Reports/Generators/YourReportGenerator.php
php artisan reporting:make:report-from-generator     # creates a Report model from a discovered generator
```

## Artisan commands

| Command                                | Purpose                                                    |
|-----------------------------------------|-------------------------------------------------------------|
| `reporting:run {report}`                | Manually trigger a report                                   |
| `reporting:make:generator`              | Scaffold a new report generator class                       |
| `reporting:make:report-from-generator`  | Create a Report model from a discovered generator            |
| `reporting:prune --days=90`             | Delete old history rows/files                                |
| `reporting:cache:clear`                 | Refresh the generator discovery cache                        |
| `reporting:plugin:reset`                | Truncate all reporting tables                                |
| `reporting:plugin:test`                 | Run this package's own test suite with a coverage score      |
| `reporting:setup`                       | Generate Shield permissions, seed schedules, register generators |

## Configuration (`config/reporting.php`)

Key options: `generator_namespaces`, `generator_paths`, `disk`, `directory`, `default_cooldown_minutes`,
`pdf_engine` (`dompdf`|`mpdf`), `chunk_size`, `pdf_chunk_size`, `logo_path`, `queue`, `cleanup_days`,
`team_model`.

---

The Filament resources (Reports, Histories, Schedules) are available out of the box under a `Reporting`
cluster — no additional configuration needed beyond registering the plugin on your panel.

## Known portability boundary

Some Filament resources in this package still use small helper traits from this project's in-house
`BetaFilament` plugin (`ResourceAccessGate`, `UsesFilamentPolicySetup`, `SupportResourceNavigationGroup`,
and an overridden `ListRecords` page). Inside the `corporate_and_legal_erp` monorepo these resolve
automatically. If you install this package in a project that doesn't have `BetaFilament`, you'll need to
either provide equivalent implementations under those same class names or extract `BetaFilament` as its
own package too. This is the one remaining hard dependency on the original monorepo.

## Tests

Test files live under `tests/` but were written against the host app's `Tests\TestCase` and are not yet
wired up to run standalone (no Orchestra Testbench harness yet). Treat them as a starting point, not a
passing suite, until that's set up.
