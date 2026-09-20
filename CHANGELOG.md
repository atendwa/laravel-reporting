# Changelog

All notable changes to `atendwa/laravel-reporting` will be documented here.

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
