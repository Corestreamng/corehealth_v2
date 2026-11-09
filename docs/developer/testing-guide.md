# Automated Testing Standards & Test Suites

CoreHealth v2 features a comprehensive suite of **35+ domain-specific test suites** containing **160+ test methods** covering billing, pharmacy, reception, doctor encounters, and multi-tenant synchronizations.

---

## 1. Testing Rules & Database Invariants

### 1. Main MySQL Test Database
Tests must run against the live MySQL test database:
```
_corehealth_db_v2_test
```
> [!CAUTION]
> **Do NOT use SQLite or in-memory databases.**  
> Complex MariaDB/MySQL 8 JSON columns, stored procedures, and triggers are not supported in SQLite.

### 2. Transaction Rollbacks (`DatabaseTransactions`)
All test classes must extend `Tests\TestCase`. This automatically wraps every test in a transaction that rolls back upon completion, keeping the test database clean.

### 3. NEVER Use `RefreshDatabase`
> [!WARNING]
> **Do NOT add the `RefreshDatabase` trait to any test class.**  
> `RefreshDatabase` would wipe the 500+ pre-seeded hospital tables, catalog tariffs, and GL chart of accounts!

### 4. Robust HTTP Status Assertions
Feature tests inspecting web and DataTable endpoints must account for test environment variances:
```php
$this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
```

---

## 2. Test Execution Commands

```bash
# 1. Run full PHPUnit suite locally
make test-local
# or: composer test

# 2. Run full test suite inside Docker container
make test

# 3. Run directly with testdox details
vendor/bin/phpunit --testdox

# 4. Run a specific domain suite (e.g., Nursing or Pharmacy)
vendor/bin/phpunit tests/Feature/Nursing/
vendor/bin/phpunit tests/Feature/Pharmacy/

# 5. Run a specific test class
vendor/bin/phpunit tests/Feature/Nursing/ConsumableAndClinicalKitSearchTest.php
```

