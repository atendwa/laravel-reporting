# atendwa/laravel-reporting

[![PHP](https://img.shields.io/packagist/dependency-v/atendwa/laravel-reporting/php?label=php&color=777BB4)](composer.json)
[![Laravel](https://img.shields.io/packagist/dependency-v/atendwa/laravel-reporting/illuminate%2Fcontracts?label=laravel&color=FF2D20)](composer.json)
[![Filament](https://img.shields.io/packagist/dependency-v/atendwa/laravel-reporting/filament%2Ffilament?label=filament&color=FDAE4B)](composer.json)
[![Release](https://img.shields.io/packagist/v/atendwa/laravel-reporting?label=release&color=007EC6)](https://packagist.org/packages/atendwa/laravel-reporting)
[![CI](https://github.com/atendwa/laravel-reporting/actions/workflows/ci.yml/badge.svg?branch=develop)](https://github.com/atendwa/laravel-reporting/actions/workflows/ci.yml)

Queue-based reporting for Laravel with a Filament interface: define generators, schedule them, export CSV and PDF, and
keep the history of every run.

- Define report **generators** (auto-discovered classes) that query your data and export it as CSV and PDF
- Schedule automated runs via cron expressions
- Apply dynamic filters/parameters at runtime through a generated Filament form
- Track every execution with full history (status, duration, file paths, modifiers used)
- Prevent concurrent execution of heavy reports via cooldown checking
- Notify users on completion via Filament's notification system

## Requirements

PHP 8.3 or later, Laravel 11, 12 or 13 and Filament 5. A PDF engine: `barryvdh/laravel-dompdf` (default) or `mpdf/mpdf`.

## Installation

```bash
composer require atendwa/laravel-reporting
composer require mpdf/mpdf          # or barryvdh/laravel-dompdf, then set REPORTING_PDF_ENGINE=dompdf
php artisan migrate
php artisan reporting:install       # publishes the config and asks where the resources go in the navigation
php artisan reporting:setup         # Shield permissions, default schedules, registers the discovered generators
```

Register the plugin on each panel that should show the reports:

```php
use Reporting\Filament\Plugins\ReportingPlugin;

$panel->plugins([
    ReportingPlugin::make(),
]);
```

## Configuration

Set in `config/reporting.php`.

Key options: `generator_namespaces`, `generator_paths`, `disk`, `directory`, `default_cooldown_minutes`,
`pdf_engine` (`dompdf`|`mpdf`), `chunk_size`, `pdf_chunk_size`, `logo_path`, `queue`, `cleanup_days`,
`team_model`.

### Navigation (`navigation.cluster` / `navigation.group`)

The Reports, Histories, and Schedules resources are available out of the box under this package's own
`Reporting\Filament\Clusters\Reporting` cluster: no configuration needed to get started. To place them
somewhere else in your panel instead, set either key (applied consistently across all three resources):

```php
'navigation' => [
    // Nest under one of your own clusters instead of the package's built-in one.
    'cluster' => App\Filament\Clusters\Operations::class,

    // Or, if you don't use a cluster, group them under a plain navigation label.
    'group' => 'Operations',
],
```

`reporting:install` walks you through setting these interactively; `cluster` wins over `group` when both
are set, since a cluster manages its own top-level navigation entry.

## Usage

1. Scaffold a generator:

   ```bash
   php artisan reporting:make:generator
   ```

## Configuration

The settings are in `config/reporting.php`.

| Key | Environment | Default | What it does |
|---|---|---|---|
| `generator_namespaces` | | `App\Reports\Generators` | namespaces searched for generators |
| `generator_paths` | | `app/Reports/Generators` | folders searched for generators |
| `disk` | `REPORTING_DISK` | `local` | disk the report files are written to |
| `directory` | | `reports` | folder on that disk |
| `default_cooldown_minutes` | | `30` | how long a heavy report's last result is served instead of a new run |
| `pdf_engine` | `REPORTING_PDF_ENGINE` | `mpdf` | `mpdf` (written incrementally) or `dompdf` (chunks merged with FPDI) |
| `chunk_size` | | `1000` | rows per chunk when writing CSV |
| `pdf_chunk_size` | | `500` | rows per chunk when writing PDF; smaller uses less memory |
| `logo_path` | `REPORTING_LOGO_PATH` | none | absolute path to the logo in PDF headers |
| `pdf_font.name`, `pdf_font.path` | `REPORTING_PDF_FONT_NAME`, `REPORTING_PDF_FONT_PATH` | mPDF's own font | a TTF font for mPDF reports; set both |
| `queue` | `REPORTING_QUEUE` | `QUEUE_CONNECTION` | queue the report jobs go on |
| `cleanup_days` | | `90` | history kept by `reporting:prune` |
| `team_model` | `REPORTING_TEAM_MODEL` | none | the team model, only for tenant-aware generators |
| `navigation.cluster` | `REPORTING_NAVIGATION_CLUSTER` | the package's `Reporting` cluster | a cluster of yours to put the resources in |
| `navigation.group` | `REPORTING_NAVIGATION_GROUP` | none | a navigation group, used when there is no cluster |

`navigation.cluster` wins when both navigation keys are set, because a cluster has its own navigation entry. The
setting applies to all three resources: Reports, Histories and Schedules.

## Usage

### Concepts

**Generators** live in `App\Reports\Generators` (configurable via `reporting.generator_namespaces` /
`reporting.generator_paths`) and implement `GeneratorInterface`. The methods you'll actually write are
`generate()`, `getHeaders()`, `getModifiers()`, and `getDefaultParameters()`. Everything else (UI, queuing,
file export, cooldown checking) is handled by the package.

**Modifiers** are filters your generator declares: the package turns them into Filament form fields
automatically. Supported types: `date_range`, `select`, `multiselect`, `text`, `number`.

**Heavy reports** (`isHeavy(): true`) get a cooldown window (`reporting.default_cooldown_minutes`) so a
cached recent result is served instead of re-running the report. Set `estimateExecutionTime()` for
context in the UI.

**Schedules** are reusable cron configurations that can be attached to multiple reports via a relation
manager in Filament.

**Tenant-aware generators**: the `Reporting\Generator\Concerns\IsTenantAware` trait is opt-in and scopes
data/options to a team/tenant model. Set `reporting.team_model` to your tenant model's FQCN before using
it; the model must expose an `is_default` boolean column, and your user model must expose a `teams()`
relation.

### Commands

| Command                                | Purpose                                                    |
|-----------------------------------------|-------------------------------------------------------------|
| `reporting:install`                     | Publish config and set the navigation cluster/group           |
| `reporting:run {report}`                | Manually trigger a report                                   |
| `reporting:make:generator`              | Scaffold a new report generator class                       |
| `reporting:make:report-from-generator`  | Create a Report model from a discovered generator            |
| `reporting:prune --days=90`             | Delete old history rows/files                                |
| `reporting:cache:clear`                 | Refresh the generator discovery cache                        |
| `reporting:plugin:reset`                | Truncate all reporting tables                                |
| `reporting:plugin:test`                 | Run this package's own test suite with a coverage score      |
| `reporting:setup`                       | Generate Shield permissions, seed schedules, register generators |

## Extending

Write a generator for each report (see "Concepts"): implement `GeneratorInterface`, or scaffold one with
`reporting:make:generator`, and declare its modifiers so the package builds the form. Add `IsTenantAware` to scope a
generator to the current team.

## Testing

```bash
composer install
composer quality   # Pint, Rector, PHPStan, Pest
```

## Contributing

Open an issue or a pull request against `develop`. Run `composer quality` before pushing.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
