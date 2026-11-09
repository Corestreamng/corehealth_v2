# CoreHealth v2 Development Standards & Architecture Invariants

When contributing to CoreHealth v2, strictly adhere to these core invariants across architecture, database, testing, Docker, linting, and documentation. All module-specific clinical stories and detailed API references reside in the documentation engine under `docs/`.

## 1. Modular Workbench & UI Architecture
- **Standard Modular Pattern**: All clinical and administrative workbenches strictly enforce a 4-part modular structure:
  - Main Blade view (`workbench.blade.php`) extends `admin.layouts.app` and contains structural HTML only.
  - Modals are extracted into `partials/_modals.blade.php`.
  - `partials/_scripts.blade.php` injects `window.WORKBENCH_CONFIG` with server-side routes and IDs.
  - Frontend logic lives strictly in standalone JS modules under `public/js/<module>-workbench.js`.
- **Inline Styles & Scripts Constraint**: Do NOT include inline `<style>` or `<script>` blocks exceeding 10 lines in any Blade view. CSS belongs in `public/css/<module>.css` and JS in `public/js/<module>.js`.
- **Dynamic Hospital Theme CSS Variables**: Hospital branding (`hos_color`, etc.) is injected into `:root` CSS variables (`--hospital-primary`, `--hospital-primary-rgb`, `--hospital-secondary`) in `admin.layouts.app`. All CSS files reference these properties using CSS custom property functions with fallbacks (e.g. `var(--hospital-primary, #011b33)`). NEVER put unparsed Blade syntax inside static `.css` files.
- **Mandatory JS/CSS Verification**: Always verify standalone JS files with `node --check public/js/<module>.js` before committing.
- **Zero-Scroll Viewport Locking**: Workbenches declare `@section('is_workbench', true)` and `@section('body_class', 'is-workbench has-workbench')` to lock the master viewport (`height: 100vh; overflow: hidden`), suppress marketing footers, and scale controls to high-density 30px heights.
- **Sidebar & Access Control**: Authoritative role and permission guards are defined character-for-character in `resources/views/admin/partials/sidebar.blade.php` using Spatie `@role` and `@can`. Never invent or mix up roles.
- **Name Formatting**: Always display patient and staff names including `othername` where available: `$user->surname . ' ' . $user->firstname . ($user->othername ? ' ' . $user->othername : '')`.

## 2. Eloquent Models, Controllers & Observers
- **Base Controllers**: Share common datatable, audit, and filter pipelines via Base Controllers (e.g. `OpsAuditBaseController`). Child controllers define configurations like `$modelMap` and invoke shared parent methods.
- **46+ Observers & Double-Entry Invariant**: The system relies on 46+ Eloquent observers for critical side effects (double-entry General Ledger journal entries, three-tier FIFO stock sync, HMO tariffs, audit trails). **NEVER bypass Eloquent models with raw DB queries** (`DB::table(...)`) on entities watched by observers; always use Eloquent (`Payment::create(...)`) to guarantee ledger balancing.

## 3. Database Compatibility (MySQL 8.0 / MariaDB)
- The project targets **MySQL 8.0** and **MariaDB 10.5+**.
- Do NOT use PostgreSQL functions. Use `CURDATE()`, `NOW()`, and `DATEDIFF()`.
- Use standard SQL `LIKE` or `App\Helpers\ClinicalSearchHelper` for multi-token keyword resolution. Never use `ILIKE`.
- Ensure JSON column queries use MariaDB-compatible syntax (`JSON_UNQUOTE(JSON_EXTRACT(...))`).

## 4. Testing Invariants & Standards
- **Main MySQL Test Database**: Tests MUST run against the live MySQL test database: `_corehealth_db_v2_test`. Never configure PHPUnit to use SQLite or an in-memory database (they lack support for complex JSON expressions and schema fixtures).
- **Transaction Rollbacks**: All test classes must extend `Tests\TestCase` (which includes `DatabaseTransactions`). Every test executes inside a MySQL transaction and rolls back automatically.
- **NO `RefreshDatabase`**: **NEVER add the `RefreshDatabase` trait to any test class.** It would wipe the entire test database schema and pre-seeded tables.
- **Status Assertions**: Web and DataTable feature tests must account for test environment variances: `$this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]))`.
- **Domain Organization**: Group tests into domain folders in `tests/Feature/` (e.g., `tests/Feature/Pharmacy/`, `tests/Feature/Nursing/`, `tests/Feature/Billing/`).

## 5. PSR-12 Code Style & Isolated Linting
- Code formatting strictly follows PSR-12 standard via `friendsofphp/php-cs-fixer`, configured in `.php-cs-fixer.php`.
- Maintained in an isolated environment (`tools/vendor/bin/php-cs-fixer`) to prevent dependency conflicts with Laravel core.
- Run `make lint` or `composer lint` for dry-run checks; run `make lint-fix` or `composer lint-fix` to auto-format.
- All commits and PRs must pass the automated `lint` step in `.github/workflows/ci.yml`.

## 6. Docker & Developer Ergonomics (Makefile)
- The stack runs in multi-container Docker (`PHP 8.3 FPM`, `Nginx`, `MySQL 8.0`, `Redis 7`).
- Always utilize the project [Makefile](Makefile):
  - `make test-local` — Run full PHPUnit test suite locally
  - `make test` — Run full PHPUnit test suite inside Docker containers
  - `make lint` / `make lint-fix` — Run CS Fixer lint checks and auto-fixes
  - `make up` / `make down` / `make restart` — Manage Docker services
  - `make fresh` — Drop tables and re-run migrations on test schema
  - `make audit` — Run dependency security audits

## 7. Mandatory Documentation & Semantic Version Tagging
- **VitePress Documentation Engine**: Human-readable documentation is maintained under `docs/` and built via `npm run docs:dev` / `npm run docs:build`.
  - **Local Build & Offline Access**: Run `npm run docs:build` to compile static HTML/JS into `public/docs/`. View locally in browser at `http://localhost:8000/docs/` (served natively by Laravel route with automatic fallback redirects for `/user-guides/`, `/developer/`, and `/releases/`). Mermaid diagrams render 100% offline via local `mermaid.min.js` with zero CDN dependency.
  - **Replicable Documentation Pattern**: All workbench user guides strictly enforce the 4-part pattern: *Clinical Objective & Overview $\to$ Exact UI Location / Where to Click $\to$ Required vs Optional Fields, Types & Variations $\to$ Downstream Handover (Who gets it next and what they do)*. Guides strictly avoid CSS/HTML/JS developer jargon (`100vh`, `#dom-id`, `DOM fragments`).
  - **Two Tracks**: Every module must have (1) a customer/user-facing **Story Guide** (`docs/user-guides/workbenches/*.md`) featuring real-world personas, step-by-step clinical narratives, and Mermaid workflow diagrams, and (2) a technical **Developer API Reference** (`docs/developer/workbenches/*.md`) detailing route guards, controllers, models, and schemas.
  - **Core Story vs Subpages Hierarchy**: The main workbench page (`docs/user-guides/workbenches/<module>.md`) MUST present the complete core clinical/operational journey. Subpages are reserved for deep granular details, field-by-field instructions, and edge cases. Every referenced subpage MUST exist, be indexed in `docs/.vitepress/config.mjs`, and contain relevant authentic UI screenshots for its workflows.
  - **Strict Fact-Checking**: All routes, roles, controllers, fields, and UI button names in documentation MUST exist in actual code (e.g. `routes/web.php`, `sidebar.blade.php`, blade partials). Never invent fictional routes, roles, or fields.
  - **Visual Quota Invariant (At Least 1 Screenshot Per Operational Phase)**: Every user guide page and subpage MUST contain at least 1 authentic UI screenshot for every main operational phase/section (e.g., a page with 5 main phases requires at least 5 authentic screenshots). Mermaid diagrams are used wherever relevant to visualize clinical decisions and data flow, but diagrams DO NOT replace pictures/screenshots. Capture authentic images under `docs/public/images/workbenches/` via `/browser` automation using standard credentials (`sysadmin@mail.com` / `123456`) against `http://127.0.0.1:8000`.
  - **Visual Inspection & Retake Invariant**: When capturing screenshots, agents MUST visually analyze them using `read_file` to confirm the target tab, modal, or form is actively visible, the viewport is scrolled to the right level without obstruction, and collapsible ribbons (such as expanded patient headers) do not occlude workspace content. Agents MUST perform automated retakes if occlusions or wrong tabs appear.
  - **Automated Screenshot Tooling**: Automated captures must utilize `tools/capture_workbench_screenshots.js` (or `npm run capture:screenshots` / `make screenshots`) driven by `puppeteer-core` against local Google Chrome.
  - **Mandatory Screenshot & Doc Updates**: Whenever adding, modifying, or refactoring UI features, workflows, or controllers, agents MUST proactively update the corresponding story and developer docs under `docs/` and capture or refresh relevant screenshots in `docs/public/images/workbenches/`. Never leave documentation or UI visuals stale.
  - **File Length Standard**: Rule and overview files (`AGENTS.md`, `GEMINI.md`, `README.md`) strictly remain concise ($\le 200$ lines). Documentation engine pages under `docs/` can go up to 400 lines to ensure exhaustive, comprehensive clinical and technical coverage.
  - **NO `walkthrough.md` files**: Do NOT create or maintain `walkthrough.md` files; purge any that appear.
- **Dynamic Highest Tag Verification**:
  - Never hardcode or assume an arbitrary starting tag.
  - Always query the repository dynamically for the absolute highest semver tag before releasing:
    `git tag -l "v*" --sort=-v:refname | head -n 1`
  - Determine the next sequential patch release tag (e.g., `v2.6.0.67` &rarr; `v2.6.0.68`).
  - Create annotated tags only: `git tag -a v2.X.Y.Z -m "Release v2.X.Y.Z: <Summary>"`.
- **Mandatory Documentation Update on Tagging**: Every git release tag creation MUST update the corresponding story and developer docs in `docs/` and add a release record under `docs/releases/<TAG>.md`.

## 8. Documentation References & Legacy Archive
- Active Documentation Portal: `docs/index.md`
- Core Architecture: `docs/developer/architecture.md`
- Coding Standards: `docs/developer/coding-standards.md`
- Database & Observers Ledger: `docs/developer/database-and-observers.md`
- Testing Standards: `docs/developer/testing-guide.md`
- Reception & Records Story: `docs/user-guides/workbenches/reception.md`
- Longitudinal Patient Pathway: `docs/user-guides/clinical-pathway.md`
- Legacy Planning & Audits Archive: `docs_archive/README.md`
