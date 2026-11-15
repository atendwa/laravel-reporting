# atendwa/laravel-reporting

[![PHP](https://img.shields.io/packagist/dependency-v/atendwa/laravel-reporting/php?label=php&color=777BB4)](composer.json)
[![Laravel](https://img.shields.io/packagist/dependency-v/atendwa/laravel-reporting/illuminate%2Fcontracts?label=laravel&color=FF2D20)](composer.json)
[![Filament](https://img.shields.io/packagist/dependency-v/atendwa/laravel-reporting/filament%2Ffilament?label=filament&color=FDAE4B)](composer.json)
[![Release](https://img.shields.io/packagist/v/atendwa/laravel-reporting?label=release&color=007EC6)](https://packagist.org/packages/atendwa/laravel-reporting)
[![CI](https://github.com/atendwa/laravel-reporting/actions/workflows/ci.yml/badge.svg?branch=develop)](https://github.com/atendwa/laravel-reporting/actions/workflows/ci.yml)

Reports for Laravel applications, run on the queue and managed in Filament. You write a generator class for each
report; the package builds its filter form, runs it in the background, exports CSV and PDF, keeps the history of every
run and can run it on a schedule.

- **Generators:** classes that return the rows of a report, discovered from the folders you configure.
- **Filters:** each generator declares its modifiers (dates, selects, numbers, text), and the package turns them into a
  Filament form.
- **Exports:** CSV, and PDF through mPDF or DomPDF, written in chunks so large reports stay within memory.
- **History:** every run is stored with its status, duration, files and the filters used.
- **Schedules:** cron expressions stored in the database and attached to any number of reports.
- **Heavy reports:** a cooldown window serves a recent result instead of running the same report again.
- **Notifications:** the person who ran a report gets a Filament database notification when it completes, with the
  download links, or when it fails.

## Requirements

- PHP 8.3 or later
- Laravel 11, 12 or 13 and Filament 5
- `spatie/laravel-permission` (installed with the package); permissions for the resources are generated for Filament
  Shield
- A PDF engine: `mpdf/mpdf` (the default) or `barryvdh/laravel-dompdf`
- A queue worker, and the Laravel scheduler for scheduled reports

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

To publish the config without the prompts, run `php artisan vendor:publish --tag=reporting-config`.

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

Create a generator, then register it as a report:

```bash
php artisan reporting:make:generator SalesReport --model=App\\Models\\Order   # --tenant for a tenant-aware one
php artisan reporting:make:report-from-generator                             # --all registers every new generator
```

The generator implements `Reporting\Contracts\GeneratorInterface`. The methods you fill in are `generate()`,
`getHeaders()` and `getModifiers()`; the stub fills in the rest (name, group, description, time estimate, whether it is
heavy, default parameters).

```php
final class SalesReport implements GeneratorInterface
{
    public function generate(array $modifiers): Collection
    {
        return Order::query()
            ->when($modifiers['date_from'] ?? null, fn ($query, $from) => $query->where('created_at', '>=', $from))
            ->get()
            ->map(fn (Order $order): array => [
                'reference' => $order->reference,
                'total' => $order->total,
            ]);
    }

    public function getHeaders(): array
    {
        return ['Reference', 'Total'];
    }

    public function getModifiers(): array
    {
        return [
            ['name' => 'date_from', 'label' => 'Date from', 'type' => 'date', 'required' => false,
                'default' => null, 'validation' => 'nullable|date', 'options' => []],
        ];
    }
}
```

The report then appears in the Reports resource, where people can run it with their filters, download the files and
attach schedules.

- **Modifier types:** `date` (or `date_range`), `select`, `multiselect`, `number` and `text`; `options` fills the
  selects.
- **Heavy reports:** return `true` from `isHeavy()` to apply the cooldown; `estimateExecutionTime()` is shown in the
  interface.
- **Schedules:** reports attached to a schedule run when the scheduler does, so the server needs
  `php artisan schedule:run` every minute.

### Commands

| Command | What it does |
|---|---|
| `reporting:install` | publishes the config and sets the navigation cluster or group |
| `reporting:setup` | generates the Shield permissions, seeds the schedules and registers the generators |
| `reporting:make:generator {name?} {--model=} {--tenant}` | creates a generator class |
| `reporting:make:report-from-generator {--all} {--regenerate}` | registers generators as reports |
| `reporting:run {report} {--user=} {--modifiers=} {--force}` | runs a report now; `--force` skips the running check |
| `reporting:prune {--days=90}` | deletes old history and its files |
| `reporting:cache:clear` | clears the generator discovery cache |
| `reporting:plugin:reset {--force}` | empties every reporting table |
| `reporting:plugin:test {--no-coverage}` | runs the package's own tests |

## Extending

- **Tenant-aware reports:** add the `Reporting\Generator\Concerns\IsTenantAware` trait (or pass `--tenant` when
  creating the generator) to limit the data and options to the current team. Set `reporting.team_model` first; the
  team model needs an `is_default` column and the user model a `teams()` relation.
- **Events:** `ReportGenerationStarted`, `ReportGenerated`, `ReportFailed` and `ReportScheduleUpdated` in
  `Reporting\Events`, for listeners of your own.
- **Access:** the Reports, Histories and Schedules resources use the package policies, so access follows the
  permissions `reporting:setup` creates.
- **Cross-tab reports:** return `group_rows_by`, `group_columns_by` and `group_value_column` from
  `getGroupingConfig()` to pivot the rows; return `null` for a flat report.

## Testing

```bash
composer install
composer test       # Pest
composer quality    # Pint, Rector, PHPStan and the tests, as in CI
```

## Contributing

Open an issue or a pull request against `develop`. Run `composer quality` before pushing.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
