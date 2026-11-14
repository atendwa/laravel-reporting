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
php artisan migrate
```

Publish the config and choose where the resources sit in your Filament navigation:

```bash
php artisan reporting:install
```

This asks whether to nest the Reports/Histories/Schedules resources under one of your own Filament
clusters (giving its FQCN), or a plain navigation group label, or leave them under the package's own
built-in "Reporting" cluster (the default). You can also just publish the config file directly and edit
it by hand:

```bash
php artisan vendor:publish --tag=reporting-config
```

Pick a PDF engine and require it (only one is needed):

```bash
composer require barryvdh/laravel-dompdf   # config('reporting.pdf_engine') = 'dompdf' (default)
composer require mpdf/mpdf                 # config('reporting.pdf_engine') = 'mpdf'
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

   This creates `app/Reports/Generators/YourReportGenerator.php` implementing `GeneratorInterface`
   (see [`src/Contracts/GeneratorInterface.php`](src/Contracts/GeneratorInterface.php) for the full
   contract — `generate()`, `getHeaders()`, `getModifiers()`, `getName()`, etc.).

2. Register it as a `Report`:

   ```bash
   php artisan reporting:make:report-from-generator
   ```

   This walks you through picking a discovered generator and creates the `Report` row that Filament
   and the scheduler use.

3. Open the **Reporting** cluster in your Filament panel — your report is ready to run, schedule, and
   download from there.

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

Issues and pull requests are welcome. Please run `vendor/bin/pint` and `vendor/bin/phpstan analyse`
before submitting.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

The MIT License (MIT). See [LICENSE](LICENSE) for details.
