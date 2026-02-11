# CoreHealth v2 - Hospital Management Information System

CoreHealth v2 is a comprehensive, enterprise-grade Hospital Management Information System (HMIS) built on Laravel. It provides an integrated platform for managing all aspects of healthcare facility operations, from patient care and clinical workflows to financial management and human resources.

## Table of Contents

- [Overview](#overview)
- [Core Modules](#core-modules)
- [System Architecture](#system-architecture)
- [Inventory & Stock Management](#inventory--stock-management)
- [Accounting & Finance](#accounting--finance)
- [Module Documentation](#module-documentation)
- [Installation & Setup](#installation--setup)
- [Development](#development)
- [Artisan Commands](#artisan-commands)
- [Testing](#testing)
- [Contributing](#contributing)
- [License](#license)

## Overview

CoreHealth v2 is designed to streamline hospital operations by integrating clinical, administrative, and financial processes into a unified system. Built with modern web technologies and best practices, it offers scalability, security, and reliability for healthcare institutions of all sizes.

### Key Technologies

| Component       | Technology                                     |
|-----------------|------------------------------------------------|
| **Framework**   | Laravel 8.x (PHP 8.x)                         |
| **Frontend**    | Blade Templates, jQuery, Select2, Chart.js     |
| **Database**    | MySQL / MariaDB                                |
| **Architecture**| MVC with Service-Observer pattern              |
| **Auth & RBAC** | Laravel UI + Spatie Permission                 |
| **DataTables**  | Yajra Laravel DataTables (server-side)         |
| **Auditing**    | owen-it/laravel-auditing                       |
| **Assets**      | Laravel Mix (Webpack)                          |
| **PDF/Invoices**| DomPDF, laraveldaily/laravel-invoices           |
| **Excel**       | Maatwebsite/Excel (PhpSpreadsheet)             |

## Core Modules

### 1. Reception & Patient Management
- **Patient Registration** — demographics, dependants, photo
- **Queue Management** — doctor queue with priority routing
- **Appointment Booking** — multi-provider scheduling
- **Reception Workbench** — real-time dashboard for front-desk staff

### 2. Doctor / Consultation
- **Doctor Dashboard** — assigned patient queue, pending reviews
- **Encounter Management** — diagnosis (ICD-10), prescriptions, lab/imaging orders, procedures, referrals
- **Incremental Save** — encounter sections auto-save via AJAX
- **Reason for Encounter** — template-based chief complaint capture

### 3. Nursing
- **Nursing Workbench** — vitals, medication chart, care plans, I&O charts
- **Medication Administration** — scheduling, administration logging, PRN
- **Intake & Output Charts** — fluid balance tracking
- **Nursing Notes** — typed notes with customisable templates
- **Shift Handover** — per-shift handover summaries
- **Injection & Immunization** — vaccination schedule tracking

### 4. Laboratory (LIS)
- **Lab Workbench** — sample collection → processing → results → verification
- **Lab Templates** — structured result templates (ranges, flags)
- **WYSIWYG Results** — rich-text editor for free-form reports
- **Audit Trail** — result edit history with approval workflow

### 5. Imaging / Radiology
- **Imaging Workbench** — request queue, result capture, report generation
- **Service Requests** — imaging orders linked to encounters
- **Result Management** — upload findings, radiologist review

### 6. Pharmacy
- **Pharmacy Workbench** — prescription queue, dispensing, FIFO batch tracking
- **Pharmacy Quick Actions** — returns (good/wrong_item/damaged/expired), damage reports
- **Pharmacy Reports** — revenue, stock value, daily sales, dispensing trends
- **Damage Reports** — auto-batch assignment, JE on approval (DR Expense, CR Inventory)
- **Returns** — restock for good/wrong_item, JE preview, refund via Patient Wallet (2200)
- **Stock Sync** — three-tier sync: `stock_batches` → `store_stocks` → `stocks` + `prices`

### 7. Inventory & Procurement
- **Multi-Store Support** — pharmacy stores, general stores, sub-stores
- **Stock Batches (FIFO)** — per-batch cost tracking, expiry monitoring
- **Purchase Orders** — create → approve → receive → batch creation
- **Store Requisitions** — inter-store transfer requests with approval
- **Stock Transfers** — FIFO or specific-batch transfers, weighted-average cost
- **Delivery Guards** — validation rules for receiving goods

### 8. Billing & Revenue Cycle
- **Billing Workbench** — payment queue, receipts, refunds
- **Multi-Payment** — Cash, Card, Transfer, HMO, Patient Wallet
- **Patient Deposits** — wallet system with auto-debit on billing
- **My Transactions** — patient-facing billing portal
- **Promotions** — discount rules and promo pricing

### 9. HMO & Insurance
- **HMO Workbench** — claims approval workflow (submitted → approved → paid)
- **Tariff Management** — per-HMO tariffs for products and services, import/export
- **Claims & Remittances** — batch claiming, payment reconciliation
- **Auto-Tariff Sync** — PriceObserver creates/updates HMO tariffs on price changes

### 10. Accounting & Finance
- **Double-Entry Accounting** — full General Ledger with Chart of Accounts
- **Journal Entries** — manual JEs + auto-generated by 27 observers
- **Financial Reports** — Trial Balance, P&L, Balance Sheet, Cash Flow
- **Bank Reconciliation** — statement import (CSV/OFX), auto-matching
- **Petty Cash** — fund management with reconciliation
- **Patient Deposits** — wallet-based prepayment system
- **Credit Notes** — issuance and application
- **Inter-Account Transfers** — bank-to-bank, cash-to-bank

### 11. Fixed Assets (IAS 16 Compliant)
- **Asset Register** — acquisition, categories, locations, depreciation schedules
- **Depreciation** — straight-line auto-calculation, monthly runs
- **Disposal** — gain/loss on sale, JE generation
- **Void** — for registration errors (no depreciation recorded)
- **CAPEX Projects** — capital expenditure tracking with budget integration

### 12. Budgeting & KPIs
- **Budget Management** — fiscal-year budgets with line items
- **CAPEX Budgets** — project-level capital budgeting
- **Cost Centers** — departmental cost tracking
- **Financial KPIs** — configurable performance indicators

### 13. Leases & Liabilities
- **Lease Management** — IFRS 16 compliant lease tracking
- **Payment Schedules** — auto-generated amortisation
- **Liability Schedules** — long-term liability management
- **Statutory Remittances** — tax, pension, NHIS obligations

### 14. Human Resources
- **HR Workbench** — employee dashboard, org chart
- **Leave Management** — types, requests, balances, calendar, approval workflow
- **Payroll** — salary profiles, pay heads, batch processing, payslip generation
- **Disciplinary** — query management, hearings, outcomes
- **Suspensions & Terminations** — workflow with document attachments
- **Employee Self-Service (ESS)** — leave requests, payslips, profile

### 15. Administration
- **Role-Based Access Control** — Spatie permissions, per-module
- **Hospital Configuration** — settings, departments, clinics, specialisations
- **Audit Logs** — full audit trail with export capability
- **Import/Export** — bulk data (products, services, staff, patients)
- **Chat/Messaging** — internal messaging system

## System Architecture

```
app/
├── Console/Commands/     # 5 artisan commands
├── Events/               # Domain events
├── Exceptions/           # Custom exception handlers
├── Helpers/              # Utility functions + BatchHelper
├── Http/Controllers/     # 90+ controllers across 7 subfolders
│   ├── Account/          # Legacy patient accounts
│   ├── Accounting/       # 19 controllers (GL, assets, leases, CAPEX, etc.)
│   ├── Admin/            # Tariff management
│   ├── API/              # Data endpoints
│   ├── Auth/             # Authentication (6 controllers)
│   ├── Doctor/           # Doctor dashboard & consultations
│   ├── HR/               # 12 controllers (leave, payroll, disciplinary, ESS)
│   └── Product/          # Legacy product controller
├── Jobs/                 # Queued jobs
├── Models/               # 140+ Eloquent models
│   ├── Accounting/       # 38 models (JE, assets, leases, budgets, etc.)
│   └── HR/               # 13 models (leave, payroll, disciplinary)
├── Observers/            # 34 observers (auto JE, stock sync, HMO tariffs)
│   └── Accounting/       # 27 accounting-specific observers
├── Policies/             # Authorization policies
├── Providers/            # Service + event providers
└── Services/             # 21 services
    └── Accounting/       # 12 accounting services

database/
├── migrations/           # Schema history
└── seeders/              # Sample data

resources/views/admin/    # Blade templates (workbenches, modals, partials)
routes/                   # 10 route files (web, accounting, hr, nursing, etc.)
docs/                     # 30 documentation files
```

### Observer-Driven Automation

CoreHealth uses **34 Eloquent observers** to automate side effects:

| Observer Area | Count | Examples |
|---------------|-------|---------|
| **Accounting** | 27 | JE creation on payments, expenses, payroll, disposals, deposits |
| **Pharmacy**   | 2  | PharmacyDamageObserver (inventory write-off), PharmacyReturnObserver (restock + refund) |
| **Stock**      | 1  | StockBatchObserver (auto-sync store_stocks + global stocks + prices) |
| **Pricing**    | 2  | PriceObserver (HMO tariff sync), ServicePriceObserver |
| **Other**      | 2  | BedObserver, HmoObserver |

## Inventory & Stock Management

### Three-Tier Stock Architecture

```
┌─────────────────┐     ┌──────────────────┐     ┌─────────────────┐     ┌────────────┐
│  stock_batches   │────▶│   store_stocks    │────▶│     stocks      │────▶│   prices   │
│  (per-batch FIFO)│     │ (per-store cache) │     │ (global cache)  │     │ (buy price)│
│  cost_price      │     │ current_quantity  │     │ current_quantity│     │ pr_buy_price│
│  current_qty     │     │                  │     │                 │     │            │
└─────────────────┘     └──────────────────┘     └─────────────────┘     └────────────┘
```

**Source of truth**: `stock_batches` table.

All downstream tables are computed caches kept in sync by the `StockBatchObserver` → `StockService`:

| Sync Step | Method | What it does |
|-----------|--------|-------------|
| 1. Store stock | `syncStoreStock()` | SUM of active batch `current_qty` → `store_stocks.current_quantity` |
| 2. Global stock | `syncGlobalStock()` | SUM across all stores → `stocks.current_quantity` |
| 3. Buy price | `syncProductPrice()` | Latest batch `cost_price` → `prices.pr_buy_price` |

### Price Sync

When a new stock batch is received (via purchase order, manual entry, or transfer), the system automatically updates the product's buy price (`prices.pr_buy_price`) to match the **latest batch's `cost_price`**. This ensures:

- Damage write-offs use the current cost
- COGS calculations reflect the most recent purchase cost
- Stock value reports are accurate

The sync is triggered automatically by the `StockBatchObserver` on every batch create/update, and can also be run in bulk via `php artisan stock:sync`.

### FIFO Dispensing

When dispensing medication, the system deducts from the **oldest batch first** (by `received_date`), ensuring proper inventory rotation and accurate COGS.

## Accounting & Finance

### Chart of Accounts (Key Codes)

| Code | Account | Purpose |
|------|---------|---------|
| 1010 | Cash | Cash at hand |
| 1110 | Accounts Receivable – HMO | Insurance claims |
| 1300 | Inventory | Stock on hand |
| 2200 | Customer Deposits | Patient wallet / prepayments |
| 5030 | Damaged Inventory Expense | Damage write-offs |
| 5040 | Expired Inventory Expense | Expiry write-offs |
| 5050 | Theft/Shrinkage Expense | Theft write-offs |
| 5060 | Loss on Returns | Non-restockable return losses |

### Auto-Generated Journal Entries

The following transactions automatically create double-entry JEs via observers:

- **Pharmacy Dispensing** — DR COGS, CR Inventory
- **Damage Approval** — DR Expense (5030/5040/5050), CR Inventory (1300)
- **Return Approval** — DR Inventory (1300) if restockable, else DR Loss (5060); CR Customer Deposits (2200)
- **Payment Receipt** — DR Cash/Bank, CR Revenue
- **Expense** — DR Expense, CR Cash/Bank
- **Fixed Asset Acquisition** — DR Asset, CR Cash/AP
- **Depreciation** — DR Depreciation Expense, CR Accumulated Depreciation
- **Asset Disposal** — DR Cash + Accumulated Dep, CR Asset + Gain/Loss
- **Payroll** — DR Salary Expense, CR Cash + Statutory liabilities
- **Lease Payments** — DR Lease Liability + Interest, CR Cash

## Module Documentation

### Accounting & Finance
- [Accounting Plan](docs/Accounting%20_plan.md)
- [Comprehensive Gap Analysis 2026](docs/ACCOUNTING_COMPREHENSIVE_GAP_ANALYSIS_2026.md)
- [Gap Analysis](docs/ACCOUNTING_GAP_ANALYSIS.md)
- [Implementation Checklist](docs/ACCOUNTING_IMPLEMENTATION_CHECKLIST.md)
- [System Enhancement Plan](docs/ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md)
- [UI Implementation Plan](docs/ACCOUNTING_UI_IMPLEMENTATION_PLAN.md)
- [Bank & Cash Statement Implementation](docs/BANK_CASH_STATEMENT_IMPLEMENTATION.md)

### Clinical Workbenches
- [Nursing Workbench Gap Analysis (Final)](docs/NURSING_WORKBENCH_GAP_ANALYSIS_FINAL.md)
- [Nursing Workbench v2](docs/NURSING_WORKBENCH_GAP_ANALYSIS_v2.md)
- [Pharmacy Workbench Implementation](docs/PHARMACY_WORKBENCH_IMPLEMENTATION_PLAN.md)
- [Pharmacy Implementation Status](docs/PHARMACY_WORKBENCH_IMPLEMENTATION_STATUS.md)
- [Billing Workbench](docs/BILLING_WORKBENCH_IMPLEMENTATION.md)

### Inventory & Stock Management
- [Inventory Implementation Plan](docs/INVENTORY_IMPLEMENTATION_PLAN.md)
- [Batch Stock Gap Analysis](docs/BATCH_STOCK_GAP_ANALYSIS.md)
- [Batch Stock Implementation Status](docs/BATCH_STOCK_IMPLEMENTATION_STATUS.md)
- [Delivery Guards Setup](docs/DELIVERY_GUARDS_SETUP.md)

### Fixed Assets & CAPEX
- [CAPEX Column Mapping](CAPEX_COLUMN_MAPPING.md)
- [CAPEX Sync Completed](CAPEX_SYNC_COMPLETED.md)
- [Void vs Disposal Guide](VOID_VS_DISPOSAL_GUIDE.md)

### Clinical Modules
- [Procedure Module Design](docs/PROCEDURE_MODULE_DESIGN_PLAN.md)
- [Lab Template Structure](docs/LAB_TEMPLATE_STRUCTURE.md)
- [Injection & Immunization Update](docs/INJECTION_IMMUNIZATION_UPDATE.md)
- [WYSIWYG & Result Edit Implementation](docs/WYSIWYG_AND_RESULT_EDIT_IMPLEMENTATION.md)

### Insurance & Billing
- [HMO Plan](docs/hmo_plan.md)
- [Biller Plan](docs/BILLER_PLAN.md)
- [My Transactions Implementation](docs/MY_TRANSACTIONS_IMPLEMENTATION.md)
- [My Transactions Test Guide](docs/MY_TRANSACTIONS_TEST_GUIDE.md)

### Human Resources
- [HRMS Implementation Plan](docs/HRMS_IMPLEMENTATION_PLAN.md)

### Technical Documentation
- [Database Field Reference](docs/DATABASE_FIELD_REFERENCE.md)
- [Environment Migration Guide](docs/ENV_TO_APPSETTINGS_MIGRATION.md)
- [Testing Checklist](docs/TESTING_CHECKLIST.md)

## Installation & Setup

### Prerequisites

- PHP >= 8.0
- Composer
- MySQL >= 5.7 or MariaDB >= 10.3
- Node.js >= 14.x
- npm

### Installation Steps

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd corehealth_v2
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Install JavaScript dependencies**
   ```bash
   npm install
   ```

4. **Environment configuration**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Configure database** — edit `.env` with your database credentials, then import schema:
   ```bash
   mysql -u root -p corehealth_v2 < _corehealth_db_v2_test.sql
   ```

6. **Run migrations** (if applicable)
   ```bash
   php artisan migrate
   ```

7. **Seed the database** (optional)
   ```bash
   php artisan db:seed
   ```

8. **Compile assets**
   ```bash
   npm run dev
   # or for production
   npm run production
   ```

9. **Generate optimised autoloader** (required for production / Unix deploy)
   ```bash
   composer dump-autoload -o
   ```
   > ⚠️ **Case-sensitivity caveat**: Some model files use lowercase filenames (e.g. `patient.php` for class `patient`). PHP class names are case-insensitive at runtime, but Composer's PSR-4 autoloader maps `Patient` → `Patient.php` literally, which fails on case-sensitive filesystems (Linux/Unix). Running `composer dump-autoload -o` builds a full classmap that resolves classes by scanning file contents, bypassing the filename case issue.
   >
   > **You must re-run this command after adding new classes.** The long-term fix is renaming files to match PSR-4 PascalCase conventions.

10. **Start the development server**
    ```bash
    php artisan serve
    ```

Visit `http://localhost:8000` to access the application.

## Development

### Code Structure

CoreHealth v2 follows Laravel conventions with additional patterns:

- **Services** (`app/Services/`) — Business logic layer (21 services)
- **Observers** (`app/Observers/`) — Automated side-effects on model events (34 observers)
- **Policies** (`app/Policies/`) — Authorization rules
- **Helpers** (`app/Helpers/`) — Utility functions, BatchHelper

### Route Files

| File | Purpose |
|------|---------|
| `routes/web.php` | Main application routes (~800 lines) |
| `routes/accounting.php` | Accounting module (18 sub-prefixes) |
| `routes/hr.php` | HR module (leave, payroll, disciplinary, ESS) |
| `routes/supply.php` | Purchase orders, requisitions, store workbench |
| `routes/nursing.php` | Nursing workbench, vitals, meds, I&O |
| `routes/charts.php` | Medication & I&O charts |
| `routes/reception.php` | Reception workbench, patient reg, queue |
| `routes/api.php` | API endpoints |

### Asset Compilation

```bash
# Development with hot reload
npm run watch

# Production build
npm run production
```

### Database Compatibility

CoreHealth targets **MySQL/MariaDB**. Key compatibility notes:
- Use `CURDATE()` / `DATEDIFF()` (not PostgreSQL functions)
- Use `LIKE` (not `ILIKE`)
- JSON columns use MariaDB-compatible syntax
- `ENUM` columns are used for status fields

## Artisan Commands

| Command | Description |
|---------|-------------|
| `php artisan stock:sync` | Reconcile stock across all tiers (batches → store → global → prices) |
| `php artisan stock:sync --create-batches` | Also create reconciliation batches for unbatched store stock |
| `php artisan stock:sync --dry-run` | Preview changes without writing |
| `php artisan process:daily-bed-bills` | Generate daily bed billing charges (scheduler) |
| `php artisan hmo:generate-tariffs` | Auto-generate HMO tariff entries |
| `php artisan scan:routes` | Scan and cache route metadata |
| `php artisan hmo:sync-executives` | Sync HMO executive user group |

### Stock Sync Details

The `stock:sync` command performs 4 steps:

1. **Store-stock sync** — recalculates `store_stocks.current_quantity` from batch SUM
2. **Unbatched detection** — finds store_stocks with qty not covered by batches, optionally creates reconciliation batches
3. **Global stock sync** — recalculates `stocks.current_quantity` from batch SUM across all stores
4. **Price sync** — updates `prices.pr_buy_price` from the latest batch's `cost_price`

## Testing

### Running Tests

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature

# Run with coverage
php artisan test --coverage
```

### Development Utilities

Several utility scripts are available in the project root:

- `check_*.php` — Database and configuration validation scripts
- `debug_*.php` — Debugging tools for specific modules
- `test_*.php` — Manual testing scripts for specific features
- `trigger_*.php` — Event and observer testing scripts

See [Testing Checklist](docs/TESTING_CHECKLIST.md) for detailed testing procedures.

## Project Status

### Completed ✅
- Patient registration & encounter management
- Doctor consultations & prescriptions
- Nursing workbench (vitals, meds, I&O, notes)
- Laboratory workbench (ordering → results → verification)
- Imaging workbench
- Pharmacy workbench (dispensing, FIFO, batch tracking)
- Pharmacy quick actions (returns, damages, with accounting)
- Billing workbench (multi-payment, deposits, receipts)
- HMO workbench (claims, tariffs, remittances)
- Full double-entry accounting (GL, JE, reports)
- Fixed assets (IAS 16 — acquisition, depreciation, disposal, void)
- CAPEX project management
- Leases & liabilities (IFRS 16)
- Bank reconciliation
- HR module (leave, payroll, disciplinary, ESS)
- Role-based access control (Spatie)
- Audit logging
- Batch stock management with three-tier sync + price sync

### In Progress 🚧
- Advanced financial analytics & dashboards
- Enhanced reporting and export
- Mobile application development

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

### Coding Standards

- Follow PSR-12 coding standards for PHP
- Use PascalCase for class names and filenames (PSR-4)
- Run `composer dump-autoload -o` after adding new classes (required for Unix deploy)
- Write comprehensive docblocks
- Include unit tests for new features
- Update documentation as needed

## Security

If you discover any security vulnerabilities, please email the security team immediately. Do not create public issues for security concerns.

## License

CoreHealth v2 is proprietary software. All rights reserved.

---

**Built with Laravel** — The PHP Framework for Web Artisans
