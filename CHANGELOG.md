# Changelog

All notable changes to `atendwa/laravel-reporting` will be documented here.

## v0.1.5

- Fixed `created_by`/`updated_by` being NOT NULL with no default and no code path that ever populated
  `updated_by` - every report/schedule/history insert failed under strict SQL mode. Both are nullable
  now and populated everywhere a record is actually created or updated.
- Fixed `Report::schedules()` / `ReportingSchedule::reports()` querying the wrong pivot table name
  (`reporting_report_schedules` instead of the actually-migrated `r_report_schedules`) - this was the
  root cause of `r_report_schedules` missing-table errors.
- Removed a leftover `isSystemStaff()` special-case in `Access::isAdministrator()` that the v0.1.3
  cleanup missed - page access is now solely `hasRole('super_admin')` via this package's own
  spatie/laravel-permission dependency. Also removed the unused, host-specific `Reporting::PANEL`
  constant.
- The report font (mPDF/DomPDF) and header logo no longer assume a consuming app has placed
  `public_path('fonts/futuralt.ttf')` / `public_path('images/branding/logo.png')` - both are now
  optional, via `config('reporting.pdf_font')` and the existing `config('reporting.logo_path')`,
  falling back to sane defaults when unset.
- Fixed DomPDF chunk generation not creating `storage/framework/temp` before writing to it.
- The package's own test suite had no working harness at all (no `TestCase`, no `phpunit.xml`) and
  three Filament resource tests referenced test-support classes and a `Team` model that don't exist
  anywhere in this package. Added a self-contained harness so `vendor/bin/pest` passes on its own.

## v0.1.4

- Migrations no longer depend on the host project's `Support\Abstractions\PluginMigration` or the `audit()` Blueprint
  macro: the package ships `Reporting\Database\PluginMigration` with its own `auditColumns()`.

## v0.1.3

- Removed the last dependencies on the origin project: the `BetaFilament` traits/overrides and the global
  `isSystemStaff()` helper. The package now ships its own `UsesPolicySetup`, `ResourceAccessGate` and
  `Access::isAdministrator()` (super_admin role, or `isSystemStaff()` on the user model when it exists).
- `ListReports` now extends Filament's `ListRecords` directly.

## v0.1.2

- Added `reporting:install` (Shield-style) to publish the config and interactively choose the Filament
  cluster or navigation group the Reports/Histories/Schedules resources register under.
- Added `reporting.navigation.cluster` / `reporting.navigation.group` config keys, applied consistently
  across all three resources; defaults to the package's own built-in `Reporting` cluster.
- Removed the last hardcoded navigation group: `ReportingScheduleResource` previously always showed
  under "Support Data" via `BetaFilament\Concerns\SupportResourceNavigationGroup`; it's config-driven now.

## v0.1.0

- Initial extraction as a standalone composer package from its origin monorepo.
- Restructured to standard Laravel-package layout (`config/`, `database/`, `resources/`, `routes/` at
  package root; `src/` for PHP classes only), wired through `spatie/laravel-package-tools`.
- Config-driven generator namespaces/paths, PDF engine, and tenant model — no more hardcoded
  application-specific defaults.
- Fixed: `reporting:run` referenced a nonexistent `LockManager` class and would always fail.
- Fixed: the Schedules relation manager tab crashed on open (called a method from a removed trait).
- Fixed: the History relation manager tab rendered blank instead of showing history rows.
- Fixed: numeric formatting always appended `.00` to integers due to a `float === int` comparison that
  is never true in PHP.
- Fixed: retrying a report from an orphaned history row (parent `Report` deleted) would crash.
