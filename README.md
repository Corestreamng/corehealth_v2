# CoreHealth v2 - Hospital Management Information System

CoreHealth v2 is a comprehensive, enterprise-grade Hospital Management Information System (HMIS) built on Laravel. It provides an integrated platform for managing all aspects of healthcare facility operations, from patient care and clinical workflows to financial management and human resources.

## Table of Contents

- [Overview](#overview)
- [Core Features](#core-features)
- [System Architecture](#system-architecture)
- [Module Documentation](#module-documentation)
- [Installation & Setup](#installation--setup)
- [Development](#development)
- [Testing](#testing)
- [Contributing](#contributing)
- [License](#license)

## Overview

CoreHealth v2 is designed to streamline hospital operations by integrating clinical, administrative, and financial processes into a unified system. Built with modern web technologies and best practices, it offers scalability, security, and reliability for healthcare institutions of all sizes.

### Key Technologies

- **Framework**: Laravel (PHP)
- **Frontend**: Blade Templates, JavaScript, Vue.js components
- **Database**: MySQL/MariaDB
- **Architecture**: MVC with Service-Repository pattern
- **Authentication**: Laravel Sanctum/Passport
- **Real-time**: WebSockets for notifications and updates

## Core Features

### Clinical Management
- **Patient Registration & Records**: Comprehensive electronic health records (EHR)
- **Outpatient & Inpatient Management**: Complete encounter tracking
- **Nursing Workbench**: Vitals monitoring, medication administration, care plans
- **Laboratory Information System (LIS)**: Test ordering, processing, and results
- **Pharmacy Management**: Prescription management, dispensing, and inventory
- **Radiology/Imaging**: DICOM integration and results management
- **Procedure Management**: Surgical and clinical procedures tracking

### Administrative Features
- **Appointment Scheduling**: Multi-provider scheduling with conflict management
- **Billing & Revenue Cycle**: Insurance claims, invoicing, and payment processing
- **Inventory Management**: Multi-location stock control with batch tracking
- **Human Resource Management**: Employee records, attendance, and payroll

### Financial Management
- **General Ledger**: Complete double-entry accounting system
- **Accounts Receivable/Payable**: Invoice and payment management
- **Bank & Cash Management**: Bank reconciliation and cash flow tracking
- **Financial Reporting**: Profit/Loss, Balance Sheet, Trial Balance

### Integration & Interoperability
- **HMO Integration**: Health insurance claims processing
- **Payment Gateways**: Multiple payment method support
- **API Access**: RESTful APIs for third-party integration
- **Data Export**: HL7, CSV, and custom format support

## System Architecture

CoreHealth v2 follows a modular architecture with clear separation of concerns:

```
├── app/
│   ├── Models/          # Eloquent ORM models
│   ├── Controllers/     # HTTP request handlers
│   ├── Services/        # Business logic layer
│   ├── Repositories/    # Data access layer
│   ├── Observers/       # Model event listeners
│   ├── Policies/        # Authorization logic
│   └── Helpers/         # Utility functions
├── database/
│   ├── migrations/      # Database schema
│   └── seeders/         # Sample data
├── resources/
│   ├── views/          # Blade templates
│   └── js/             # Frontend assets
├── routes/             # Application routes
├── tests/              # Automated tests
└── docs/               # Documentation
```

## Module Documentation

### Accounting & Finance
- [Accounting Plan](docs/Accounting%20_plan.md) - Overall accounting module strategy
- [Comprehensive Gap Analysis 2026](docs/ACCOUNTING_COMPREHENSIVE_GAP_ANALYSIS_2026.md) - Future enhancements roadmap
- [Gap Analysis](docs/ACCOUNTING_GAP_ANALYSIS.md) - Current system gaps
- [Implementation Checklist](docs/ACCOUNTING_IMPLEMENTATION_CHECKLIST.md) - Setup guide
- [System Enhancement Plan](docs/ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md) - Planned improvements
- [UI Implementation Plan](docs/ACCOUNTING_UI_IMPLEMENTATION_PLAN.md) - User interface guidelines
- [Bank & Cash Statement Implementation](docs/BANK_CASH_STATEMENT_IMPLEMENTATION.md) - Banking module setup

### Clinical Workbenches
- [Nursing Workbench Gap Analysis](docs/NURSING_WORKBENCH_GAP_ANALYSIS_FINAL.md) - Latest analysis
- [Nursing Workbench v2](docs/NURSING_WORKBENCH_GAP_ANALYSIS_v2.md) - Version 2 updates
- [Pharmacy Workbench Implementation](docs/PHARMACY_WORKBENCH_IMPLEMENTATION_PLAN.md) - Pharmacy module plan
- [Pharmacy Implementation Status](docs/PHARMACY_WORKBENCH_IMPLEMENTATION_STATUS.md) - Current progress
- [Billing Workbench](docs/BILLING_WORKBENCH_IMPLEMENTATION.md) - Billing interface setup

### Inventory & Stock Management
- [Inventory Implementation Plan](docs/INVENTORY_IMPLEMENTATION_PLAN.md) - Stock management system
- [Batch Stock Gap Analysis](docs/BATCH_STOCK_GAP_ANALYSIS.md) - Batch tracking analysis
- [Batch Stock Implementation Status](docs/BATCH_STOCK_IMPLEMENTATION_STATUS.md) - Current status
- [Delivery Guards Setup](docs/DELIVERY_GUARDS_SETUP.md) - Stock delivery validation

### Clinical Modules
- [Procedure Module Design](docs/PROCEDURE_MODULE_DESIGN_PLAN.md) - Surgical procedures system
- [Lab Template Structure](docs/LAB_TEMPLATE_STRUCTURE.md) - Laboratory test templates
- [Injection & Immunization Update](docs/INJECTION_IMMUNIZATION_UPDATE.md) - Vaccination tracking
- [WYSIWYG & Result Edit Implementation](docs/WYSIWYG_AND_RESULT_EDIT_IMPLEMENTATION.md) - Rich text editors

### Insurance & Billing
- [HMO Plan](docs/hmo_plan.md) - Health insurance integration
- [Biller Plan](docs/BILLER_PLAN.md) - Billing system architecture
- [My Transactions Implementation](docs/MY_TRANSACTIONS_IMPLEMENTATION.md) - Patient billing portal
- [My Transactions Test Guide](docs/MY_TRANSACTIONS_TEST_GUIDE.md) - Testing procedures

### Human Resources
- [HRMS Implementation Plan](docs/HRMS_IMPLEMENTATION_PLAN.md) - HR module development

### Technical Documentation
- [Database Field Reference](docs/DATABASE_FIELD_REFERENCE.md) - Database schema documentation
- [Environment Migration Guide](docs/ENV_TO_APPSETTINGS_MIGRATION.md) - Configuration management
- [Testing Checklist](docs/TESTING_CHECKLIST.md) - QA procedures

## Installation & Setup

### Prerequisites

- PHP >= 8.1
- Composer
- MySQL >= 5.7 or MariaDB >= 10.3
- Node.js >= 14.x
- npm or yarn

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

5. **Configure database**
   - Edit `.env` file with your database credentials
   - Import the database schema: `_corehealth_db_v2_test.sql`

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

9. **Start the development server**
   ```bash
   php artisan serve
   ```

Visit `http://localhost:8000` to access the application.

## Development

### Code Structure

CoreHealth v2 follows Laravel best practices with additional patterns:

- **Services**: Business logic layer (`app/Services/`)
- **Repositories**: Data access abstraction
- **Observers**: Automated actions on model events (e.g., accounting entries)
- **Policies**: Authorization rules
- **Events**: Decoupled event handling

### Development Tools

Several utility scripts are available for development:

- `check_*.php` - Database and configuration validation scripts
- `debug_*.php` - Debugging tools for specific modules
- `test_*.php` - Manual testing scripts
- `trigger_*.php` - Event and observer testing

### Asset Compilation

```bash
# Development with hot reload
npm run watch

# Production build
npm run production
```

### Database Utilities

```bash
# Check database schema
php check_table.php

# Validate configurations
php check_banks.php
php check_status_enum.php
```

## Testing

CoreHealth v2 includes comprehensive testing coverage:

### Running Tests

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature

# Run with coverage
php artisan test --coverage
```

### Testing Documentation

See [Testing Checklist](docs/TESTING_CHECKLIST.md) for detailed testing procedures and [My Transactions Test Guide](docs/MY_TRANSACTIONS_TEST_GUIDE.md) for module-specific testing.

## Project Status

CoreHealth v2 is under active development. Current focus areas:

- ✅ Core patient management
- ✅ Basic billing and invoicing
- ✅ Pharmacy and inventory management
- 🚧 Advanced accounting features
- 🚧 Complete HMO integration
- 🚧 Enhanced reporting and analytics
- 📋 Mobile application development

## Contributing

We welcome contributions from the community! Please follow these guidelines:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

### Coding Standards

- Follow PSR-12 coding standards for PHP
- Use meaningful variable and function names
- Write comprehensive docblocks
- Include unit tests for new features
- Update documentation as needed

## Security

If you discover any security vulnerabilities, please email the security team immediately. Do not create public issues for security concerns.

## Support

For support and questions:
- Check the [documentation](docs/)
- Review existing issues
- Contact the development team

## License

CoreHealth v2 is proprietary software. All rights reserved.

---

**Built with Laravel** - The PHP Framework for Web Artisans
