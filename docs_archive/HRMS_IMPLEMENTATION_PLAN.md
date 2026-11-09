# Human Resources Management System (HRMS) Implementation Plan

> **Version:** 1.0  
> **Date:** January 23, 2026  
> **Status:** Planning Phase  
> **Estimated Effort:** 4-6 Weeks

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Current System Audit](#2-current-system-audit)
3. [System Architecture Overview](#3-system-architecture-overview)
4. [Database Schema Design](#4-database-schema-design)
5. [Models & Relationships](#5-models--relationships)
6. [Controllers & Services](#6-controllers--services)
7. [Routes Structure](#7-routes-structure)
8. [Views & UI Components](#8-views--ui-components)
9. [Permission System](#9-permission-system)
10. [Integration Points](#10-integration-points)
11. [Implementation Phases](#11-implementation-phases)
12. [Testing Strategy](#12-testing-strategy)

---

## 1. Executive Summary

### 1.1 Objective
Transform the existing basic Staff CRUD into a comprehensive Human Resources Management System (HRMS) that handles:
- Complete employee lifecycle management
- Leave management with configurable leave types
- Disciplinary actions (queries, suspensions, terminations)
- Payroll processing with configurable pay heads
- Employee Self-Service (ESS) portal
- Integration with existing Expense module for payroll disbursement

### 1.2 Key Features

| Module | Description |
|--------|-------------|
| **HR Workbench** | Administrative command center for HR operations |
| **Leave Management** | Leave types, applications, approvals, calendar |
| **Disciplinary Module** | Queries, suspensions (with login blocking), terminations |
| **Payroll Engine** | Pay heads, salary profiles, batch payroll, approvals |
| **Employee Self-Service** | Personal data, payslips, leave balance, disciplinary history |
| **Attachment System** | Polymorphic file attachments for all HR documents |

---

## 2. Current System Audit

### 2.1 Existing Staff Module

**Location:** `app/Models/Staff.php`

```php
// Current Staff Model Fields
- id
- user_id (FK → users.id)
- specialization_id (FK → specializations.id)
- clinic_id (FK → clinics.id)
- gender
- date_of_birth
- home_address
- phone_number
- consultation_fee
- is_unit_head (boolean)
- is_dept_head (boolean)
- status (integer)
- timestamps
```

**Relationships:**
- `belongsTo` User
- `belongsTo` Specialization
- `belongsTo` Clinic

**Current Routes:** `Route::resource('staff', StaffController::class)`

### 2.2 Existing User Model

**Location:** `app/Models/User.php`

```php
// Current User Model Fields
- id
- is_admin (FK → user_categories.id)
- filename (avatar)
- old_records
- surname, firstname, othername
- assignRole, assignPermission
- status
- email, password
- timestamps
```

**Key Relationships:**
- `hasOne` Staff (staff_profile)
- `hasOne` Patient (patient_profile)
- Uses Spatie `HasRoles` trait

### 2.3 Existing Expense Module

**Location:** `app/Models/Expense.php`

```php
// Expense Categories Already Includes:
const CATEGORY_SALARIES = 'salaries';

// Expense Statuses:
const STATUS_PENDING = 'pending';
const STATUS_APPROVED = 'approved';
const STATUS_REJECTED = 'rejected';
const STATUS_VOID = 'void';
```

**Integration Point:** Payroll approval will create expenses with `category = 'salaries'`

### 2.4 Gaps Identified

| Area | Current State | Required Enhancement |
|------|--------------|---------------------|
| Staff Fields | Basic info only | Employment details, bank info, salary profile |
| User Status | Simple integer | Suspension status with message |
| Leave | Not exists | Full leave management system |
| Discipline | Not exists | Query, suspension, termination modules |
| Payroll | Not exists | Pay heads, salary config, batch processing |
| Attachments | Not standardized | Polymorphic attachment system |

---

## 3. System Architecture Overview

### 3.1 Module Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           HRMS ARCHITECTURE                              │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌──────────────────┐    ┌──────────────────┐    ┌──────────────────┐  │
│  │   HR WORKBENCH   │    │  EMPLOYEE SELF   │    │    SIDEBAR       │  │
│  │   (HR Manager)   │    │  SERVICE (ESS)   │    │   NAVIGATION     │  │
│  └────────┬─────────┘    └────────┬─────────┘    └──────────────────┘  │
│           │                       │                                     │
│  ┌────────┴───────────────────────┴─────────────────────────────────┐  │
│  │                      CORE HR MODULES                              │  │
│  ├──────────────┬──────────────┬──────────────┬─────────────────────┤  │
│  │   STAFF      │    LEAVE     │ DISCIPLINARY │     PAYROLL         │  │
│  │  MANAGEMENT  │  MANAGEMENT  │   MODULE     │     ENGINE          │  │
│  │              │              │              │                     │  │
│  │ • CRUD       │ • Types      │ • Queries    │ • Pay Heads         │  │
│  │ • Profiles   │ • Requests   │ • Suspensions│ • Salary Profiles   │  │
│  │ • Bank Info  │ • Approvals  │ • Termination│ • Batch Generation  │  │
│  │ • Employment │ • Calendar   │ • Login Block│ • Approval Flow     │  │
│  │   Details    │ • Balance    │              │ • Expense Create    │  │
│  └──────────────┴──────────────┴──────────────┴─────────────────────┘  │
│                                   │                                     │
│  ┌────────────────────────────────┴─────────────────────────────────┐  │
│  │                    SHARED SERVICES                                │  │
│  ├──────────────────┬───────────────────┬───────────────────────────┤  │
│  │   ATTACHMENTS    │   NOTIFICATIONS   │   EXPENSE INTEGRATION     │  │
│  │   (Polymorphic)  │   (Events/Email)  │   (Payroll → Expense)     │  │
│  └──────────────────┴───────────────────┴───────────────────────────┘  │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

### 3.2 Data Flow - Payroll to Expense

```
┌──────────────┐    ┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│   HR Creates │───▶│   Payroll    │───▶│   Finance    │───▶│   Expense    │
│   Batch      │    │   Generated  │    │   Approves   │    │   Created    │
│   Payroll    │    │   (pending)  │    │   Payroll    │    │   (pending)  │
└──────────────┘    └──────────────┘    └──────────────┘    └──────────────┘
```

---

## 4. Database Schema Design

### 4.1 New Tables

#### 4.1.1 Staff Extensions (Migration: `add_hr_fields_to_staff_table`)

```php
Schema::table('staff', function (Blueprint $table) {
    // Employment Information
    $table->string('employee_id')->nullable()->unique()->after('id');
    $table->date('date_hired')->nullable();
    $table->date('date_confirmed')->nullable();
    $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'intern'])->default('full_time');
    $table->enum('employment_status', ['active', 'suspended', 'terminated', 'resigned'])->default('active');
    
    // Bank Information
    $table->string('bank_name')->nullable();
    $table->string('bank_account_number')->nullable();
    $table->string('bank_account_name')->nullable();
    
    // Emergency Contact
    $table->string('emergency_contact_name')->nullable();
    $table->string('emergency_contact_phone')->nullable();
    $table->string('emergency_contact_relationship')->nullable();
    
    // Tax & Pension
    $table->string('tax_id')->nullable();
    $table->string('pension_id')->nullable();
    
    // HR Notes
    $table->text('hr_notes')->nullable();
    
    // Suspension details (for login blocking)
    $table->timestamp('suspended_at')->nullable();
    $table->unsignedBigInteger('suspended_by')->nullable();
    $table->text('suspension_reason')->nullable();
    $table->date('suspension_end_date')->nullable();
    
    $table->foreign('suspended_by')->references('id')->on('users')->nullOnDelete();
});
```

#### 4.1.2 Leave Types Table (`leave_types`)

```php
Schema::create('leave_types', function (Blueprint $table) {
    $table->id();
    $table->string('name');                              // "Annual Leave", "Sick Leave", etc.
    $table->string('code')->unique();                    // "AL", "SL", "ML", etc.
    $table->text('description')->nullable();
    $table->integer('max_days_per_year')->default(0);    // 0 = unlimited
    $table->integer('max_consecutive_days')->default(0); // 0 = no limit
    $table->integer('max_requests_per_year')->default(0);// 0 = unlimited
    $table->integer('min_days_notice')->default(0);      // Days before leave starts
    $table->boolean('requires_attachment')->default(false);
    $table->boolean('is_paid')->default(true);
    $table->boolean('is_active')->default(true);
    $table->string('color')->default('#3498db');         // For calendar display
    $table->json('applicable_employment_types')->nullable(); // ["full_time", "contract"]
    $table->timestamps();
    $table->softDeletes();
});
```

#### 4.1.3 Leave Requests Table (`leave_requests`)

```php
Schema::create('leave_requests', function (Blueprint $table) {
    $table->id();
    $table->string('request_number')->unique();
    $table->unsignedBigInteger('staff_id');
    $table->unsignedBigInteger('leave_type_id');
    $table->date('start_date');
    $table->date('end_date');
    $table->integer('total_days');
    $table->text('reason')->nullable();
    $table->text('handover_notes')->nullable();
    $table->unsignedBigInteger('relief_staff_id')->nullable(); // Who covers
    
    // Status Flow: pending → supervisor_approved → approved/rejected → cancelled/recalled
    // Two-Level Approval:
    // 1. First Level: Unit Head (same department) OR Dept Head (same user category)
    // 2. Second Level: HR Manager (only after first level approved)
    $table->enum('status', [
        'pending',              // Initial state, awaiting first-level approval
        'supervisor_approved',  // First level (unit/dept head) approved, awaiting HR
        'approved',             // HR approved (final)
        'rejected',             // Rejected at any stage
        'cancelled',            // Cancelled by staff
        'recalled'              // Recalled after approval
    ])->default('pending');
    
    // First Level Approval (Unit Head / Dept Head)
    $table->unsignedBigInteger('supervisor_approved_by')->nullable();
    $table->timestamp('supervisor_approved_at')->nullable();
    $table->text('supervisor_comments')->nullable();
    
    // Second Level Approval (HR Manager)
    $table->unsignedBigInteger('hr_approved_by')->nullable();
    $table->timestamp('hr_approved_at')->nullable();
    $table->text('hr_comments')->nullable();
    
    // Legacy/combined fields for backward compatibility
    $table->unsignedBigInteger('reviewed_by')->nullable();
    $table->timestamp('reviewed_at')->nullable();
    $table->text('review_comments')->nullable();
    
    $table->timestamps();
    $table->softDeletes();
    
    $table->foreign('staff_id')->references('id')->on('staff')->cascadeOnDelete();
    $table->foreign('leave_type_id')->references('id')->on('leave_types');
    $table->foreign('relief_staff_id')->references('id')->on('staff')->nullOnDelete();
    $table->foreign('supervisor_approved_by')->references('id')->on('users')->nullOnDelete();
    $table->foreign('hr_approved_by')->references('id')->on('users')->nullOnDelete();
    $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
    
    $table->index(['staff_id', 'status']);
    $table->index(['start_date', 'end_date']);
});
```

#### 4.1.4 Leave Balances Table (`leave_balances`)

```php
Schema::create('leave_balances', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('staff_id');
    $table->unsignedBigInteger('leave_type_id');
    $table->integer('year');
    $table->decimal('entitled_days', 5, 1)->default(0);    // Total allocation
    $table->decimal('used_days', 5, 1)->default(0);        // Days taken
    $table->decimal('pending_days', 5, 1)->default(0);     // Pending approval
    $table->decimal('carried_forward', 5, 1)->default(0);  // From previous year
    $table->timestamps();
    
    $table->foreign('staff_id')->references('id')->on('staff')->cascadeOnDelete();
    $table->foreign('leave_type_id')->references('id')->on('leave_types');
    
    $table->unique(['staff_id', 'leave_type_id', 'year']);
});
```

#### 4.1.5 Disciplinary Queries Table (`disciplinary_queries`)

```php
Schema::create('disciplinary_queries', function (Blueprint $table) {
    $table->id();
    $table->string('query_number')->unique();
    $table->unsignedBigInteger('staff_id');
    $table->string('subject');
    $table->text('description');
    $table->enum('severity', ['minor', 'moderate', 'major', 'gross_misconduct'])->default('minor');
    $table->date('incident_date')->nullable();
    $table->text('expected_response')->nullable();
    $table->date('response_deadline');
    
    // Status: issued → response_received → reviewed → closed
    $table->enum('status', ['issued', 'response_received', 'under_review', 'closed'])->default('issued');
    
    // Staff Response
    $table->text('staff_response')->nullable();
    $table->timestamp('response_received_at')->nullable();
    
    // HR Decision
    $table->text('hr_decision')->nullable();
    $table->enum('outcome', ['warning', 'final_warning', 'suspension', 'termination', 'dismissed', 'no_action'])->nullable();
    $table->unsignedBigInteger('decided_by')->nullable();
    $table->timestamp('decided_at')->nullable();
    
    // Issuer
    $table->unsignedBigInteger('issued_by');
    $table->timestamps();
    $table->softDeletes();
    
    $table->foreign('staff_id')->references('id')->on('staff')->cascadeOnDelete();
    $table->foreign('issued_by')->references('id')->on('users');
    $table->foreign('decided_by')->references('id')->on('users')->nullOnDelete();
});
```

#### 4.1.6 Staff Suspensions Table (`staff_suspensions`)

```php
Schema::create('staff_suspensions', function (Blueprint $table) {
    $table->id();
    $table->string('suspension_number')->unique();
    $table->unsignedBigInteger('staff_id');
    $table->unsignedBigInteger('disciplinary_query_id')->nullable(); // Link to query if applicable
    $table->enum('type', ['paid', 'unpaid'])->default('unpaid');
    $table->date('start_date');
    $table->date('end_date')->nullable(); // Null = indefinite
    $table->text('reason');
    $table->text('suspension_message'); // Message shown to staff on login attempt
    
    $table->enum('status', ['active', 'lifted', 'expired'])->default('active');
    
    // Lifted early
    $table->unsignedBigInteger('lifted_by')->nullable();
    $table->timestamp('lifted_at')->nullable();
    $table->text('lift_reason')->nullable();
    
    // Issuer
    $table->unsignedBigInteger('issued_by');
    $table->timestamps();
    $table->softDeletes();
    
    $table->foreign('staff_id')->references('id')->on('staff')->cascadeOnDelete();
    $table->foreign('disciplinary_query_id')->references('id')->on('disciplinary_queries')->nullOnDelete();
    $table->foreign('issued_by')->references('id')->on('users');
    $table->foreign('lifted_by')->references('id')->on('users')->nullOnDelete();
    
    $table->index(['staff_id', 'status']);
});
```

#### 4.1.7 Staff Terminations Table (`staff_terminations`)

```php
Schema::create('staff_terminations', function (Blueprint $table) {
    $table->id();
    $table->string('termination_number')->unique();
    $table->unsignedBigInteger('staff_id');
    $table->unsignedBigInteger('disciplinary_query_id')->nullable();
    
    $table->enum('type', ['voluntary', 'involuntary', 'retirement', 'death', 'contract_end'])->default('voluntary');
    $table->enum('reason_category', [
        'resignation', 'misconduct', 'poor_performance', 'redundancy', 
        'retirement', 'medical', 'death', 'contract_expiry', 'other'
    ]);
    $table->text('reason_details');
    $table->date('notice_date');
    $table->date('effective_date');
    $table->date('last_working_day');
    
    // Exit Details
    $table->boolean('exit_interview_conducted')->default(false);
    $table->text('exit_interview_notes')->nullable();
    $table->boolean('clearance_completed')->default(false);
    $table->boolean('final_payment_processed')->default(false);
    
    $table->unsignedBigInteger('processed_by');
    $table->timestamps();
    $table->softDeletes();
    
    $table->foreign('staff_id')->references('id')->on('staff')->cascadeOnDelete();
    $table->foreign('disciplinary_query_id')->references('id')->on('disciplinary_queries')->nullOnDelete();
    $table->foreign('processed_by')->references('id')->on('users');
});
```

#### 4.1.8 Pay Heads Table (`pay_heads`)

```php
Schema::create('pay_heads', function (Blueprint $table) {
    $table->id();
    $table->string('name');                              // "Basic Salary", "Housing Allowance", "Tax", etc.
    $table->string('code')->unique();                    // "BASIC", "HOUSING", "TAX", "PENSION"
    $table->text('description')->nullable();
    $table->enum('type', ['addition', 'deduction']);     // Earnings vs Deductions
    $table->enum('calculation_type', ['fixed', 'percentage', 'formula'])->default('fixed');
    $table->string('calculation_base')->nullable();      // For percentage: "basic_salary", "gross_salary"
    $table->decimal('default_value', 15, 2)->default(0); // Default amount or percentage
    $table->boolean('is_taxable')->default(true);
    $table->boolean('is_mandatory')->default(false);     // Must be in every payroll
    $table->boolean('is_active')->default(true);
    $table->integer('sort_order')->default(0);
    $table->timestamps();
    $table->softDeletes();
});
```

#### 4.1.9 Salary Profiles Table (`staff_salary_profiles`)

```php
Schema::create('staff_salary_profiles', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('staff_id');
    $table->decimal('basic_salary', 15, 2)->default(0);
    $table->enum('pay_frequency', ['monthly', 'bi_weekly', 'weekly'])->default('monthly');
    $table->date('effective_from');
    $table->date('effective_to')->nullable(); // Null = current profile
    $table->boolean('is_active')->default(true);
    $table->text('notes')->nullable();
    $table->unsignedBigInteger('created_by');
    $table->timestamps();
    $table->softDeletes();
    
    $table->foreign('staff_id')->references('id')->on('staff')->cascadeOnDelete();
    $table->foreign('created_by')->references('id')->on('users');
    
    $table->index(['staff_id', 'is_active']);
});
```

#### 4.1.10 Salary Profile Items Table (`staff_salary_profile_items`)

```php
Schema::create('staff_salary_profile_items', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('salary_profile_id');
    $table->unsignedBigInteger('pay_head_id');
    $table->enum('calculation_type', ['fixed', 'percentage', 'formula'])->default('fixed');
    $table->string('calculation_base')->nullable();
    $table->decimal('value', 15, 4)->default(0); // Amount or percentage value
    $table->timestamps();
    
    $table->foreign('salary_profile_id')->references('id')->on('staff_salary_profiles')->cascadeOnDelete();
    $table->foreign('pay_head_id')->references('id')->on('pay_heads');
    
    $table->unique(['salary_profile_id', 'pay_head_id']);
});
```

#### 4.1.11 Payroll Batches Table (`payroll_batches`)

```php
Schema::create('payroll_batches', function (Blueprint $table) {
    $table->id();
    $table->string('batch_number')->unique();
    $table->string('name');                              // "January 2026 Payroll"
    $table->date('pay_period_start');
    $table->date('pay_period_end');
    $table->date('payment_date');
    $table->integer('total_staff')->default(0);
    $table->decimal('total_gross', 15, 2)->default(0);
    $table->decimal('total_additions', 15, 2)->default(0);
    $table->decimal('total_deductions', 15, 2)->default(0);
    $table->decimal('total_net', 15, 2)->default(0);
    
    // Status: draft → submitted → approved → paid / rejected
    $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'paid'])->default('draft');
    
    // Workflow
    $table->unsignedBigInteger('created_by');
    $table->unsignedBigInteger('submitted_by')->nullable();
    $table->timestamp('submitted_at')->nullable();
    $table->unsignedBigInteger('approved_by')->nullable();
    $table->timestamp('approved_at')->nullable();
    $table->text('approval_comments')->nullable();
    $table->unsignedBigInteger('rejected_by')->nullable();
    $table->timestamp('rejected_at')->nullable();
    $table->text('rejection_reason')->nullable();
    
    // Expense Link
    $table->unsignedBigInteger('expense_id')->nullable(); // Created on approval
    
    $table->timestamps();
    $table->softDeletes();
    
    $table->foreign('created_by')->references('id')->on('users');
    $table->foreign('submitted_by')->references('id')->on('users')->nullOnDelete();
    $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
    $table->foreign('rejected_by')->references('id')->on('users')->nullOnDelete();
    $table->foreign('expense_id')->references('id')->on('expenses')->nullOnDelete();
});
```

#### 4.1.12 Payroll Items Table (`payroll_items`)

```php
Schema::create('payroll_items', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('payroll_batch_id');
    $table->unsignedBigInteger('staff_id');
    $table->unsignedBigInteger('salary_profile_id');
    
    // Summary
    $table->decimal('basic_salary', 15, 2)->default(0);
    $table->decimal('gross_salary', 15, 2)->default(0);
    $table->decimal('total_additions', 15, 2)->default(0);
    $table->decimal('total_deductions', 15, 2)->default(0);
    $table->decimal('net_salary', 15, 2)->default(0);
    
    // Bank Details (snapshot at time of payroll)
    $table->string('bank_name')->nullable();
    $table->string('bank_account_number')->nullable();
    $table->string('bank_account_name')->nullable();
    
    $table->timestamps();
    
    $table->foreign('payroll_batch_id')->references('id')->on('payroll_batches')->cascadeOnDelete();
    $table->foreign('staff_id')->references('id')->on('staff');
    $table->foreign('salary_profile_id')->references('id')->on('staff_salary_profiles');
    
    $table->unique(['payroll_batch_id', 'staff_id']);
});
```

#### 4.1.13 Payroll Item Details Table (`payroll_item_details`)

```php
Schema::create('payroll_item_details', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('payroll_item_id');
    $table->unsignedBigInteger('pay_head_id');
    $table->enum('type', ['addition', 'deduction']);
    $table->string('pay_head_name');
    $table->decimal('amount', 15, 2)->default(0);
    $table->timestamps();
    
    $table->foreign('payroll_item_id')->references('id')->on('payroll_items')->cascadeOnDelete();
    $table->foreign('pay_head_id')->references('id')->on('pay_heads');
});
```

#### 4.1.14 HR Attachments Table (`hr_attachments`) - Polymorphic

```php
Schema::create('hr_attachments', function (Blueprint $table) {
    $table->id();
    $table->morphs('attachable');                        // attachable_type, attachable_id
    $table->string('filename');
    $table->string('original_filename');
    $table->string('file_path');
    $table->string('mime_type');
    $table->unsignedBigInteger('file_size');
    $table->string('document_type')->nullable();         // 'medical_report', 'query_response', 'termination_letter'
    $table->text('description')->nullable();
    $table->unsignedBigInteger('uploaded_by');
    $table->timestamps();
    $table->softDeletes();
    
    $table->foreign('uploaded_by')->references('id')->on('users');
    
    $table->index(['attachable_type', 'attachable_id']);
});
```

### 4.2 Table Relationships Diagram

```
                                    ┌─────────────┐
                                    │    users    │
                                    └──────┬──────┘
                                           │ 1:1
                                    ┌──────┴──────┐
                                    │    staff    │
                                    └──────┬──────┘
                    ┌──────────┬──────────┼──────────┬──────────┬──────────┐
                    │          │          │          │          │          │
            ┌───────┴───────┐  │  ┌───────┴───────┐  │  ┌───────┴───────┐  │
            │ leave_requests│  │  │disciplinary_  │  │  │  staff_       │  │
            │               │  │  │   queries     │  │  │salary_profiles│  │
            └───────┬───────┘  │  └───────┬───────┘  │  └───────┬───────┘  │
                    │          │          │          │          │          │
            ┌───────┴───────┐  │  ┌───────┴───────┐  │  ┌───────┴───────┐  │
            │ leave_types   │  │  │staff_         │  │  │staff_salary_  │  │
            │               │  │  │suspensions    │  │  │profile_items  │  │
            └───────────────┘  │  └───────────────┘  │  └───────┬───────┘  │
                               │                     │          │          │
            ┌──────────────────┘  ┌──────────────────┘  ┌───────┴───────┐  │
            │                     │                     │   pay_heads   │  │
    ┌───────┴───────┐     ┌───────┴───────┐            └───────────────┘  │
    │leave_balances │     │staff_         │                               │
    │               │     │terminations   │     ┌─────────────────────────┘
    └───────────────┘     └───────────────┘     │
                                          ┌─────┴─────────┐
                                          │payroll_batches│
                                          └───────┬───────┘
                                                  │
                                          ┌───────┴───────┐
                                          │ payroll_items │
                                          └───────┬───────┘
                                                  │
                                          ┌───────┴───────┐
                                          │payroll_item_  │
                                          │   details     │
                                          └───────────────┘
                    
                    ┌───────────────────────────────────────────────┐
                    │              hr_attachments                   │
                    │  (polymorphic: leave_requests, queries,       │
                    │   suspensions, terminations, payroll_batches) │
                    └───────────────────────────────────────────────┘
```

---

## 5. Models & Relationships

### 5.1 New Models to Create

```
app/Models/HR/
├── LeaveType.php
├── LeaveRequest.php
├── LeaveBalance.php
├── DisciplinaryQuery.php
├── StaffSuspension.php
├── StaffTermination.php
├── PayHead.php
├── StaffSalaryProfile.php
├── StaffSalaryProfileItem.php
├── PayrollBatch.php
├── PayrollItem.php
├── PayrollItemDetail.php
└── HrAttachment.php
```

### 5.2 Model Code Examples

#### LeaveType Model

```php
<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class LeaveType extends Model implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'name', 'code', 'description', 'max_days_per_year',
        'max_consecutive_days', 'max_requests_per_year', 'min_days_notice',
        'requires_attachment', 'is_paid', 'is_active', 'color',
        'applicable_employment_types'
    ];

    protected $casts = [
        'requires_attachment' => 'boolean',
        'is_paid' => 'boolean',
        'is_active' => 'boolean',
        'applicable_employment_types' => 'array',
    ];

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function leaveBalances()
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
```

#### LeaveRequest Model

```php
<?php

namespace App\Models\HR;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class LeaveRequest extends Model implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'request_number', 'staff_id', 'leave_type_id', 'start_date', 'end_date',
        'total_days', 'reason', 'handover_notes', 'relief_staff_id', 'status',
        'reviewed_by', 'reviewed_at', 'review_comments'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_RECALLED = 'recalled';

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->request_number)) {
                $model->request_number = self::generateRequestNumber();
            }
        });
    }

    public static function generateRequestNumber(): string
    {
        $prefix = 'LR';
        $year = date('Y');
        $lastRequest = self::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();
        
        $sequence = $lastRequest ? (int) substr($lastRequest->request_number, -6) + 1 : 1;
        return $prefix . $year . str_pad($sequence, 6, '0', STR_PAD_LEFT);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function reliefStaff()
    {
        return $this->belongsTo(Staff::class, 'relief_staff_id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function attachments()
    {
        return $this->morphMany(HrAttachment::class, 'attachable');
    }
}
```

#### PayrollBatch Model

```php
<?php

namespace App\Models\HR;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class PayrollBatch extends Model implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'batch_number', 'name', 'pay_period_start', 'pay_period_end',
        'payment_date', 'total_staff', 'total_gross', 'total_additions',
        'total_deductions', 'total_net', 'status', 'created_by',
        'submitted_by', 'submitted_at', 'approved_by', 'approved_at',
        'approval_comments', 'rejected_by', 'rejected_at', 'rejection_reason',
        'expense_id'
    ];

    protected $casts = [
        'pay_period_start' => 'date',
        'pay_period_end' => 'date',
        'payment_date' => 'date',
        'total_gross' => 'decimal:2',
        'total_additions' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'total_net' => 'decimal:2',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    const STATUS_DRAFT = 'draft';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_PAID = 'paid';

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->batch_number)) {
                $model->batch_number = self::generateBatchNumber();
            }
        });
    }

    public static function generateBatchNumber(): string
    {
        $prefix = 'PAY';
        $yearMonth = date('Ym');
        $lastBatch = self::where('batch_number', 'like', $prefix . $yearMonth . '%')
            ->orderBy('id', 'desc')
            ->first();
        
        $sequence = $lastBatch ? (int) substr($lastBatch->batch_number, -4) + 1 : 1;
        return $prefix . $yearMonth . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    public function items()
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function attachments()
    {
        return $this->morphMany(HrAttachment::class, 'attachable');
    }

    /**
     * Recalculate totals from items
     */
    public function recalculateTotals()
    {
        $this->total_staff = $this->items()->count();
        $this->total_gross = $this->items()->sum('gross_salary');
        $this->total_additions = $this->items()->sum('total_additions');
        $this->total_deductions = $this->items()->sum('total_deductions');
        $this->total_net = $this->items()->sum('net_salary');
        $this->save();
    }
}
```

### 5.3 Staff Model Extensions

```php
// Add to existing Staff model

// New relationships
public function salaryProfiles()
{
    return $this->hasMany(\App\Models\HR\StaffSalaryProfile::class);
}

public function currentSalaryProfile()
{
    return $this->hasOne(\App\Models\HR\StaffSalaryProfile::class)
        ->where('is_active', true)
        ->latest();
}

public function leaveRequests()
{
    return $this->hasMany(\App\Models\HR\LeaveRequest::class);
}

public function leaveBalances()
{
    return $this->hasMany(\App\Models\HR\LeaveBalance::class);
}

public function disciplinaryQueries()
{
    return $this->hasMany(\App\Models\HR\DisciplinaryQuery::class);
}

public function suspensions()
{
    return $this->hasMany(\App\Models\HR\StaffSuspension::class);
}

public function activeSuspension()
{
    return $this->hasOne(\App\Models\HR\StaffSuspension::class)
        ->where('status', 'active')
        ->where(function ($q) {
            $q->whereNull('end_date')
              ->orWhere('end_date', '>=', now());
        });
}

public function termination()
{
    return $this->hasOne(\App\Models\HR\StaffTermination::class);
}

public function payrollItems()
{
    return $this->hasMany(\App\Models\HR\PayrollItem::class);
}

// Scopes
public function scopeActive($query)
{
    return $query->where('employment_status', 'active');
}

public function scopeSuspended($query)
{
    return $query->where('employment_status', 'suspended');
}

public function scopeNotSuspended($query)
{
    return $query->where('employment_status', '!=', 'suspended');
}

// Helper methods
public function isSuspended(): bool
{
    return $this->employment_status === 'suspended' || $this->activeSuspension()->exists();
}

public function getSuspensionMessage(): ?string
{
    $suspension = $this->activeSuspension;
    return $suspension ? $suspension->suspension_message : null;
}
```

---

## 6. Controllers & Services

### 6.1 Controller Structure

```
app/Http/Controllers/HR/
├── HrWorkbenchController.php        # Dashboard & main workbench
├── LeaveTypeController.php          # Leave type CRUD
├── LeaveRequestController.php       # Leave request management
├── LeaveCalendarController.php      # Leave calendar view
├── DisciplinaryQueryController.php  # Query CRUD
├── StaffSuspensionController.php    # Suspension management
├── StaffTerminationController.php   # Termination processing
├── PayHeadController.php            # Pay head CRUD
├── SalaryProfileController.php      # Staff salary configuration
├── PayrollController.php            # Payroll batch management
└── EmployeeSelfServiceController.php # ESS portal
```

### 6.2 Service Classes

```
app/Services/HR/
├── LeaveService.php                 # Leave calculations, balance management
├── DisciplinaryService.php          # Query/suspension/termination workflows
├── PayrollService.php               # Payroll generation, calculations
└── HrAttachmentService.php          # File upload handling
```

### 6.3 Key Service Methods

#### LeaveService.php

```php
<?php

namespace App\Services\HR;

use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveBalance;
use App\Models\HR\LeaveType;
use App\Models\Staff;
use Carbon\Carbon;

class LeaveService
{
    /**
     * Calculate remaining leave balance for a staff member
     */
    public function getLeaveBalance(Staff $staff, LeaveType $leaveType, int $year = null): array
    {
        $year = $year ?? now()->year;
        
        $balance = LeaveBalance::firstOrCreate(
            [
                'staff_id' => $staff->id,
                'leave_type_id' => $leaveType->id,
                'year' => $year,
            ],
            [
                'entitled_days' => $leaveType->max_days_per_year,
                'used_days' => 0,
                'pending_days' => 0,
                'carried_forward' => 0,
            ]
        );

        return [
            'entitled' => $balance->entitled_days + $balance->carried_forward,
            'used' => $balance->used_days,
            'pending' => $balance->pending_days,
            'available' => ($balance->entitled_days + $balance->carried_forward) 
                          - $balance->used_days - $balance->pending_days,
        ];
    }

    /**
     * Validate leave request against constraints
     */
    public function validateLeaveRequest(Staff $staff, LeaveType $leaveType, Carbon $startDate, Carbon $endDate): array
    {
        $errors = [];
        $totalDays = $startDate->diffInDaysFiltered(function ($date) {
            return !$date->isWeekend(); // Exclude weekends
        }, $endDate) + 1;

        // Check max consecutive days
        if ($leaveType->max_consecutive_days > 0 && $totalDays > $leaveType->max_consecutive_days) {
            $errors[] = "Maximum consecutive days allowed is {$leaveType->max_consecutive_days}";
        }

        // Check notice period
        if ($leaveType->min_days_notice > 0) {
            $daysNotice = now()->diffInDays($startDate, false);
            if ($daysNotice < $leaveType->min_days_notice) {
                $errors[] = "Minimum {$leaveType->min_days_notice} days notice required";
            }
        }

        // Check annual frequency
        if ($leaveType->max_requests_per_year > 0) {
            $requestsThisYear = LeaveRequest::where('staff_id', $staff->id)
                ->where('leave_type_id', $leaveType->id)
                ->whereYear('created_at', now()->year)
                ->whereIn('status', ['pending', 'approved'])
                ->count();

            if ($requestsThisYear >= $leaveType->max_requests_per_year) {
                $errors[] = "Maximum {$leaveType->max_requests_per_year} requests per year exceeded";
            }
        }

        // Check balance
        $balance = $this->getLeaveBalance($staff, $leaveType);
        if ($totalDays > $balance['available']) {
            $errors[] = "Insufficient leave balance. Available: {$balance['available']} days";
        }

        return $errors;
    }

    /**
     * Approve leave request and update balance
     */
    public function approveLeaveRequest(LeaveRequest $request, int $approvedBy, string $comments = null): void
    {
        $request->status = LeaveRequest::STATUS_APPROVED;
        $request->reviewed_by = $approvedBy;
        $request->reviewed_at = now();
        $request->review_comments = $comments;
        $request->save();

        // Update balance
        $balance = LeaveBalance::where('staff_id', $request->staff_id)
            ->where('leave_type_id', $request->leave_type_id)
            ->where('year', $request->start_date->year)
            ->first();

        if ($balance) {
            $balance->pending_days -= $request->total_days;
            $balance->used_days += $request->total_days;
            $balance->save();
        }
    }
}
```

#### PayrollService.php

```php
<?php

namespace App\Services\HR;

use App\Models\HR\PayrollBatch;
use App\Models\HR\PayrollItem;
use App\Models\HR\PayrollItemDetail;
use App\Models\HR\PayHead;
use App\Models\HR\StaffSalaryProfile;
use App\Models\Expense;
use App\Models\Staff;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    /**
     * Generate payroll batch for selected staff
     */
    public function generatePayrollBatch(
        array $staffIds,
        string $periodStart,
        string $periodEnd,
        string $paymentDate,
        int $createdBy
    ): PayrollBatch {
        return DB::transaction(function () use ($staffIds, $periodStart, $periodEnd, $paymentDate, $createdBy) {
            // Create batch
            $batch = PayrollBatch::create([
                'name' => 'Payroll ' . date('F Y', strtotime($periodStart)),
                'pay_period_start' => $periodStart,
                'pay_period_end' => $periodEnd,
                'payment_date' => $paymentDate,
                'status' => PayrollBatch::STATUS_DRAFT,
                'created_by' => $createdBy,
            ]);

            // Process each staff member
            foreach ($staffIds as $staffId) {
                $this->addStaffToPayroll($batch, $staffId);
            }

            // Recalculate totals
            $batch->recalculateTotals();

            return $batch;
        });
    }

    /**
     * Add a staff member to payroll batch
     */
    public function addStaffToPayroll(PayrollBatch $batch, int $staffId): ?PayrollItem
    {
        $staff = Staff::with('currentSalaryProfile.items.payHead')->find($staffId);
        
        if (!$staff || !$staff->currentSalaryProfile) {
            return null;
        }

        $profile = $staff->currentSalaryProfile;
        $basicSalary = $profile->basic_salary;

        // Calculate additions and deductions
        $additions = 0;
        $deductions = 0;
        $itemDetails = [];

        // Add basic salary as first item
        $basicPayHead = PayHead::where('code', 'BASIC')->first();
        if ($basicPayHead) {
            $itemDetails[] = [
                'pay_head_id' => $basicPayHead->id,
                'type' => 'addition',
                'pay_head_name' => 'Basic Salary',
                'amount' => $basicSalary,
            ];
            $additions += $basicSalary;
        }

        // Process salary profile items
        foreach ($profile->items as $item) {
            $amount = $this->calculatePayHeadAmount($item, $basicSalary, $additions);
            
            $itemDetails[] = [
                'pay_head_id' => $item->pay_head_id,
                'type' => $item->payHead->type,
                'pay_head_name' => $item->payHead->name,
                'amount' => $amount,
            ];

            if ($item->payHead->type === 'addition') {
                $additions += $amount;
            } else {
                $deductions += $amount;
            }
        }

        $grossSalary = $additions;
        $netSalary = $grossSalary - $deductions;

        // Create payroll item
        $payrollItem = PayrollItem::create([
            'payroll_batch_id' => $batch->id,
            'staff_id' => $staffId,
            'salary_profile_id' => $profile->id,
            'basic_salary' => $basicSalary,
            'gross_salary' => $grossSalary,
            'total_additions' => $additions,
            'total_deductions' => $deductions,
            'net_salary' => $netSalary,
            'bank_name' => $staff->bank_name,
            'bank_account_number' => $staff->bank_account_number,
            'bank_account_name' => $staff->bank_account_name,
        ]);

        // Create item details
        foreach ($itemDetails as $detail) {
            PayrollItemDetail::create(array_merge($detail, [
                'payroll_item_id' => $payrollItem->id,
            ]));
        }

        return $payrollItem;
    }

    /**
     * Calculate pay head amount based on type
     */
    private function calculatePayHeadAmount($item, float $basicSalary, float $grossSalary): float
    {
        switch ($item->calculation_type) {
            case 'percentage':
                $base = $item->calculation_base === 'basic_salary' ? $basicSalary : $grossSalary;
                return round($base * ($item->value / 100), 2);
            
            case 'fixed':
            default:
                return $item->value;
        }
    }

    /**
     * Approve payroll batch and create expense
     */
    public function approvePayrollBatch(PayrollBatch $batch, int $approvedBy, string $comments = null): Expense
    {
        return DB::transaction(function () use ($batch, $approvedBy, $comments) {
            // Create expense entry
            $expense = Expense::create([
                'category' => Expense::CATEGORY_SALARIES,
                'title' => "Payroll: {$batch->name}",
                'description' => "Payroll batch {$batch->batch_number} for period {$batch->pay_period_start->format('d M Y')} to {$batch->pay_period_end->format('d M Y')}. Staff count: {$batch->total_staff}",
                'amount' => $batch->total_net,
                'expense_date' => $batch->payment_date,
                'reference_type' => PayrollBatch::class,
                'reference_id' => $batch->id,
                'status' => Expense::STATUS_PENDING,
                'recorded_by' => $approvedBy,
            ]);

            // Update batch status
            $batch->status = PayrollBatch::STATUS_APPROVED;
            $batch->approved_by = $approvedBy;
            $batch->approved_at = now();
            $batch->approval_comments = $comments;
            $batch->expense_id = $expense->id;
            $batch->save();

            return $expense;
        });
    }
}
```

---

## 7. Routes Structure

### 7.1 HR Routes File (`routes/hr.php`)

```php
<?php

use App\Http\Controllers\HR\HrWorkbenchController;
use App\Http\Controllers\HR\LeaveTypeController;
use App\Http\Controllers\HR\LeaveRequestController;
use App\Http\Controllers\HR\LeaveCalendarController;
use App\Http\Controllers\HR\DisciplinaryQueryController;
use App\Http\Controllers\HR\StaffSuspensionController;
use App\Http\Controllers\HR\StaffTerminationController;
use App\Http\Controllers\HR\PayHeadController;
use App\Http\Controllers\HR\SalaryProfileController;
use App\Http\Controllers\HR\PayrollController;
use App\Http\Controllers\HR\EmployeeSelfServiceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| HR Management Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->prefix('hr')->name('hr.')->group(function () {

    // =========================================
    // EMPLOYEE SELF SERVICE (All Employees)
    // =========================================
    Route::prefix('my-hr')->name('ess.')->group(function () {
        Route::get('/', [EmployeeSelfServiceController::class, 'index'])->name('index');
        Route::get('/salary', [EmployeeSelfServiceController::class, 'salary'])->name('salary');
        Route::get('/payslips', [EmployeeSelfServiceController::class, 'payslips'])->name('payslips');
        Route::get('/payslips/{payrollItem}', [EmployeeSelfServiceController::class, 'viewPayslip'])->name('payslip.view');
        Route::get('/payslips/{payrollItem}/download', [EmployeeSelfServiceController::class, 'downloadPayslip'])->name('payslip.download');
        
        // Leave Management
        Route::get('/leave', [EmployeeSelfServiceController::class, 'leave'])->name('leave');
        Route::get('/leave/apply', [EmployeeSelfServiceController::class, 'applyLeave'])->name('leave.apply');
        Route::post('/leave/apply', [EmployeeSelfServiceController::class, 'storeLeave'])->name('leave.store');
        Route::get('/leave/{leaveRequest}', [EmployeeSelfServiceController::class, 'viewLeave'])->name('leave.view');
        Route::post('/leave/{leaveRequest}/cancel', [EmployeeSelfServiceController::class, 'cancelLeave'])->name('leave.cancel');
        Route::get('/leave-calendar', [EmployeeSelfServiceController::class, 'leaveCalendar'])->name('leave.calendar');
        
        // Disciplinary History
        Route::get('/disciplinary', [EmployeeSelfServiceController::class, 'disciplinary'])->name('disciplinary');
        Route::get('/disciplinary/queries/{query}', [EmployeeSelfServiceController::class, 'viewQuery'])->name('query.view');
        Route::post('/disciplinary/queries/{query}/respond', [EmployeeSelfServiceController::class, 'respondToQuery'])->name('query.respond');
    });

    // =========================================
    // HR WORKBENCH (HR Manager Only)
    // =========================================
    Route::middleware(['role:SUPERADMIN|ADMIN|HR Manager'])->group(function () {
        
        // HR Workbench Dashboard
        Route::get('/workbench', [HrWorkbenchController::class, 'index'])->name('workbench');
        Route::get('/workbench/stats', [HrWorkbenchController::class, 'stats'])->name('workbench.stats');

        // Staff Management (existing, moved under HR)
        Route::resource('staff', \App\Http\Controllers\StaffController::class);
        Route::get('staff-list', [\App\Http\Controllers\StaffController::class, 'listStaff'])->name('staff.list');

        // ----- LEAVE MANAGEMENT -----
        Route::prefix('leave')->name('leave.')->group(function () {
            // Leave Types CRUD
            Route::resource('types', LeaveTypeController::class)->names([
                'index' => 'types.index',
                'create' => 'types.create',
                'store' => 'types.store',
                'show' => 'types.show',
                'edit' => 'types.edit',
                'update' => 'types.update',
                'destroy' => 'types.destroy',
            ]);

            // Leave Requests Management
            Route::get('requests', [LeaveRequestController::class, 'index'])->name('requests.index');
            Route::get('requests/{leaveRequest}', [LeaveRequestController::class, 'show'])->name('requests.show');
            Route::post('requests/{leaveRequest}/approve', [LeaveRequestController::class, 'approve'])->name('requests.approve');
            Route::post('requests/{leaveRequest}/reject', [LeaveRequestController::class, 'reject'])->name('requests.reject');
            Route::post('requests/{leaveRequest}/recall', [LeaveRequestController::class, 'recall'])->name('requests.recall');

            // Leave Calendar
            Route::get('calendar', [LeaveCalendarController::class, 'index'])->name('calendar');
            Route::get('calendar/events', [LeaveCalendarController::class, 'events'])->name('calendar.events');
        });

        // ----- DISCIPLINARY -----
        Route::prefix('disciplinary')->name('disciplinary.')->group(function () {
            // Queries
            Route::resource('queries', DisciplinaryQueryController::class);
            Route::post('queries/{query}/review', [DisciplinaryQueryController::class, 'review'])->name('queries.review');
            Route::post('queries/{query}/decide', [DisciplinaryQueryController::class, 'decide'])->name('queries.decide');

            // Suspensions
            Route::resource('suspensions', StaffSuspensionController::class);
            Route::post('suspensions/{suspension}/lift', [StaffSuspensionController::class, 'lift'])->name('suspensions.lift');

            // Terminations
            Route::resource('terminations', StaffTerminationController::class);
            Route::post('terminations/{termination}/complete-clearance', [StaffTerminationController::class, 'completeClearance'])->name('terminations.clearance');
        });

        // ----- PAYROLL -----
        Route::prefix('payroll')->name('payroll.')->group(function () {
            // Pay Heads CRUD
            Route::resource('pay-heads', PayHeadController::class)->names([
                'index' => 'pay-heads.index',
                'create' => 'pay-heads.create',
                'store' => 'pay-heads.store',
                'show' => 'pay-heads.show',
                'edit' => 'pay-heads.edit',
                'update' => 'pay-heads.update',
                'destroy' => 'pay-heads.destroy',
            ]);

            // Salary Profiles
            Route::get('salary-profiles', [SalaryProfileController::class, 'index'])->name('profiles.index');
            Route::get('salary-profiles/staff/{staff}', [SalaryProfileController::class, 'staffProfile'])->name('profiles.staff');
            Route::post('salary-profiles/staff/{staff}', [SalaryProfileController::class, 'saveStaffProfile'])->name('profiles.save');
            Route::get('salary-profiles/{profile}/history', [SalaryProfileController::class, 'history'])->name('profiles.history');

            // Payroll Batches
            Route::get('batches', [PayrollController::class, 'index'])->name('batches.index');
            Route::get('batches/create', [PayrollController::class, 'create'])->name('batches.create');
            Route::post('batches', [PayrollController::class, 'store'])->name('batches.store');
            Route::get('batches/{batch}', [PayrollController::class, 'show'])->name('batches.show');
            Route::post('batches/{batch}/submit', [PayrollController::class, 'submit'])->name('batches.submit');
            Route::post('batches/{batch}/approve', [PayrollController::class, 'approve'])->name('batches.approve');
            Route::post('batches/{batch}/reject', [PayrollController::class, 'reject'])->name('batches.reject');
            Route::delete('batches/{batch}', [PayrollController::class, 'destroy'])->name('batches.destroy');
            Route::get('batches/{batch}/print', [PayrollController::class, 'print'])->name('batches.print');
        });

        // ----- REPORTS -----
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('leave-summary', [HrWorkbenchController::class, 'leaveSummaryReport'])->name('leave-summary');
            Route::get('disciplinary', [HrWorkbenchController::class, 'disciplinaryReport'])->name('disciplinary');
            Route::get('payroll-history', [HrWorkbenchController::class, 'payrollHistoryReport'])->name('payroll-history');
            Route::get('staff-turnover', [HrWorkbenchController::class, 'staffTurnoverReport'])->name('staff-turnover');
        });

        // ----- ATTACHMENTS -----
        Route::post('attachments', [\App\Http\Controllers\HR\HrAttachmentController::class, 'store'])->name('attachments.store');
        Route::delete('attachments/{attachment}', [\App\Http\Controllers\HR\HrAttachmentController::class, 'destroy'])->name('attachments.destroy');
        Route::get('attachments/{attachment}/download', [\App\Http\Controllers\HR\HrAttachmentController::class, 'download'])->name('attachments.download');
    });
});
```

### 7.2 Register Routes in `RouteServiceProvider.php`

```php
// In boot() method, add:
Route::middleware('web')
    ->group(base_path('routes/hr.php'));
```

---

## 8. Views & UI Components

### 8.1 View Structure

```
resources/views/admin/hr/
├── workbench/
│   └── index.blade.php              # HR Dashboard/Workbench
├── leave/
│   ├── types/
│   │   ├── index.blade.php
│   │   ├── create.blade.php
│   │   └── edit.blade.php
│   ├── requests/
│   │   ├── index.blade.php
│   │   └── show.blade.php
│   └── calendar.blade.php
├── disciplinary/
│   ├── queries/
│   │   ├── index.blade.php
│   │   ├── create.blade.php
│   │   ├── show.blade.php
│   │   └── edit.blade.php
│   ├── suspensions/
│   │   ├── index.blade.php
│   │   ├── create.blade.php
│   │   └── show.blade.php
│   └── terminations/
│       ├── index.blade.php
│       ├── create.blade.php
│       └── show.blade.php
├── payroll/
│   ├── pay-heads/
│   │   ├── index.blade.php
│   │   ├── create.blade.php
│   │   └── edit.blade.php
│   ├── salary-profiles/
│   │   ├── index.blade.php
│   │   └── staff.blade.php
│   └── batches/
│       ├── index.blade.php
│       ├── create.blade.php
│       ├── show.blade.php
│       └── print.blade.php
├── ess/                             # Employee Self Service
│   ├── index.blade.php              # ESS Dashboard
│   ├── salary.blade.php             # View salary configuration
│   ├── payslips/
│   │   ├── index.blade.php
│   │   └── view.blade.php
│   ├── leave/
│   │   ├── index.blade.php
│   │   ├── apply.blade.php
│   │   ├── view.blade.php
│   │   └── calendar.blade.php
│   └── disciplinary/
│       ├── index.blade.php
│       └── query-response.blade.php
├── components/
│   ├── attachment-uploader.blade.php
│   ├── attachment-list.blade.php
│   └── leave-balance-card.blade.php
└── reports/
    ├── leave-summary.blade.php
    ├── disciplinary.blade.php
    └── payroll-history.blade.php
```

### 8.2 Sidebar Navigation Updates

```php
// Add to resources/views/admin/partials/sidebar.blade.php

{{-- ========================================
     GLOBAL HR INSIGHT (All Employees)
     After Requisitions and Messenger
     ======================================== --}}
<li class="nav-item {{ request()->routeIs('hr.ess.*') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('hr.ess.*') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-global-hr" data-bs-target="#sidebar-global-hr" aria-expanded="{{ request()->routeIs('hr.ess.*') ? 'true' : 'false' }}" aria-controls="sidebar-global-hr" id="sidebar-global-hr-toggle">
        <i class="mdi mdi-account-badge-outline menu-icon"></i>
        <span class="menu-title">My HR</span>
        <i class="mdi mdi-chevron-right menu-arrow"></i>
    </a>
    <div class="collapse {{ request()->routeIs('hr.ess.*') ? 'show' : '' }}" id="sidebar-global-hr">
        <ul class="nav flex-column sub-menu">
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.ess.index') ? 'active' : '' }}" href="{{ route('hr.ess.index') }}">
                    Overview
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.ess.salary') ? 'active' : '' }}" href="{{ route('hr.ess.salary') }}">
                    My Salary
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.ess.payslips*') ? 'active' : '' }}" href="{{ route('hr.ess.payslips') }}">
                    Payslips
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.ess.leave*') ? 'active' : '' }}" href="{{ route('hr.ess.leave') }}">
                    Leave
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.ess.disciplinary*') ? 'active' : '' }}" href="{{ route('hr.ess.disciplinary') }}">
                    Disciplinary
                </a>
            </li>
        </ul>
    </div>
</li>

{{-- ========================================
     HR MANAGEMENT SECTION (HR Manager Only)
     ======================================== --}}
@hasanyrole('SUPERADMIN|ADMIN|HR Manager')
<li class="pt-2 pb-1">
    <span class="nav-item-head">Human Resources</span>
</li>
<li class="nav-item {{ request()->routeIs('hr.workbench') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('hr.workbench') ? 'active' : '' }}" href="{{ route('hr.workbench') }}" id="sidebar-hr-workbench">
        <i class="mdi mdi-desktop-mac-dashboard menu-icon"></i>
        <span class="menu-title">HR Workbench</span>
    </a>
</li>
<li class="nav-item {{ request()->routeIs('hr.staff.*') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('hr.staff.*') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-hr-staff" data-bs-target="#sidebar-hr-staff" aria-expanded="{{ request()->routeIs('hr.staff.*') ? 'true' : 'false' }}" aria-controls="sidebar-hr-staff" id="sidebar-hr-staff-toggle">
        <i class="mdi mdi-account-group menu-icon"></i>
        <span class="menu-title">Staff Management</span>
        <i class="mdi mdi-chevron-right menu-arrow"></i>
    </a>
    <div class="collapse {{ request()->routeIs('hr.staff.*') ? 'show' : '' }}" id="sidebar-hr-staff">
        <ul class="nav flex-column sub-menu">
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.staff.index') ? 'active' : '' }}" href="{{ route('hr.staff.index') }}">
                    All Staff
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.staff.create') ? 'active' : '' }}" href="{{ route('hr.staff.create') }}">
                    Add Staff
                </a>
            </li>
        </ul>
    </div>
</li>
<li class="nav-item {{ request()->routeIs('hr.leave.*') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('hr.leave.*') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-hr-leave" data-bs-target="#sidebar-hr-leave" aria-expanded="{{ request()->routeIs('hr.leave.*') ? 'true' : 'false' }}" aria-controls="sidebar-hr-leave" id="sidebar-hr-leave-toggle">
        <i class="mdi mdi-calendar-clock menu-icon"></i>
        <span class="menu-title">Leave Management</span>
        <i class="mdi mdi-chevron-right menu-arrow"></i>
    </a>
    <div class="collapse {{ request()->routeIs('hr.leave.*') ? 'show' : '' }}" id="sidebar-hr-leave">
        <ul class="nav flex-column sub-menu">
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.leave.types.*') ? 'active' : '' }}" href="{{ route('hr.leave.types.index') }}">
                    Leave Types
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.leave.requests.*') ? 'active' : '' }}" href="{{ route('hr.leave.requests.index') }}">
                    Leave Requests
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.leave.calendar') ? 'active' : '' }}" href="{{ route('hr.leave.calendar') }}">
                    Leave Calendar
                </a>
            </li>
        </ul>
    </div>
</li>
<li class="nav-item {{ request()->routeIs('hr.disciplinary.*') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('hr.disciplinary.*') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-hr-disciplinary" data-bs-target="#sidebar-hr-disciplinary" aria-expanded="{{ request()->routeIs('hr.disciplinary.*') ? 'true' : 'false' }}" aria-controls="sidebar-hr-disciplinary" id="sidebar-hr-disciplinary-toggle">
        <i class="mdi mdi-alert-octagon menu-icon"></i>
        <span class="menu-title">Disciplinary</span>
        <i class="mdi mdi-chevron-right menu-arrow"></i>
    </a>
    <div class="collapse {{ request()->routeIs('hr.disciplinary.*') ? 'show' : '' }}" id="sidebar-hr-disciplinary">
        <ul class="nav flex-column sub-menu">
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.disciplinary.queries.*') ? 'active' : '' }}" href="{{ route('hr.disciplinary.queries.index') }}">
                    Queries
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.disciplinary.suspensions.*') ? 'active' : '' }}" href="{{ route('hr.disciplinary.suspensions.index') }}">
                    Suspensions
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.disciplinary.terminations.*') ? 'active' : '' }}" href="{{ route('hr.disciplinary.terminations.index') }}">
                    Terminations
                </a>
            </li>
        </ul>
    </div>
</li>
<li class="nav-item {{ request()->routeIs('hr.payroll.*') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('hr.payroll.*') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-hr-payroll" data-bs-target="#sidebar-hr-payroll" aria-expanded="{{ request()->routeIs('hr.payroll.*') ? 'true' : 'false' }}" aria-controls="sidebar-hr-payroll" id="sidebar-hr-payroll-toggle">
        <i class="mdi mdi-cash-multiple menu-icon"></i>
        <span class="menu-title">Payroll</span>
        <i class="mdi mdi-chevron-right menu-arrow"></i>
    </a>
    <div class="collapse {{ request()->routeIs('hr.payroll.*') ? 'show' : '' }}" id="sidebar-hr-payroll">
        <ul class="nav flex-column sub-menu">
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.payroll.pay-heads.*') ? 'active' : '' }}" href="{{ route('hr.payroll.pay-heads.index') }}">
                    Pay Heads
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.payroll.profiles.*') ? 'active' : '' }}" href="{{ route('hr.payroll.profiles.index') }}">
                    Salary Profiles
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.payroll.batches.*') ? 'active' : '' }}" href="{{ route('hr.payroll.batches.index') }}">
                    Payroll Batches
                </a>
            </li>
        </ul>
    </div>
</li>
<li class="nav-item {{ request()->routeIs('hr.reports.*') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('hr.reports.*') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-hr-reports" data-bs-target="#sidebar-hr-reports" aria-expanded="{{ request()->routeIs('hr.reports.*') ? 'true' : 'false' }}" aria-controls="sidebar-hr-reports" id="sidebar-hr-reports-toggle">
        <i class="mdi mdi-chart-bar menu-icon"></i>
        <span class="menu-title">HR Reports</span>
        <i class="mdi mdi-chevron-right menu-arrow"></i>
    </a>
    <div class="collapse {{ request()->routeIs('hr.reports.*') ? 'show' : '' }}" id="sidebar-hr-reports">
        <ul class="nav flex-column sub-menu">
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.reports.leave-summary') ? 'active' : '' }}" href="{{ route('hr.reports.leave-summary') }}">
                    Leave Summary
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.reports.disciplinary') ? 'active' : '' }}" href="{{ route('hr.reports.disciplinary') }}">
                    Disciplinary
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.reports.payroll-history') ? 'active' : '' }}" href="{{ route('hr.reports.payroll-history') }}">
                    Payroll History
                </a>
            </li>
        </ul>
    </div>
</li>
@endhasanyrole
```

---

## 9. Permission System

### 9.1 HR Permissions Seeder

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class HrPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Staff Management (existing, ensure they exist)
            'staff.view' => 'View staff records',
            'staff.create' => 'Create staff records',
            'staff.edit' => 'Edit staff records',
            'staff.delete' => 'Delete staff records',

            // Leave Management
            'leave-types.view' => 'View leave types',
            'leave-types.manage' => 'Manage leave types',
            'leave-requests.view' => 'View all leave requests',
            'leave-requests.approve' => 'Approve/reject leave requests',
            'leave-requests.recall' => 'Recall approved leave',

            // Disciplinary
            'disciplinary.view' => 'View disciplinary records',
            'disciplinary.create-query' => 'Issue disciplinary queries',
            'disciplinary.decide-query' => 'Make disciplinary decisions',
            'disciplinary.suspend' => 'Suspend staff members',
            'disciplinary.lift-suspension' => 'Lift staff suspensions',
            'disciplinary.terminate' => 'Process terminations',

            // Payroll
            'payroll.view' => 'View payroll records',
            'payroll.manage-pay-heads' => 'Manage pay heads',
            'payroll.manage-profiles' => 'Manage salary profiles',
            'payroll.generate' => 'Generate payroll batches',
            'payroll.submit' => 'Submit payroll for approval',
            'payroll.approve' => 'Approve/reject payroll batches',

            // HR Reports
            'hr-reports.view' => 'View HR reports',

            // ESS (for staff to view their own data)
            'ess.view-salary' => 'View own salary configuration',
            'ess.view-payslips' => 'View own payslips',
            'ess.apply-leave' => 'Apply for leave',
            'ess.view-disciplinary' => 'View own disciplinary history',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web']
            );
            $this->command->info("Created permission: {$name}");
        }

        // Create HR Manager Role
        $hrManagerRole = Role::firstOrCreate(['name' => 'HR Manager', 'guard_name' => 'web']);
        
        $hrPermissions = [
            'staff.view', 'staff.create', 'staff.edit', 'staff.delete',
            'leave-types.view', 'leave-types.manage',
            'leave-requests.view', 'leave-requests.approve', 'leave-requests.recall',
            'disciplinary.view', 'disciplinary.create-query', 'disciplinary.decide-query',
            'disciplinary.suspend', 'disciplinary.lift-suspension', 'disciplinary.terminate',
            'payroll.view', 'payroll.manage-pay-heads', 'payroll.manage-profiles',
            'payroll.generate', 'payroll.submit',
            'hr-reports.view',
        ];

        $hrManagerRole->syncPermissions($hrPermissions);
        $this->command->info('HR Manager role created with permissions');

        // Assign ESS permissions to all staff roles
        $staffRoles = ['STAFF', 'NURSE', 'DOCTOR', 'LAB_SCIENTIST', 'PHARMACIST', 'RECEPTIONIST'];
        $essPermissions = ['ess.view-salary', 'ess.view-payslips', 'ess.apply-leave', 'ess.view-disciplinary'];

        foreach ($staffRoles as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                foreach ($essPermissions as $perm) {
                    $role->givePermissionTo($perm);
                }
                $this->command->info("Added ESS permissions to {$roleName}");
            }
        }

        // Create Finance Approver role for payroll approval
        $financeRole = Role::firstOrCreate(['name' => 'Finance Manager', 'guard_name' => 'web']);
        $financeRole->givePermissionTo(['payroll.view', 'payroll.approve']);
        $this->command->info('Finance Manager role updated with payroll approval');
    }
}
```

---

## 10. Integration Points

### 10.1 Login Suspension Check

**Modify `app/Http/Controllers/Auth/LoginController.php`:**

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    protected $redirectTo = '/home';

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     * Override the authenticated method to check for suspension
     */
    protected function authenticated(Request $request, $user)
    {
        // Check if user has a staff profile and is suspended
        if ($user->staff_profile) {
            $staff = $user->staff_profile;
            
            // Check for active suspension
            if ($staff->isSuspended()) {
                auth()->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                $message = $staff->getSuspensionMessage() 
                    ?? 'Your account has been suspended. Please contact HR for more information.';

                return redirect()->route('login')
                    ->withErrors(['suspension' => $message])
                    ->with('suspension_alert', true);
            }

            // Check employment status
            if (in_array($staff->employment_status, ['terminated', 'resigned'])) {
                auth()->logout();
                $request->session()->invalidate();
                
                return redirect()->route('login')
                    ->withErrors(['account' => 'Your employment has been terminated. Access denied.']);
            }
        }

        return redirect()->intended($this->redirectPath());
    }
}
```

**Update Login View (`resources/views/auth/login.blade.php`):**

```blade
@if(session('suspension_alert'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="mdi mdi-alert-circle mr-2"></i>
    <strong>Account Suspended</strong>
    <p class="mb-0 mt-2">{{ $errors->first('suspension') }}</p>
</div>
@endif
```

### 10.2 Expense Module Integration

**Add to Expense Model (`app/Models/Expense.php`):**

```php
// Add new category constant
const CATEGORY_PAYROLL = 'payroll';

// Update getCategories()
public static function getCategories(): array
{
    return [
        self::CATEGORY_PURCHASE_ORDER => 'Purchase Order',
        self::CATEGORY_STORE_EXPENSE => 'Store Expense',
        self::CATEGORY_MAINTENANCE => 'Maintenance',
        self::CATEGORY_UTILITIES => 'Utilities',
        self::CATEGORY_SALARIES => 'Salaries',
        self::CATEGORY_PAYROLL => 'Payroll',  // Add this
        self::CATEGORY_OTHER => 'Other',
    ];
}

// Add relationship to payroll batch
public function payrollBatch()
{
    return $this->hasOne(\App\Models\HR\PayrollBatch::class, 'expense_id');
}
```

### 10.3 Middleware for Suspension Check

**Create `app/Http/Middleware/CheckSuspension.php`:**

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckSuspension
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check() && auth()->user()->staff_profile) {
            $staff = auth()->user()->staff_profile;
            
            if ($staff->isSuspended()) {
                auth()->logout();
                $request->session()->invalidate();
                
                return redirect()->route('login')
                    ->withErrors(['suspension' => $staff->getSuspensionMessage()]);
            }
        }

        return $next($request);
    }
}
```

**Register in `app/Http/Kernel.php`:**

```php
protected $middlewareGroups = [
    'web' => [
        // ... existing middleware
        \App\Http\Middleware\CheckSuspension::class,
    ],
];
```

---

## 11. Implementation Phases

### Phase 1: Foundation (Week 1)
| Task | Description | Priority |
|------|-------------|----------|
| 1.1 | Create all migrations | High |
| 1.2 | Create base models with relationships | High |
| 1.3 | Run HR permissions seeder | High |
| 1.4 | Extend Staff model with new fields | High |
| 1.5 | Create HR routes file | High |
| 1.6 | Add HR section to sidebar | Medium |

### Phase 2: Leave Management (Week 2)
| Task | Description | Priority |
|------|-------------|----------|
| 2.1 | LeaveType CRUD | High |
| 2.2 | LeaveService class | High |
| 2.3 | LeaveRequest workflow | High |
| 2.4 | Leave balance calculation | High |
| 2.5 | Leave calendar (FullCalendar.js) | Medium |
| 2.6 | ESS leave application | Medium |

### Phase 3: Disciplinary Module (Week 3)
| Task | Description | Priority |
|------|-------------|----------|
| 3.1 | DisciplinaryQuery CRUD | High |
| 3.2 | Staff response workflow | High |
| 3.3 | StaffSuspension CRUD | High |
| 3.4 | Login blocking mechanism | Critical |
| 3.5 | StaffTermination workflow | High |
| 3.6 | ESS disciplinary view | Medium |

### Phase 4: Payroll Engine (Week 4)
| Task | Description | Priority |
|------|-------------|----------|
| 4.1 | PayHead CRUD | High |
| 4.2 | Salary Profile management | High |
| 4.3 | PayrollService class | Critical |
| 4.4 | Batch payroll generation | Critical |
| 4.5 | Payroll approval workflow | Critical |
| 4.6 | Expense creation on approval | Critical |

### Phase 5: ESS Portal & Polish (Week 5)
| Task | Description | Priority |
|------|-------------|----------|
| 5.1 | ESS dashboard | High |
| 5.2 | Payslip view/download | High |
| 5.3 | Personal leave calendar | Medium |
| 5.4 | HR Workbench dashboard | Medium |
| 5.5 | HR Reports | Medium |
| 5.6 | Attachment system | Medium |

### Phase 6: Testing & Documentation (Week 6)
| Task | Description | Priority |
|------|-------------|----------|
| 6.1 | Unit tests for services | High |
| 6.2 | Feature tests for workflows | High |
| 6.3 | UAT testing | Critical |
| 6.4 | User documentation | Medium |
| 6.5 | Bug fixes | High |
| 6.6 | Performance optimization | Medium |

---

## 12. Testing Strategy

### 12.1 Unit Tests

```
tests/Unit/Services/HR/
├── LeaveServiceTest.php
├── PayrollServiceTest.php
└── DisciplinaryServiceTest.php
```

### 12.2 Feature Tests

```
tests/Feature/HR/
├── LeaveTypeManagementTest.php
├── LeaveRequestWorkflowTest.php
├── DisciplinaryQueryWorkflowTest.php
├── SuspensionLoginBlockTest.php
├── PayrollGenerationTest.php
├── PayrollApprovalTest.php
└── EssPortalTest.php
```

### 12.3 Key Test Scenarios

1. **Leave Management:**
   - Leave balance calculation accuracy
   - Max consecutive days validation
   - Annual frequency limit enforcement
   - Leave overlap detection

2. **Disciplinary:**
   - Query response deadline tracking
   - Suspension login blocking
   - Suspension expiry auto-lift
   - Termination workflow completion

3. **Payroll:**
   - Pay head calculations (fixed, percentage)
   - Batch total accuracy
   - Expense creation on approval
   - Duplicate payroll prevention

---

## Appendix A: Default Leave Types

| Code | Name | Max Days/Year | Max Consecutive | Paid | Requires Attachment |
|------|------|---------------|-----------------|------|---------------------|
| AL | Annual Leave | 21 | 14 | Yes | No |
| SL | Sick Leave | 10 | 5 | Yes | Yes (for >2 days) |
| ML | Maternity Leave | 90 | 90 | Yes | Yes |
| PL | Paternity Leave | 5 | 5 | Yes | Yes |
| CL | Compassionate Leave | 5 | 5 | Yes | No |
| SB | Study/Exam Leave | 10 | 5 | Yes | Yes |
| UL | Unpaid Leave | 30 | 30 | No | No |

## Appendix B: Default Pay Heads

| Code | Name | Type | Calculation | Taxable |
|------|------|------|-------------|---------|
| BASIC | Basic Salary | Addition | Fixed | Yes |
| HOUSING | Housing Allowance | Addition | Fixed | Yes |
| TRANSPORT | Transport Allowance | Addition | Fixed | No |
| MEAL | Meal Allowance | Addition | Fixed | No |
| UTILITY | Utility Allowance | Addition | Fixed | No |
| PAYE | Income Tax (PAYE) | Deduction | Percentage | - |
| PENSION_EE | Pension (Employee) | Deduction | 8% of Basic | - |
| PENSION_ER | Pension (Employer) | Addition | 10% of Basic | - |
| NHIS | Health Insurance | Deduction | Fixed | - |
| LOAN | Loan Repayment | Deduction | Fixed | - |

---

## Appendix C: Files to Create

### Migrations (14 files)
1. `xxxx_add_hr_fields_to_staff_table.php`
2. `xxxx_create_leave_types_table.php`
3. `xxxx_create_leave_requests_table.php`
4. `xxxx_create_leave_balances_table.php`
5. `xxxx_create_disciplinary_queries_table.php`
6. `xxxx_create_staff_suspensions_table.php`
7. `xxxx_create_staff_terminations_table.php`
8. `xxxx_create_pay_heads_table.php`
9. `xxxx_create_staff_salary_profiles_table.php`
10. `xxxx_create_staff_salary_profile_items_table.php`
11. `xxxx_create_payroll_batches_table.php`
12. `xxxx_create_payroll_items_table.php`
13. `xxxx_create_payroll_item_details_table.php`
14. `xxxx_create_hr_attachments_table.php`

### Models (13 files)
1. `app/Models/HR/LeaveType.php`
2. `app/Models/HR/LeaveRequest.php`
3. `app/Models/HR/LeaveBalance.php`
4. `app/Models/HR/DisciplinaryQuery.php`
5. `app/Models/HR/StaffSuspension.php`
6. `app/Models/HR/StaffTermination.php`
7. `app/Models/HR/PayHead.php`
8. `app/Models/HR/StaffSalaryProfile.php`
9. `app/Models/HR/StaffSalaryProfileItem.php`
10. `app/Models/HR/PayrollBatch.php`
11. `app/Models/HR/PayrollItem.php`
12. `app/Models/HR/PayrollItemDetail.php`
13. `app/Models/HR/HrAttachment.php`

### Controllers (12 files)
1. `app/Http/Controllers/HR/HrWorkbenchController.php`
2. `app/Http/Controllers/HR/LeaveTypeController.php`
3. `app/Http/Controllers/HR/LeaveRequestController.php`
4. `app/Http/Controllers/HR/LeaveCalendarController.php`
5. `app/Http/Controllers/HR/DisciplinaryQueryController.php`
6. `app/Http/Controllers/HR/StaffSuspensionController.php`
7. `app/Http/Controllers/HR/StaffTerminationController.php`
8. `app/Http/Controllers/HR/PayHeadController.php`
9. `app/Http/Controllers/HR/SalaryProfileController.php`
10. `app/Http/Controllers/HR/PayrollController.php`
11. `app/Http/Controllers/HR/EmployeeSelfServiceController.php`
12. `app/Http/Controllers/HR/HrAttachmentController.php`

### Services (4 files)
1. `app/Services/HR/LeaveService.php`
2. `app/Services/HR/DisciplinaryService.php`
3. `app/Services/HR/PayrollService.php`
4. `app/Services/HR/HrAttachmentService.php`

### Seeders (2 files)
1. `database/seeders/HrPermissionsSeeder.php`
2. `database/seeders/DefaultPayHeadsSeeder.php`

### Middleware (1 file)
1. `app/Http/Middleware/CheckSuspension.php`

### Routes (1 file)
1. `routes/hr.php`

### Views (~50 files)
See Section 8.1 for complete structure

---

**End of HRMS Implementation Plan**

*Document Version: 1.0*  
*Last Updated: January 23, 2026*
