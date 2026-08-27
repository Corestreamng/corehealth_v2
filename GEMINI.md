# CoreHealth v2 Development Standards

When working on this project, strictly adhere to the following standards across architecture, UI, testing, Docker, CI/CD, and security:

## 1. UI, Views, & Workbenches
- **Major Clinical Workbenches** (Reception, Doctor, Pharmacy, etc.): These are implemented as comprehensive single Blade files (e.g., `reception/workbench.blade.php`). They contain inline CSS/JS, modal logic, and AJAX endpoints for a single-page-application feel, extending `admin.layouts.app`.
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

## 4. Testing Standards
- **Main Database**: Do NOT configure PHPUnit to use SQLite or an in-memory database. Tests MUST run against the main MySQL test database (`_corehealth_db_v2_test`) to ensure complete schema compatibility.
- **Transactions**: All test classes must extend `Tests\TestCase` (which includes `DatabaseTransactions`). Tests automatically wrap database operations in a MySQL transaction and roll back upon completion.
- **No RefreshDatabase**: Do NOT add `RefreshDatabase` trait to test classes (it would wipe the database!).
- **Folder Organization**: Organize tests logically into domain-specific folders inside `tests/Feature/` (e.g., `tests/Feature/OpsAudit/`, `tests/Feature/Pharmacy/`).
- **Mandatory Feature & Unit Tests**: Every new feature or bug fix MUST include corresponding PHPUnit feature and unit tests.

## 5. Docker & Reproducible Environments
- **Container Architecture**: The app runs in a multi-container stack (`PHP 8.3 FPM`, `Nginx`, `MySQL 8.0`, `Redis 7`).
- **One-Command Setup**: Ensure all changes maintain 1-click startup compatibility via `docker compose up -d --build`.
- **Environment Parity**: Maintain environment parity across local development, Docker, and CI/CD pipelines.

## 6. CI/CD & Security Hygiene
- **Automated Workflow**: All commits and pull requests must pass the `.github/workflows/ci.yml` pipeline (PHP 8.3 + MySQL 8.0 + asset build).
- **No Hardcoded Secrets**: Never commit live API keys or `.env.save` files. Inject sensitive keys via environment variables (e.g., `GOOGLE_MAPS_API_KEY`) and `.env.example`.
- **Structured Logging**: Log events using the structured Monolog JSON channel (`config/logging.php`).
- **Dependency Hygiene**: Maintain lockfiles (`composer.lock`, `package-lock.json`) and run security audits (`composer audit`, `npm audit`).

## 7. Autoloading & Naming Caveats
- Some legacy model files use lowercase filenames (e.g., `patient.php`).
- Because of PSR-4 case mismatch on Unix filesystems, **you must run `composer dump-autoload -o`** after creating new classes to regenerate the optimized classmap.

## 8. Version Tagging
- **Semantic Tagging**: Whenever completing a feature or milestone, tag the repository using `git tag` starting from `v2.5.0.1` (`git tag -a v2.X.Y.Z`). Major version increments require explicit instruction from the user.
