# CoreHealth v2 Development Standards

When working on this project, strictly adhere to the following standards across architecture, UI, testing, Docker, CI/CD, and security:

## 1. UI, Views, & Workbenches
- **Major Clinical Workbenches** (Reception, Doctor, Pharmacy, Nursing, etc.): These extend `admin.layouts.app`. Massive workbench files must be modularized into sub-partials inside `partials/` subdirectories (e.g. `resources/views/admin/pharmacy/partials/_modals.blade.php`, `_scripts.blade.php`, `_damages.blade.php`, and `resources/views/admin/nursing/partials/_modals.blade.php`, `_scripts.blade.php`). Do NOT create or maintain single 15,000+ line Blade files.
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
- **Makefile Standard**: Always utilize the project [Makefile](file:///home/mrapollos/Documents/work/corehealth_v2/Makefile) for standard commands:
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
