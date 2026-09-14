# Changelog

All notable changes to `atendwa/laravel-reporting` will be documented here.

## Unreleased

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
