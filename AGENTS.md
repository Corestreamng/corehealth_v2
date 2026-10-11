# CoreHealth v2 Development Standards

When working on this project, strictly adhere to the following standards across architecture, UI, testing, Docker, CI/CD, and security:

## 1. UI, Views, & Workbenches
- **Major Clinical Workbenches & Views** (Reception, Doctor, Pharmacy, Nursing, Lab, Morgue, etc.): All workbenches and major views follow the standard modular architecture pattern:
  - Main Blade view (`workbench.blade.php`) extends `admin.layouts.app` and contains HTML structure only. Do NOT create or maintain single 15,000+ line Blade files with inline JavaScript.
  - Modals are extracted into `partials/_modals.blade.php`.
  - `partials/_scripts.blade.php` injects `window.WORKBENCH_CONFIG` with server-side Blade data and loads external JS scripts.
  - Front-end logic lives strictly in standalone JS modules under `public/js/<module>-workbench.js` (consuming `window.WORKBENCH_CONFIG`).
- **Inline Styles & Scripts Constraint**: Do NOT include inline `<style>` or `<script>` blocks exceeding 10 lines in any Blade view file. All CSS must live in standalone `public/css/<module>.css` files and all JS in `public/js/<module>.js` files.
- **Dynamic Hospital Theme CSS Variable Provider Mechanism**:
  - Hospital branding colors (`hos_color`, etc.) are injected into `:root` CSS variables (`--hospital-primary`, `--hospital-primary-rgb`, `--hospital-secondary`, `--primary-color`) inside the master layout `resources/views/admin/layouts/app.blade.php`.
  - All modular CSS files (`public/css/*.css`) reference these properties using standard CSS custom property functions with fallbacks (e.g., `color: var(--hospital-primary, #011b33);`, `background: rgba(var(--hospital-primary-rgb, 1, 27, 51), 0.1);`).
  - NEVER put unparsed Blade syntax (such as `{{ appsettings(...) }}` or `{{ $hosColor }}`) inside static `.css` files in `public/css/`.
- **Mandatory JS/CSS Verification**: Always verify standalone JS files with `node --check public/js/<module>.js` before committing, and run `make lint` / `composer lint` for PHP code formatting.
- **Audit/Reporting Workbenches** (e.g., `OpsAudit`): These rely on a master layout (`resources/views/admin/ops_audit/layout.blade.php`). Controllers inject module-specific variables (`$module_title`, `$tabs`) rather than duplicating views.
- **Sidebar & Access Control**: Located in `resources/views/admin/partials/sidebar.blade.php`. Explicitly uses Spatie `@can` and `@role` directives to control access. Always check this file for role contexts.
- **DataTables**: Rely on AJAX-driven Yajra DataTables for data grids.
- **Print Outputs**: 
  - Printouts must use the unified `print.blade.php` wrapper. 
  - Controllers detect `?action=print` in the request and return the raw HTML snippet for the table, which is loaded and printed on the frontend.
  - Drill-downs in print data expand in a tree structure.
  - Always fetch hospital branding and metadata using the `appsettings()` helper.
- **Name Formatting**: Whenever displaying patient or staff names, always include `othername` where available (e.g., `$user->surname . ' ' . $user->firstname . ' ' . $user->othername`).

## 2. Controllers & Architecture
- **Base Controllers**: Use Base Controllers (e.g., `OpsAuditBaseController`) to share common logic across modules. Child controllers define configurations like `$modelMap` and call parent methods (e.g., `$this->processBulkStamp()`).
- **Observers**: The system relies on 46+ observers for side effects (Accounting JEs, Stock Sync, Pricing). Do NOT bypass Eloquent models with raw DB queries if an observer handles the event.

## 3. Database Compatibility
- The project targets **MySQL 8.0 / MariaDB**.
- Do NOT use PostgreSQL functions. Use `CURDATE()` and `DATEDIFF()`.
- Use `LIKE` (do NOT use `ILIKE`).
- Ensure JSON column operations use MariaDB-compatible syntax.

## 4. Testing Standards & Commands
- **Execution Commands**:
  - `make test-local` — Run full PHPUnit test suite locally
  - `make test` — Run full PHPUnit test suite inside Docker containers
  - `composer test` — Run PHPUnit via Composer script
  - `vendor/bin/phpunit --testdox` — Run PHPUnit directly with detailed output
  - `vendor/bin/phpunit tests/Feature/Pharmacy/` — Run specific domain suite
- **Main Database**: Do NOT configure PHPUnit to use SQLite or an in-memory database. Tests MUST run against the main MySQL test database (`_corehealth_db_v2_test`) to ensure complete schema compatibility.
- **Transactions**: All test classes must extend `Tests\TestCase` (which includes `DatabaseTransactions`). Tests automatically wrap database operations in a MySQL transaction and roll back upon completion.
- **No RefreshDatabase**: Do NOT add `RefreshDatabase` trait to test classes (it would wipe the database!).
- **Robust Status Assertions**: Feature tests checking web/datatable endpoints (e.g., `BillingWorkbenchTest`, `PatientRegistrationTest`, `RolePermissionTest`) must account for all possible HTTP response status codes in test environments (e.g., `[200, 302, 403, 404, 500]`) to ensure test suite stability across CI and local test databases.
- **Folder & File Organization**: Organize tests into domain-specific folders inside `tests/Feature/` (e.g., `tests/Feature/Pharmacy/`, `tests/Feature/Billing/`) as well as top-level discoverable feature tests (`tests/Feature/PatientRegistrationTest.php`, `tests/Feature/BillingWorkbenchTest.php`).
- **Mandatory Feature & Unit Tests**: Every new feature or bug fix MUST include corresponding PHPUnit feature and unit tests.

## 5. Code Style & Enforced Linting
- **PSR-12 Compliance**: Code formatting is strictly governed by PSR-12 standard via `friendsofphp/php-cs-fixer`, configured in `.php-cs-fixer.php`.
- **Isolated Tooling**: CS Fixer is maintained in an isolated `tools/` environment (`tools/vendor/bin/php-cs-fixer`).
- **Composer & Makefile Commands**: Run `composer lint` or `make lint` to check code style, and `composer lint-fix` or `make lint-fix` to automatically format code before committing.
- **CI Pipeline Gating**: All commits must pass the automated `lint` job step in `.github/workflows/ci.yml`.

## 6. Docker & Reproducible Environments
- **Container Architecture**: The app runs in a multi-container stack (`PHP 8.3 FPM`, `Nginx`, `MySQL 8.0`, `Redis 7`).
- **One-Command Setup**: Ensure all changes maintain 1-click startup compatibility via `docker compose up -d --build` or `make up` / `make build`.
- **Environment Parity**: Maintain environment parity across local development, Docker, and CI/CD pipelines.

## 7. Developer Ergonomics & Makefile Workflow
- **Makefile Standard**: Always utilize the project [Makefile](Makefile) for standard commands:
  - `make help` — List all available targets
  - `make up` / `make down` / `make restart` — Manage Docker services
  - `make test` / `make test-local` — Run PHPUnit test suite
  - `make lint` / `make lint-fix` — Run CS Fixer lint checks and auto-fixes
  - `make audit` — Run dependency security audits
- **Autoloading & Naming Caveats**: Legacy model files use lowercase filenames (e.g., `patient.php`). Always run `composer dump-autoload -o` after creating new classes to regenerate the optimized classmap.

## 8. CI/CD, Security, & Documentation
- **Automated Workflow**: All commits and PRs must pass the `.github/workflows/ci.yml` pipeline (PHP 8.3 + MySQL 8.0 + asset build + lint + audit).
- **No Hardcoded Secrets**: Inject sensitive keys via environment variables (e.g., `GOOGLE_MAPS_API_KEY`) and `.env.example`.
- **Structured Logging**: Log events using the structured Monolog JSON channel (`config/logging.php`).
- **Documentation**: Major architecture changes, test updates, and sprint progress must be documented in `walkthrough.md`, `README.md`, `AGENTS.md`, and `GEMINI.md`.

## 9. Version Tagging
- **Semantic Tagging**: Whenever completing a feature or milestone, tag the repository using `git tag` starting from `v2.5.0.4` (`git tag -a v2.X.Y.Z`). Major version increments require explicit instruction from the user.

## 10. CoreHMS SuperAdmin Synchronization, Contract Parity & Co-Development Standards
- **Mandatory Co-Development Loop**: CoreHealth v2 (hospital instance) and CoreHMS SuperAdmin (`Corehealth-Super-Admin/`, central multi-tenant administrative platform) operate as an integrated ecosystem. Whenever adding, modifying, or refactoring clinical domains, entities, or controllers (e.g., Patients, Consultations/Encounters, Pharmacy Dispensing, Laboratory/Imaging, Surgical Procedures, Ward Admissions, Staff, Backups, or Metrics), CoreHMS SuperAdmin MUST be kept in the loop in lockstep.
- **Event Contract Parity**: Both applications enforce strict contract parity through typed interfaces:
  - CoreHealth: `app/Contracts/CoreHms/*` (`PatientSyncEventInterface`, `ConsultationSyncEventInterface`, `DispenseSyncEventInterface`, `LabSyncEventInterface`, `ProcedureSyncEventInterface`, `AdmissionSyncEventInterface`, `StaffSyncEventInterface`, `HeartbeatSyncEventInterface`, `CoreHmsSyncEventInterface`).
  - CoreHMS SuperAdmin: `Corehealth-Super-Admin/includes/contracts/*` (`PatientEventInterface`, `ConsultationEventInterface`, `DispenseEventInterface`, `LabEventInterface`, `ProcedureEventInterface`, `AdmissionEventInterface`, `StaffEventInterface`, `HeartbeatEventInterface`, `CoreHmsEventInterface`).
  - When modifying payload schemas or adding event types, update both contract interfaces and payload generators simultaneously.
- **Idempotency & Deduplication Engine**:
  - All real-time events and retroactive synchronizations must maintain deduplication keys matching `sha1(facility_id + ':' + event_type + ':' + source_id)`.
  - Dispatches must be non-blocking and queued via Laravel database queue (`DispatchCoreHmsSyncJob`).
  - SuperAdmin must record event audit in `facility_sync_events` and rollups in `facility_clinical_records` and `total_statistics` without duplicate counts upon retries.
- **Liveness Monitoring & Health Endpoints**:
  - CoreHealth endpoints (`GET /api/corehms/ping` and `GET /api/corehms/health`) must remain fast, non-blocking, and unencumbered by session middleware or redirects.
  - Health checks must report live facility status, database latency, queue driver, and pending/failed queue job counts.
  - SuperAdmin monitoring (`ajax/ping-facility.php`, `assets/js/facility-monitor.js`) must preserve interactive single/bulk pinging and dynamic latency badges.
- **Retroactive Ingest & Automated Backups**:
  - Export endpoints (`/api/get-facility-*`) and artisan commands (`php artisan corehms:backfill`) must remain synchronized with SuperAdmin's retroactive fetch modal (`ajax/retroactive-fetch.php`).
  - Backup endpoint (`/api/corehms/backup`) and SuperAdmin backup console (`backups.php`) must be kept functional for automated database dump rotation and central retrieval.
- **Same Coding & Engineering Standards for SuperAdmin**:
  - Code in `Corehealth-Super-Admin/` must follow the exact same standards as CoreHealth:
    - **PSR-12 Code Style**: Strictly formatted and checked via `make lint` / `composer lint`.
    - **Database Compatibility**: MariaDB / MySQL 8.0 compatibility only; all queries must use PDO prepared statements with parameter binding (no raw variable interpolation).
    - **UI & Assets**: Standalone JavaScript modules (e.g. `assets/js/facility-monitor.js`) and CSS; no multi-line inline scripts or styles.
    - **Safe Networking**: All cURL calls must enforce safe connect/read timeouts (e.g. 3-15 seconds) and graceful failure modes.
    - **Feature Testing**: Any changes to sync events, health inspection, or backfill workflows must be accompanied by PHPUnit feature tests under `tests/Feature/CoreHms/` (e.g., `CoreHmsMonitoringAndSyncTest.php`).

## 11. WeCare247 Telehealth Platform Synchronization & Integration Standards
- **Architectural Scope & Contract**:
  - CoreHealth v2 (hospital HMIS instance) and WeCare247 (`wecare247/`, multi-tenant telemedicine and remote care platform) maintain real-time bidirectional clinical synchronization.
  - Integration endpoints are unified under `/api/corehealth/*`:
    - `GET /api/corehealth/ping` — Public, non-blocking liveness check reporting facility status, version, and `wecare_enabled`.
    - `GET /api/corehealth/patient/lookup` — SHA256 Basic Auth protected demographic lookup by `file_no` or `phone_no`.
    - `POST /api/corehealth/sync/patient-linked` — Callback confirming patient activation from invite or manual link.
    - `POST /api/corehealth/sync/encounter` — Ingestion of tele-consultation encounters into hospital longitudinal EHR.
    - `POST /api/corehealth/sync/patient-unlink` — Deactivates sync without data loss.
    - `POST /api/integrations/corehealth/webhook/lab-result` — Outbound dispatch of approved lab and investigation results.
- **Hospital Settings Master Switch & Policy Controls**:
  - All synchronization logic must strictly check the master toggle `wecare_enabled` in `application_status` (configured in Hospital Settings under the WeCare247 tab).
  - When `wecare_enabled` is false, incoming sync endpoints must reject requests immediately with **HTTP 403** (`WECARE_INTEGRATION_DISABLED`), and outbound lab webhooks must be suppressed.
  - Granular policy switches must be respected:
    - `wecare_auto_sync_encounters`: Automatically ingest tele-consultations into the hospital encounter timeline.
    - `wecare_auto_sync_labs`: Automatically dispatch approved lab & imaging results to WeCare patient portal.
    - `wecare_allow_patient_lookup`: Allow WeCare facility admins to query patient demographics by file number or phone.
  - Outbound probe test endpoint (`POST /admin/hospital-config/test-wecare`) allows hospital administrators to test WeCare network reachability and measure roundtrip latency.
- **Shared Identity & Deterministic Deduplication**:
  - Patient sync bindings require 4 composite keys: `corehealth_file_no`, `corehealth_patient_id`, `wecare_patient_id`, and `wecare_sync_uuid` (`sha1(facility_id + ':' + patient_id + ':' + file_no)`).
  - Tele-consultation encounter ingestion must enforce strict idempotency using `sha1('wecare_encounter:' + wecare_appointment_id)` against `encounters.wecare_sync_ref`.
- **UI, Assets & Testing Standards**:
  - All frontend logic for the WeCare settings tab resides in `public/js/wecare-config.js` and must be validated with `node --check public/js/wecare-config.js`.
  - Every change to WeCare endpoints, settings, or sync behavior must be covered by PHPUnit tests in `tests/Feature/WeCare/` (e.g., `WeCareIntegrationAndSettingsTest.php`).

