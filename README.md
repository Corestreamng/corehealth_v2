# CoreHealth v2 - Hospital Management Information System

CoreHealth v2 is an enterprise-grade Hospital Management Information System (HMIS) built on Laravel 8.x (PHP 8.3 target). It provides an integrated platform for healthcare facilities of all sizes, orchestrating longitudinal patient pathways, high-density clinical workbenches, double-entry general ledger accounting, multi-store FIFO inventory, and centralized multi-tenant cloud synchronization.

---

## 1. System Overview & Key Technologies

CoreHealth v2 unifies clinical, administrative, and revenue operations into distraction-free, zero-scroll operational cockpits optimized for standard hospital displays ($1366 \times 768$).

| Component | Technology & Implementation |
|---|---|
| **Framework & Runtime** | Laravel 8.x on **PHP 8.3 FPM** |
| **Container Stack** | Multi-container Docker (**PHP 8.3 FPM**, **Nginx**, **MySQL 8.0**, **Redis 7**) |
| **Ergonomics & Automation** | Unified [`Makefile`](Makefile) for 1-command Docker management, testing, and linting |
| **Documentation Engine** | Modern VitePress documentation portal under `docs/` (`npm run docs:dev`) |
| **Frontend Architecture** | Viewport-locked Blade views, dynamic CSS custom properties, standalone JS modules |
| **Database & ORM** | MySQL 8.0 / MariaDB 10.5+ with 46+ Eloquent Observers enforcing GL balancing |
| **Access Control (RBAC)** | Role-Based Access Control via Spatie Permission with strict blade navigation guards |
| **Code Style & Quality** | PSR-12 standard enforced via isolated `friendsofphp/php-cs-fixer` in `tools/` |
| **Automated Testing** | 35 domain-specific PHPUnit feature suites running on live MySQL schema `_corehealth_db_v2_test` |
| **Health Interoperability** | **HL7 FHIR R4** (v4.0.1), **openEHR** (Rel 1.1.0+), **OpenEMR** (v7.0+), and **NDPA 2023** via Controller Proxies |
| **External Integrations** | WeCare247 Telehealth sync and CoreHMS SuperAdmin central multi-facility monitoring |

---

## 2. Comprehensive Documentation Engine (VitePress)

All module-specific clinical stories, user manuals, and technical API references are maintained in the modern VitePress documentation engine under [`docs/`](docs/):

```bash
# Launch interactive local documentation portal
npm run docs:dev

# Build static documentation bundle
npm run docs:build
```

### Documentation Tracks

- **User Story & Operational Guides** (`docs/user-guides/workbenches/*.md`): Real-world clinical workflows, step-by-step procedures, and authentic UI screenshots for hospital personnel.
- **Developer API Reference** (`docs/developer/workbenches/*.md`): Paystack-style API contracts, route tables, database schemas, observer triggers, and PHPUnit suites for engineers.
- **Health Interoperability Reference** ([`docs/developer/interoperability/rest-api-crud-endpoints.md`](docs/developer/interoperability/rest-api-crud-endpoints.md)): Full CRUD specifications for HL7 FHIR R4, openEHR ITS-REST, OpenEMR compatibility, and NDPA 2023 consent APIs.
- **Clinical Pathway** ([`docs/user-guides/clinical-pathway.md`](docs/user-guides/clinical-pathway.md)): End-to-end patient journey from intake to discharge.
- **Legacy Planning Archive** ([`docs_archive/README.md`](docs_archive/README.md)): Historical engineering specifications and sprint records.

---

## 3. Core Clinical & Operational Workbenches

Every hospital department operates within a dedicated zero-scroll workbench adhering to CoreHealth's 4-part modular structure:

| Department / Role | Primary Workbench Route | Role Guard | User Guide | Developer API |
|---|---|---|---|---|
| **Reception & Records** | `/reception/workbench` | `SUPERADMIN\|ADMIN\|RECEPTIONIST` | [Reception Guide](docs/user-guides/workbenches/reception.md) | [Reception API](docs/developer/workbenches/reception.md) |
| **Doctor Consultation** | `/encounters?status=waiting` | `SUPERADMIN\|ADMIN\|DOCTOR` | [Doctor Guide](docs/user-guides/workbenches/doctor.md) | [Doctor API](docs/developer/workbenches/doctor.md) |
| **Nursing & Inpatient Care** | `/nursing-workbench` | `SUPERADMIN\|ADMIN\|NURSE` | [Nursing Guide](docs/user-guides/workbenches/nursing.md) | [Nursing API](docs/developer/workbenches/nursing.md) |
| **Pharmacy & Dispensary** | `/pharmacy/workbench` | `SUPERADMIN\|ADMIN\|PHARMACIST` | [Pharmacy Guide](docs/user-guides/workbenches/pharmacy.md) | [Pharmacy API](docs/developer/workbenches/pharmacy.md) |
| **Medical Laboratory (LIS)** | `/lab/workbench` | `SUPERADMIN\|ADMIN\|LAB_SCIENTIST` | [Lab Guide](docs/user-guides/workbenches/laboratory.md) | [Lab API](docs/developer/workbenches/laboratory.md) |
| **Imaging & Radiology (RIS)** | `/imaging/workbench` | `SUPERADMIN\|ADMIN\|RADIOLOGIST` | [Imaging Guide](docs/user-guides/workbenches/imaging.md) | [Imaging API](docs/developer/workbenches/imaging.md) |
| **Theatre & Surgery** | `/theatre/workbench` | `SUPERADMIN\|ADMIN\|SURGEON` | [Theatre Guide](docs/user-guides/workbenches/theatre.md) | [Theatre API](docs/developer/workbenches/theatre.md) |
| **Maternity (ANC/PANC)** | `/maternity/workbench` | `SUPERADMIN\|ADMIN\|MIDWIFE` | [Maternity Guide](docs/user-guides/workbenches/maternity.md) | [Maternity API](docs/developer/workbenches/maternity.md) |
| **Inpatient Wards** | `/beds` | `SUPERADMIN\|ADMIN\|NURSE` | [Inpatient Guide](docs/user-guides/workbenches/inpatient.md) | [Inpatient API](docs/developer/workbenches/inpatient.md) |
| **Billing & Cashier** | `/billing/workbench` | `SUPERADMIN\|ADMIN\|BILLER` | [Billing Guide](docs/user-guides/workbenches/billing.md) | [Billing API](docs/developer/workbenches/billing.md) |
| **HMO & Managed Care** | `/hmo/workbench` | `SUPERADMIN\|ADMIN\|HMO_OFFICER` | [HMO Guide](docs/user-guides/workbenches/hmo.md) | [HMO API](docs/developer/workbenches/hmo.md) |
| **Store & Inventory** | `/store/workbench` | `SUPERADMIN\|ADMIN\|STORE_KEEPER` | [Store Guide](docs/user-guides/workbenches/inventory.md) | [Store API](docs/developer/workbenches/inventory.md) |
| **Internal Audit & OpsAudit** | `/ops-audit/workbench` | `SUPERADMIN\|ADMIN\|INTERNAL_AUDITOR` | [Audit Guide](docs/user-guides/workbenches/ops-audit.md) | [Audit API](docs/developer/workbenches/ops-audit.md) |
| **Human Resources (HRMS)** | `/hr/workbench` | `SUPERADMIN\|ADMIN\|HR_OFFICER` | [HRMS Guide](docs/user-guides/workbenches/hr.md) | [HRMS API](docs/developer/workbenches/hr.md) |
| **Mortuary & Last Office** | `/mortuary/workbench` | `SUPERADMIN\|ADMIN\|MORTUARY_ATTENDANT` | [Mortuary Guide](docs/user-guides/workbenches/mortuary.md) | [Mortuary API](docs/developer/workbenches/mortuary.md) |
| **Operational Cockpit** | `/home?mode=cockpit` | *All Authenticated Roles* | [Cockpit Guide](docs/user-guides/workbenches/cockpit.md) | [Cockpit API](docs/developer/workbenches/cockpit.md) |

---

## 4. Quick Start & Developer Ergonomics

The project includes a comprehensive [`Makefile`](Makefile) providing standardized one-command workflows:

### Launch with Docker (Recommended)

```bash
# Build and start Docker services (PHP 8.3 FPM, Nginx, MySQL 8.0, Redis 7)
make up

# Execute full automated test suite inside Docker
make test

# View container logs
make logs

# Stop all services
make down
```

### Local Environment Setup

```bash
# Install PHP & Node dependencies
composer install
npm install

# Configure environment and encryption keys
cp .env.example .env
php artisan key:generate

# Import pre-seeded test database
make import-test-db

# Run tests locally
make test-local

# Documentation Engine (VitePress)
npm run docs:dev      # Launch local dev server with hot reload
npm run docs:build    # Build static docs into public/docs/
# Access docs in browser via Laravel server at http://localhost:8000/docs/
# (Mermaid diagrams render 100% offline with zero CDN dependencies)
```

---

## 5. Architecture Invariants & Standards

Contributors and automated agents must strictly adhere to the project invariants defined in [`AGENTS.md`](AGENTS.md):

1. **Modular Workbench Architecture**: Viewport locked (`@section('is_workbench', true)`), structural HTML only in main blade view, modals in `partials/_modals.blade.php`, `window.WORKBENCH_CONFIG` in `partials/_scripts.blade.php`, and standalone JavaScript in `public/js/<module>-workbench.js`.
2. **Double-Entry Observers**: 46+ Eloquent Observers manage double-entry General Ledger postings, FIFO batch tracking, and audit trails. **Never bypass Eloquent models with raw DB queries (`DB::table(...)`) on watched entities.**
3. **Database Compatibility**: MySQL 8.0 / MariaDB 10.5+. Standard SQL `LIKE` or `App\Helpers\ClinicalSearchHelper` for multi-token keyword resolution. Never use PostgreSQL syntax.
4. **Testing Invariants**: All tests extend `Tests\TestCase` (includes `DatabaseTransactions`) against the live schema `_corehealth_db_v2_test`. **Never use `RefreshDatabase` or SQLite.**
5. **PSR-12 Isolated Linting**: Code style is enforced via `friendsofphp/php-cs-fixer` in `tools/`. Run `make lint` or `make lint-fix`.
6. **Mandatory Documentation & Screenshots**: Every UI or workflow modification requires updating documentation under `docs/` and capturing/refreshing authentic screenshots in `docs/public/images/workbenches/` following `docs/developer/screenshot-guide.md` ($1 \le \text{visuals} \le 5$ per page).
7. **Health Interoperability Parity**: Inbound FHIR, openEHR, and OpenEMR writes proxy production controllers (Local Work Parity) to guarantee all 46+ observers fire, zero core schema mutations, LOINC/UCUM/ATC bindings, and NDPA 2023 consent compliance.

---

## 6. Directory Structure

```
app/                      # Domain logic, Controllers, 199 Eloquent Models, 46 Observers, Services
app/Interoperability/     # Multi-standard layer: FHIR R4, openEHR, OpenEMR, NDPA Sanitizer
Corehealth-Super-Admin/   # Central Multi-Tenant Administration Cloud Platform
database/                 # Migrations, Seeders, and _corehealth_db_v2_test.sql.gz test schema
docker/                   # Multi-container Docker configurations (PHP 8.3 FPM, Nginx, Redis)
docs/                     # VitePress Documentation Engine (User Stories & Developer APIs)
docs_archive/             # Historical engineering specifications and sprint records
public/                   # Static compiled assets, standalone modular JS and CSS
resources/views/admin/    # High-density modular blade views and workbench cockpits
routes/                   # 16 domain route files including routes/interop.php
tests/                    # 36 domain-specific PHPUnit test suites (DatabaseTransactions)
tools/                    # Isolated PHP CS Fixer tooling environment
Makefile                  # Unified developer orchestration commands
AGENTS.md                 # Authoritative architecture invariants and developer rules
```

---

## 7. Releases & Semantic Versioning

CoreHealth v2 follows semantic versioning (`v2.X.Y.Z`). Releases are queried dynamically before tagging:

```bash
# Query highest release tag
git tag -l "v*" --sort=-v:refname | head -n 1
```

Release history, changelogs, and upgrade notes are documented under [`docs/releases/`](docs/releases/index.md).

---

## License

CoreHealth v2 is proprietary software. All rights reserved.
