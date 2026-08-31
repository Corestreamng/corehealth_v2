# Contributing to CoreHealth v2

Thank you for contributing to the CoreHealth v2 Hospital Management System. Please follow these guidelines to ensure quality, reliability, and security across the codebase.

---

## 1. Development Standards & Guidelines

- **Architecture**: Core clinical workbenches (Reception, Doctor, Pharmacy, Nursing, Lab, Morgue, etc.) follow a modular pattern:
  - Blade view (`workbench.blade.php`) contains only HTML structure, extending `admin.layouts.app`
  - Modals in `partials/_modals.blade.php`
  - `partials/_scripts.blade.php` injects `window.WORKBENCH_CONFIG` with Blade data and loads the JS module
  - Business logic lives in `public/js/<module>-workbench.js` (no inline Blade in JS)
- **Database Compatibility**: Targets MySQL 8.0 / MariaDB. Always use `CURDATE()`, `DATEDIFF()`, and `LIKE`. Do not use PostgreSQL-specific functions.
- **Input Validation**: All controller methods must call `$request->validate()` before processing. See [docs/input-validation.md](./docs/input-validation.md) for full details.
- **Testing Standard**:
  - All tests must run against the main MySQL test database (`_corehealth_db_v2_test`).
  - Do NOT use SQLite or `RefreshDatabase` trait (it will wipe production databases).
  - All tests MUST extend `Tests\TestCase` which uses `Illuminate\Foundation\Testing\DatabaseTransactions`.
  - Every new controller or feature **must** have a corresponding PHPUnit test file.
- **Autoloading**: Legacy models may use lowercase filenames. Always run `composer dump-autoload -o` after creating new classes.

---

## 2. Development Workflow

1. Create a branch off `master` using the appropriate prefix:
   ```bash
   git checkout -b feat/your-feature-name    # New features
   git checkout -b fix/your-bug-fix          # Bug fixes
   git checkout -b chore/your-chore          # Refactoring, tooling
   git checkout -b test/your-test-suite      # Adding tests
   git checkout -b docs/your-docs-update     # Documentation
   ```

2. Run linting before committing:
   ```bash
   composer lint          # Check for CS violations
   composer lint-fix      # Auto-fix CS violations
   ```

3. Run the full test suite locally:
   ```bash
   make test-local
   # or: vendor/bin/phpunit --testdox
   ```

4. Commit with semantic commit messages:
   - `feat:` — New feature
   - `fix:` — Bug fix
   - `chore:` — Refactoring, tooling, configuration
   - `test:` — Adding or updating tests
   - `docs:` — Documentation changes

5. Tag completed milestones starting from `v2.5.0.0` using semantic versioning.

---

## 3. Required CI Checks

**All pull requests and pushes to `master` or `main` must pass all CI pipeline jobs** before merging. The CI pipeline (`ci.yml`) runs:

| Job | What it checks | Must pass? |
|---|---|---|
| **PHP Tests (MySQL)** | Full PHPUnit suite against MySQL 8.0 | ✅ Required |
| **Build Frontend Assets** | `npm ci && npm run production` | ✅ Required |
| **PHP Code Style (CS Fixer)** | PSR-12 compliance via php-cs-fixer | ✅ Required |
| **Security Audit** | `composer audit` + `npm audit --audit-level=critical` | ⚠️ Advisory |

If the lint job fails, run `composer lint-fix` to auto-resolve violations before pushing.

---

## 4. Pull Request Guidelines

- Ensure 100% of PHPUnit test suites pass before submitting PRs.
- Every new feature or controller must include at least one corresponding test file in `tests/Feature/<Module>/`.
- Provide descriptive PR summaries outlining added features, bug fixes, and verification steps.
- Reference the relevant issue or task ID in the PR description.
- Security-sensitive changes must be reviewed by a project maintainer before merging.
- See [SECURITY.md](./SECURITY.md) for vulnerability reporting.

---

## 5. Code Style

This project enforces **PSR-12** via `friendsofphp/php-cs-fixer` (configured in `.php-cs-fixer.php`). The CS Fixer is installed in an isolated `tools/` environment.

```bash
# Check for violations (dry-run)
make lint

# Auto-fix all violations
make lint-fix
```

The CI pipeline will fail if any CS violations are present on merge branches.
