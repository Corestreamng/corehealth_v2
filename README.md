# CoreHealth v2 - Hospital Management Information System

CoreHealth v2 is a comprehensive, enterprise-grade Hospital Management Information System (HMIS) built on Laravel. It provides an integrated platform for managing all aspects of healthcare facility operations, from patient care and clinical workflows to financial management, human resources, multi-store inventory, and AI-assisted clinical summarization.

## Table of Contents

- [Overview](#overview)
- [Core Modules](#core-modules)
- [System Architecture](#system-architecture)
- [Inventory & Stock Management](#inventory--stock-management)
- [Accounting & Finance](#accounting--finance)
- [Module Documentation](#module-documentation)
- [Installation & Setup](#installation--setup)
- [Docker Deployment](#docker-deployment)
- [Developer Ergonomics & Makefile](#developer-ergonomics--makefile)
- [Code Style & Linting](#code-style--linting)
- [Development](#development)
- [Artisan Commands](#artisan-commands)
- [Testing](#testing)
- [CI/CD & Governance](#cicd--governance)
- [Contributing](#contributing)
- [Security](#security)
- [License](#license)

## Overview

CoreHealth v2 is designed to streamline hospital operations by integrating clinical, administrative, and financial processes into a unified system. Built with modern web technologies and best practices, it offers scalability, security, and reliability for healthcare institutions of all sizes.

### Key Technologies

| Component       | Technology                                     |
|-----------------|------------------------------------------------|
| **Framework**   | Laravel 8.x (PHP 8.3 target)                   |
| **Container**   | Docker & Docker Compose (PHP 8.3 FPM + Nginx + MySQL 8.0 + Redis 7) |
| **CI/CD**       | GitHub Actions Automated Pipeline (PHPUnit + CS Fixer Lint + Audit) |
| **Code Style**  | PSR-12 Enforced via `friendsofphp/php-cs-fixer` in `tools/` |
| **Ergonomics**  | `Makefile` for 1-command local development & Docker orchestration |
| **Frontend**    | Blade Templates (Modular Partials), Vanilla CSS, jQuery, Select2, Chart.js |
| **Database**    | MySQL 8.0 / MariaDB                            |
| **Architecture**| MVC with Service-Observer pattern (46 observers) |
| **Auth & RBAC** | Laravel UI + Spatie Permission                 |
| **DataTables**  | Yajra Laravel DataTables (server-side)         |
| **Logging**     | Structured Monolog JSON Channel                |
| **Auditing**    | owen-it/laravel-auditing                       |
| **Assets**      | Laravel Mix (Webpack)                          |
| **PDF/Invoices**| DomPDF, laraveldaily/laravel-invoices           |
| **Excel**       | Maatwebsite/Excel (PhpSpreadsheet)             |

---

## Core Modules

### 1. Reception & Patient Management
- **Patient Registration** — demographics, dependants, photo, covered by `PatientRegistrationTest`
- **Queue Management** — doctor queue with priority routing
- **Appointment Booking** — multi-provider scheduling, check-in, cancel/no-show, rescheduling, and doctor reassignments
- **Reception Workbench** — real-time dashboard for front-desk staff

### 2. Doctor / Consultation & Referrals
- **Doctor Dashboard** — assigned patient queue, pending reviews, consultation timers
- **Encounter Management** — diagnosis (ICD-10), prescriptions, lab/imaging orders, procedures, referrals, and template-based chief complaint capture
- **Incremental Save** — encounter sections auto-save via AJAX
- **Specialist Referrals** — creation from consultations, reception-side referral booking, doctor acceptance/declination, and patient referral histories

### 3. Emergency Intake
- **Walk-in & Emergency Triage** — rapid patient intake from any workbench
- **Triage & Disposition** — rapid triage scoring, emergency bed search and assignment, and direct disposition routing (admission or consulting clinic)

### 4. Nursing
- **Nursing Workbench** — modularized into partials (`_modals.blade.php`, `_scripts.blade.php`), vitals, medication chart, care plans, I&O charts
- **Medication Administration** — scheduling, administration logging, PRN
- **Intake & Output Charts** — fluid balance tracking
- **Nursing Notes** — typed notes with customisable templates
- **Shift Handover & Management** — shift start/end, per-shift activity reports, shift calendars, and multi-nurse handover acknowledgement
- **Injection & Immunization** — vaccination schedule tracking
- **Deceased Queue & Last Office** — clinical workflow routing and Last Office completions

### 5. Maternity Module (ANC & Wellness)
- **ANC Enrollment** — Antenatal Care (ANC) registration, obstetric/medical history, and previous pregnancy logs
- **ANC Visits** — progress monitoring, vitals, labs, imaging, prescriptions, and ANC cards/Road-to-Health card printing
- **Delivery & Partograph** — delivery records, baby registration (wellness, growth charts), and digital partograph entry
- **Postnatal & Immunizations** — postnatal visits and child immunization schedules synchronized with the nursing system

### 6. Surgery / Theatre Workbench
- **Theatre Queues** — patient procedure tracking and surgical queue metrics
- **Procedure Management** — surgical checklist tracking, inline procedure logs, and surgical team assignments
- **Theatre Billing** — inline billing for surgery services and consumables

### 7. Laboratory (LIS)
- **Lab Workbench** — sample collection $\rightarrow$ processing $\rightarrow$ results $\rightarrow$ verification
- **Lab Templates** — structured result templates (ranges, flags)
- **WYSIWYG Results** — rich-text editor for free-form reports
- **Audit Trail** — result edit history with approval workflow

### 8. Imaging / Radiology
- **Imaging Workbench** — request queue, result capture, report generation
- **Service Requests** — imaging orders linked to encounters
- **Result Management** — upload findings, radiologist review

### 9. Pharmacy
- **Pharmacy Workbench** — modularized into partials (`_modals.blade.php`, `_scripts.blade.php`, `_damages.blade.php`, `_returns.blade.php`), prescription queue, dispensing, FIFO batch tracking
- **Pharmacy Quick Actions** — returns (good/wrong_item/damaged/expired), damage reports
- **Pharmacy Reports** — revenue, stock value, daily sales, dispensing trends
- **Damage Reports** — auto-batch assignment, JE on approval (DR Expense, CR Inventory)
- **Returns** — restock for good/wrong_item, JE preview, refund via Patient Wallet (2200)
- **Stock Sync** — three-tier sync: `stock_batches` $\rightarrow$ `store_stocks` $\rightarrow$ `stocks` + `prices`

### 10. Inventory & Procurement
- **Multi-Store Support** — pharmacy stores, general stores, sub-stores
- **Stock Batches (FIFO)** — per-batch cost tracking, expiry monitoring
- **Purchase Orders** — create $\rightarrow$ approve $\rightarrow$ receive $\rightarrow$ batch creation
- **Store Requisitions & Returns** — inter-store transfer requests, approval workflows, and store/PO return processes
- **Stock Transfers** — FIFO or specific-batch transfers, weighted-average cost
- **Delivery Guards** — validation rules for receiving goods

### 11. Store Governance & Advanced Administration
- **Store Role Catalog** — definition of store roles (Central, Pharmacy Hub, Pharmacy Satellite, Lab, Imaging, Ward)
- **Lane Policy Matrix** — matrix rules regulating transfers between store roles (with none, manager, or admin approval requirements)
- **Context Resolution Rules** — automated store resolution based on user roles and department overrides (e.g. NURSE resolve to Ward store)
- **Test Resolution Panel** — simulation and resolution tracing panel for debugging resolve paths
- **Manager KPIs** — custom KPI widgets for Pharmacy, Wards, and Central Stores

### 12. Billing & Revenue Cycle
- **Billing Workbench** — payment queue, receipts, refunds, materialized queue optimization, covered by `BillingWorkbenchTest`
- **Multi-Payment** — Cash, Card, Transfer, HMO, Patient Wallet
- **Patient Deposits** — wallet system with auto-debit on billing
- **My Transactions** — patient-facing billing portal
- **Promotions** — discount rules and promo pricing

### 13. HMO & Insurance
- **HMO Workbench** — claims approval workflow (submitted $\rightarrow$ approved $\rightarrow$ paid)
- **Tariff Management** — per-HMO tariffs for products and services, import/export
- **Claims & Remittances** — batch claiming, payment reconciliation
- **Auto-Tariff Sync** — PriceObserver creates/updates HMO tariffs on price changes

### 14. Accounting & Finance
- **Double-Entry Accounting** — full General Ledger with Chart of Accounts
- **Journal Entries** — manual JEs + auto-generated by 30 observers
- **Financial Reports** — Trial Balance, P&L, Balance Sheet, Cash Flow
- **Bank Reconciliation** — statement import (CSV/OFX), auto-matching
- **Petty Cash** — fund management with reconciliation
- **Patient Deposits** — wallet-based prepayment system
- **Credit Notes** — issuance and application
- **Inter-Account Transfers** — bank-to-bank, cash-to-bank

### 15. Fixed Assets (IAS 16 Compliant)
- **Asset Register** — acquisition, categories, locations, depreciation schedules
- **Depreciation** — straight-line auto-calculation, monthly runs
- **Disposal** — gain/loss on sale, JE generation
- **Void** — for registration errors (no depreciation recorded)
- **CAPEX Projects** — capital expenditure tracking with budget integration

### 16. Budgeting & KPIs
- **Budget Management** — fiscal-year budgets with line items
- **CAPEX Budgets** — project-level capital budgeting
- **Cost Centers** — departmental cost tracking
- **Financial KPIs** — configurable performance indicators

### 17. Leases & Liabilities
- **Lease Management** — IFRS 16 compliant lease tracking
- **Payment Schedules** — auto-generated amortisation
- **Liability Schedules** — long-term liability management
- **Statutory Remittances** — tax, pension, NHIS obligations

### 18. Human Resources
- **HR Workbench** — employee dashboard, org chart
- **Leave Management** — types, requests, balances, calendar, approval workflow
- **Payroll** — salary profiles, pay heads, batch processing, payslip generation
- **Disciplinary** — query management, hearings, outcomes
- **Suspensions & Terminations** — workflow with document attachments
- **Employee Self-Service (ESS)** — leave requests, payslips, profile

### 19. AI/LLM Clinical Assistant
- **Multi-Provider Gateway** — supports Google Gemini, OpenAI, Anthropic Claude, Hugging Face, and local Ollama
- **Longitudinal EHR Summaries** — generates physician-level patient briefings by parsing 14 data vectors with intelligent caching
- **Clinical Note Polish** — rewrites raw dictated notes into structured medical formats
- **Connectivity Testing** — administrator panel for testing API keys and listing supported models

### 20. Internal Audit Workbench
- **Audit Responsibility Worksheets** — Cash Book/Billing, Bank Reconciliation, HMO verification, Disclosures/Refunds, Payroll/Expenses, Clinical patient flow, Ward income, Theatre bundles, Diagnostics, and Inventory
- **Digital Approvals (Audit Stamps)** — seals audited periods/responsibility domains to freeze modifications and establish accountability
- **Staff Receivables Settlement** — sequential payment allocation across outstanding staff bills with bank/discount overrides

### 21. Administration
- **Role-Based Access Control** — Spatie permissions, per-module
- **Hospital Configuration** — settings, departments, clinics, specialisations
- **Audit Logs** — full audit trail with export capability
- **Import/Export** — bulk data (products, services, staff, patients)
- **Chat/Messaging** — internal messaging system

---

## System Architecture

```
app/
├── Console/Commands/     # 5 artisan commands
├── Events/               # Domain events
├── Exceptions/           # Custom exception handlers
├── Helpers/              # Utility functions + BatchHelper
├── Http/Controllers/     # 100+ controllers across 7 subfolders
│   ├── Account/          # Legacy patient accounts
│   ├── Accounting/       # 19 controllers (GL, assets, leases, CAPEX, etc.)
│   ├── Admin/            # Tariff management
│   ├── API/              # Data endpoints
│   ├── Auth/             # Authentication (6 controllers)
│   ├── Doctor/           # Doctor dashboard & consultations
│   ├── HR/               # 12 controllers (leave, payroll, disciplinary, ESS)
│   └── Product/          # Legacy product controller
├── Jobs/                 # Queued jobs
├── Models/               # 199 Eloquent models
│   ├── Accounting/       # 38 models (JE, assets, leases, budgets, etc.)
│   └── HR/               # 22 models (leave, payroll, disciplinary)
├── Observers/            # 46 observers (auto JE, stock sync, HMO tariffs)
│   ├── Accounting/       # 30 accounting-specific observers
│   └── HR/               # 3 HR-specific observers
├── Policies/             # Authorization policies
├── Providers/            # Service + event providers
└── Services/             # 47 services
    ├── Accounting/       # 12 accounting services
    └── Dashboard/        # 17 dashboard-specific services

database/
├── migrations/           # Schema history
└── seeders/              # Sample data

docker/                   # Docker environment configs (Nginx & Supervisor)
resources/views/admin/    # Blade templates (workbenches modularized into partials)
routes/                   # 15 route files (web, accounting, hr, nursing, etc.)
tests/                    # 35 domain-specific PHPUnit feature and unit test suites
tools/                    # Isolated tooling environment (friendsofphp/php-cs-fixer)
Makefile                  # Unified developer command suite
docs/                     # 30+ documentation files
```

---

## Installation & Setup

### Prerequisites

- PHP >= 8.3
- Composer
- MySQL >= 8.0 or MariaDB >= 10.3
- Node.js >= 18.x
- npm

### Quick Setup with Makefile

```bash
# Clone repository
git clone https://github.com/Corestreamng/corehealth_v2.git
cd corehealth_v2

# Start Docker environment in 1 command
make up

# Or set up locally:
composer install
npm install
cp .env.example .env
php artisan key:generate
make import-test-db
composer dump-autoload -o
```

---

## Docker Deployment

CoreHealth v2 includes a complete containerized environment for development and production based on **PHP 8.3 FPM**, **Nginx**, **MySQL 8.0**, and **Redis 7**.

### Launching with Docker Compose / Makefile

```bash
# Build and start all services via Makefile
make build

# Or via Docker Compose directly:
docker compose up -d --build

# Execute tests in container
make test

# View logs
make logs
```

---

## Developer Ergonomics & Makefile

The project includes a comprehensive [Makefile](file:///home/mrapollos/Documents/work/corehealth_v2/Makefile) providing simple, standardized developer commands:

| Command | Description |
|---------|-------------|
| `make help` | Display available targets and documentation |
| `make up` | Start all Docker containers in background |
| `make build` | Rebuild and launch Docker stack |
| `make down` | Stop all Docker containers |
| `make test` | Execute full PHPUnit test suite inside Docker |
| `make test-local` | Run PHPUnit test suite locally |
| `make lint` | Run CS Fixer code style checks in dry-run mode |
| `make lint-fix` | Automatically format all PHP code to PSR-12 standard |
| `make audit` | Perform security audits (`composer audit`, `npm audit`) |
| `make fresh` | Drop tables and re-run migrations |
| `make shell` | Open a interactive bash session inside PHP container |

---

## Code Style & Linting

Code quality and formatting are enforced via **PHP CS Fixer** configured in [.php-cs-fixer.php](file:///home/mrapollos/Documents/work/corehealth_v2/.php-cs-fixer.php):

- **Standard**: Strictly follows PSR-12 code style guidelines across `app/`, `config/`, `database/`, `routes/`, and `tests/`.
- **Isolated Environment**: Maintained in an isolated `tools/` folder (`tools/vendor/bin/php-cs-fixer`) to prevent dependency conflicts with Laravel 8 core packages.
- **Commands**:
  ```bash
  composer lint     # or make lint (dry-run check)
  composer lint-fix # or make lint-fix (auto-fix formatting)
  ```
- **Automated Gating**: Enforced automatically on all pushes and pull requests via GitHub Actions.

---

## Testing

### Automated Testing Suite (35 Domain Test Suites)

The codebase features **35 domain-specific PHPUnit test suites** containing **155+ test methods**, verified against the live MySQL test database (`_corehealth_db_v2_test`).

#### Key Test Suites
- **`PatientRegistrationTest.php`** — Covers reception workbench view, patient creation, and file search.
- **`BillingWorkbenchTest.php`** — Covers cash, HMO, wallet payments, observer triggers, and datatable queue responses.
- **`AuthBootstrapTest.php` & `RolePermissionTest.php`** — Covers Spatie permission guards and authentication flows.
- **`FifoDispenseTest.php` & `PharmacyReturnsTest.php`** — Covers stock sync, FIFO dispensing, and inventory returns.

#### Running Tests

```bash
# Run all tests via Makefile
make test

# Or via PHPUnit locally
vendor/bin/phpunit --testdox
```

---

## CI/CD & Governance

- **Automated CI Workflow** ([.github/workflows/ci.yml](.github/workflows/ci.yml)): GitHub Actions pipeline running PHP 8.3, setting up a MySQL 8.0 container, importing `_corehealth_db_v2_test.sql`, running PHP CS Fixer lint checks, executing PHPUnit tests, building assets, and auditing security.
- **Structured JSON Logging**: Configured via Monolog `JsonFormatter` channel in `config/logging.php`.
- **Dependabot**: Configured in [.github/dependabot.yml](.github/dependabot.yml) for weekly Composer and NPM dependency security checks.
- **Semantic Version Tags**: Tagged across 35 historic milestones starting from `v2.0.0.0` through `v2.5.0.2`.

---

## Contributing

1. Review [CONTRIBUTING.md](CONTRIBUTING.md) for PR requirements, branch workflow, and testing rules.
2. Follow PSR-12 coding standards using `make lint` / `composer lint`.
3. Run `composer dump-autoload -o` after adding new classes.
4. Ensure 100% of test suites pass cleanly (`make test`) before opening pull requests.

---

## License

CoreHealth v2 is proprietary software. All rights reserved.
