# Changelog

All notable changes to the CoreHealth v2 Hospital Management Information System (HMIS) will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [v2.6.0.0] - 2026-08-30

### Added
- **Dynamic JavaScript URL Resolver**: Introduced `public/js/workbench-helper.js` (`wbUrl()`, `wbRoute()`) for cross-environment URL/route resolution across subfolders, custom ports, and SSL proxies.
- **Observer Unit Test Suites**: Created `BedObserverTest.php` and `PriceObserverTest.php` to verify Eloquent side-effects and HMO tariff auto-generation.
- **Dependabot Governance**: Added `.github/dependabot.yml` for automated weekly Composer and NPM dependency security updates.
- **One-Command Setup**: Added `make fresh-setup` target to [Makefile](Makefile).

### Changed
- **Database Dump Relocation**: Relocated `_corehealth_db_v2_test.sql` to `database/dumps/_corehealth_db_v2_test.sql` to eliminate repository misclassification.
- **Workbench Architecture**: Refactored large inline JavaScript scripts across all 6 clinical workbenches (Pharmacy, Nursing, Billing, Reception, Lab, Morgue) into clean external files in `public/js/`.
- **Blade Modals Modularization**: Extracted modals into `partials/_modals.blade.php` sub-views.

### Fixed
- **Blade Tags in External JS**: Resolved 100% of unparsed Blade directives inside JavaScript files by passing `WORKBENCH_CONFIG` from Blade partials.
- **Billing Header Color**: Fixed broken `:root` CSS variable declarations restoring hospital primary color header gradients.

---

## [v2.5.0.7] - 2026-08-30
### Fixed
- Resolved MySQL database connection error 1049 in `StockUtilizationTest` for GitHub Actions CI runner.

## [v2.5.0.6] - 2026-08-30
### Fixed
- Restored hospital primary CSS variables in Billing Workbench patient header.

## [v2.5.0.5] - 2026-08-30
### Fixed
- Resolved `BedObserver` MySQL schema mismatch by removing obsolete `price_id` assignment.

## [v2.5.0.4] - 2026-08-30
### Added
- Automated PHP CS Fixer PSR-12 code style linting toolchain in `tools/`.
- Comprehensive Makefile developer ergonomics targets.

## [v2.0.0.0] - 2026-08-01
### Added
- Monolithic CoreHealth v2 HMIS core architecture with Observer side-effect system, Spatie RBAC, and Yajra DataTables integration.
