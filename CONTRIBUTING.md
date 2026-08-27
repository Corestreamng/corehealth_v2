# Contributing to CoreHealth v2

Thank you for contributing to the CoreHealth v2 Hospital Management System. Please follow these guidelines to ensure quality, reliability, and security across the codebase.

---

## 1. Development Standards & Guidelines

- **Architecture**: Core clinical workbenches (Reception, Doctor, Pharmacy, etc.) use single Blade view architecture extending `admin.layouts.app`.
- **Database Compatibility**: Targets MySQL/MariaDB. Always use `CURDATE()`, `DATEDIFF()`, and `LIKE`. Do not use PostgreSQL-specific functions.
- **Testing Standard**:
  - All tests must run against the main MySQL test database (`_corehealth_db_v2_test`).
  - Do NOT use SQLite or `RefreshDatabase` trait (it will wipe production databases).
  - All tests MUST extend `Tests\TestCase` which uses `Illuminate\Foundation\Testing\DatabaseTransactions`.
- **Autoloading**: Legacy models may use lowercase filenames. Always run `composer dump-autoload -o` after creating new classes.

---

## 2. Development Workflow

1. Create a feature branch off `master`:
   ```bash
   git checkout -b feat/your-feature-name
   ```
2. Run test suites locally:
   ```bash
   php vendor/phpunit/phpunit/phpunit
   ```
3. Commit with semantic commit messages (`feat:`, `fix:`, `chore:`, `docs:`, `test:`).
4. Tag completed features starting from `v2.5.0.1`.

---

## 3. Pull Request Guidelines

- Ensure 100% of PHPUnit test suites pass before submitting PRs.
- Provide descriptive PR summaries outlining added features, bug fixes, and verification steps.
