# Changelog

All notable changes to the CoreHealth v2 Hospital Management System will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [2.5.0.1] - 2026-08-27
### Added
- Comprehensive Feature & Unit Test Suite across 35 clinical and operational domains (155+ test methods).
- Schema compatibility refactoring ensuring test compatibility across all database versions.
- Centralized helper functions (`adjustBrightness`, `hexToRgba`).
- Docker deployment stack (`Dockerfile`, `docker-compose.yml`, Supervisord & Nginx configs) targeting PHP 8.3 FPM and MySQL 8.0.
- Structured JSON logging channel (`config/logging.php`).
- Automated CI pipeline workflow (`.github/workflows/ci.yml`) with PHP 8.3 and MySQL 8.0 service.
- `.github/dependabot.yml` dependency management policy.

### Changed
- Purged over 84 MB of unused vendored JS plugins (`tinymce`, `x-editable`, duplicate DataTables assets).
- Removed legacy `template/` theme directory.
- Sanitized sensitive variables and externalized Google Maps API key to environment configurations.

---

## [2.5.0.0] - 2026-08-20
### Added
- Granular Hospital Configuration UI Toggles for Emergency Intake across Reception, Doctor, and Pharmacy workbenches.
- Unidentified Patient Emergency Registration Workflow.
- Doctor Admission & Discharge full workflow.

---

## [2.4.9.0] - 2026-07-15
### Added
- Internal Audit & Ops Audit Workbenches with Yajra DataTables integration.
- Thermal Receipt printing views with configurable paper width settings.

---

## [2.4.0.0] - 2026-06-01
### Added
- Spatie Role & Permission access control integration across workbench sidebars.
- Patient Deposit and Patient Wallet management.

---

## [2.3.0.0] - 2026-04-10
### Added
- Pharmacy FIFO Stock Batch Dispensing engine.
- Automated Accounting Journal Entry Observers for stock, billing, and payment events.

---

## [2.2.0.0] - 2026-02-15
### Added
- Laboratory Workbench with sample collection, result entry, and result release workflows.
- Imaging Workbench with radiology request tracking and result upload.

---

## [2.1.0.0] - 2025-11-20
### Added
- HMO Tariff Management and Tariff Normalization engines.
- HR & Payroll Workbench for staff management and payslip generation.

---

## [2.0.0.0] - 2022-12-01
### Added
- Initial release of CoreHealth v2 Hospital Management System monolith.
- Core Reception, Doctor, Pharmacy, and Billing modules built on Laravel 8.
