# Laravel Reporting

A queue-based reporting system for Laravel with Filament UI integration: define generators, schedule them,
export CSV/PDF, and track every run's history — all through a Filament panel.

## Requirements

| Package               | Version       |
|------------------------|---------------|
| PHP                    | ^8.3          |
| Laravel                | ^11.0 \| ^12.0 \| ^13.0 |
| Filament               | ^5.0          |

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

## Quick start

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

## Artisan commands

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

## Configuration (`config/reporting.php`)

Key options: `generator_namespaces`, `generator_paths`, `disk`, `directory`, `default_cooldown_minutes`,
`pdf_engine` (`dompdf`|`mpdf`), `chunk_size`, `pdf_chunk_size`, `logo_path`, `queue`, `cleanup_days`,
`team_model`.

### Navigation (`navigation.cluster` / `navigation.group`)

The Reports, Histories, and Schedules resources are available out of the box under this package's own
`Reporting\Filament\Clusters\Reporting` cluster — no configuration needed to get started. To place them
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

## Testing

```bash
composer install
vendor/bin/pest
```

Test files under `tests/` are being migrated to run standalone via Orchestra Testbench — some may not
pass yet outside the origin monorepo. Contributions welcome.

## Contributing

Issues and pull requests are welcome. Please run `vendor/bin/pint` and `vendor/bin/phpstan analyse`
before submitting.

## License

The MIT License (MIT). See [LICENSE](LICENSE) for details.
