# CoreHealth v2 Development Standards

When working on this project, strictly adhere to the following standards across architecture, UI, and testing:

## 1. UI, Views, & Workbenches
- **Major Clinical Workbenches** (Reception, Doctor, Pharmacy, etc.): These are implemented as massive, comprehensive single Blade files (e.g., `reception/workbench.blade.php` is 10,000+ lines). They contain inline CSS/JS, complex modal logic, and AJAX endpoints for a single-page-application feel. They extend `admin.layouts.app`.
- **Audit/Reporting Workbenches** (e.g., `OpsAudit`): These rely on a master layout (e.g., `resources/views/admin/ops_audit/layout.blade.php`). Controllers inject module-specific variables (`$module_title`, `$tabs`) rather than duplicating views.
- **Sidebar & Access Control**: The main sidebar is located in `resources/views/admin/partials/sidebar.blade.php`. It explicitly uses Spatie `@can` and `@role` directives to control access. Always check this file for role contexts.
- **DataTables**: The system heavily relies on AJAX-driven Yajra DataTables for data grids.
- **Print Outputs**: 
  - Printouts must use the unified `print.blade.php` wrapper. 
  - Controllers should detect `?action=print` in the request and return the raw HTML snippet for the table, which is then dynamically loaded and printed on the frontend.
  - Drill-downs in print data should expand in a tree structure.
  - Always fetch hospital branding and metadata using the `appsettings()` helper.
- **Name Formatting**: Whenever displaying patient or staff names, always include `othername` where available (e.g., `$user->surname . ' ' . $user->firstname . ' ' . $user->othername`).

## 2. Controllers & Architecture
- **Base Controllers**: Use Base Controllers (e.g., `OpsAuditBaseController`) to share common logic across modules. Child controllers should define configurations like `$modelMap` and call parent methods (e.g., `$this->processBulkStamp()`).
- **Observers**: The system uses 46+ observers for side effects (Accounting JEs, Stock Sync, Pricing). Do not bypass models with raw DB queries if an observer needs to catch the event.

## 3. Database Compatibility
- The project targets **MySQL/MariaDB**.
- Do NOT use PostgreSQL functions. Use `CURDATE()` and `DATEDIFF()`.
- Use `LIKE` (do NOT use `ILIKE`).
- Ensure JSON column operations use MariaDB-compatible syntax.

## 4. Testing Standards
- **Main Database**: Do NOT configure PHPUnit to use SQLite or an in-memory database. Tests must run against the main MySQL database to ensure complete schema compatibility.
- **Transactions**: The `tests/TestCase.php` base class has already been configured to use the `Illuminate\Foundation\Testing\DatabaseTransactions` trait. Because of this, all Feature and Unit tests extending `TestCase` will automatically wrap their database operations in a transaction and roll them back upon completion. 
- **No RefreshDatabase**: Do NOT manually add the `DatabaseTransactions` trait or `RefreshDatabase` trait to new test classes. Using `RefreshDatabase` would aggressively wipe the main production database!
- **Folder Organization**: Organize tests logically into domain-specific folders inside `tests/Feature/` (e.g., `tests/Feature/OpsAudit/`).

## 5. Autoloading & Naming Caveats
- Some legacy model files use lowercase filenames (e.g., `patient.php`).
- Because of this PSR-4 case mismatch on Unix filesystems, **you must run `composer dump-autoload -o`** after creating new classes to regenerate the optimized classmap.

## 6. Investigation & Context Gathering
- **Stop Guessing Schemas**: Do not guess database columns or relationships. Always use `php artisan tinker --execute="dump(DB::select('describe table_name'));"` or `show tables` to verify the exact schema in the live database.
- **Read Models and Migrations**: Before modifying queries, relationships, or business logic, always read the corresponding Eloquent Models and database migrations to understand the real foreign keys, scopes, and context.
