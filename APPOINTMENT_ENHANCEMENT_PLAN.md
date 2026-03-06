# Appointment & Queue Enhancement Plan

> **Date:** 2026-03-02
> **Updated:** 2026-03-05
> **Priority:** HIGH — Core patient flow improvement
> **Scope:** Reception Workbench, Doctor Queue, Nurse Workbench, Encounter System
> **Overall Status:** Phases 1–5 COMPLETE. Phase 6 IN PROGRESS — observer-based email notifications, double-booking detection, auto no-show implemented.

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Current State Analysis](#2-current-state-analysis)
3. [Unified Status System](#3-unified-status-system)
4. [Database Schema](#4-database-schema)
5. [Appointment Calendar & Table View](#5-appointment-calendar--table-view)
   - 5A. [Follow-Up Visits](#5a-follow-up-visits)
   - 5B. [Doctor Reassignment](#5b-doctor-reassignment)
   - 5C. [Rescheduling (Enhanced)](#5c-rescheduling-enhanced)
   - 5D. [Specialist Referral System](#5d-specialist-referral-system)
6. [Reception Workbench — Appointment Management](#6-reception-workbench--appointment-management)
7. [Doctor Queue — Appointment & Schedule View](#7-doctor-queue--appointment--schedule-view)
8. [Nurse Workbench — Status Sync](#8-nurse-workbench--status-sync)
9. [Consultation Timer (Pause / Resume)](#9-consultation-timer-pause--resume)
10. [Status Sync Architecture](#10-status-sync-architecture)
11. [Implementation Phases](#11-implementation-phases)
12. [File-by-File Change Map](#12-file-by-file-change-map)
13. [UI Reference](#13-ui-reference)

---

## 1. Executive Summary

The current system is **walk-in only** — patients are booked directly into `DoctorQueue` with no future scheduling, no appointment calendar, and no consultation timer. Status integers are **split-brained** (reception sees 1-4, doctor sees 1-3), making cross-role visibility impossible.

This plan introduces:

- **Unified `DoctorAppointment` model** — a dedicated scheduling entity separate from the live queue
- **Calendar + Table dual-view** with toggle switch, filters, and role-scoped visibility
- **8-state unified status system** synced across reception, nurse, and doctor
- **Consultation timer** with pause/resume, tracked per-encounter in the database
- **Auto-conversion** from appointment → queue entry on patient arrival
- **Vitals as optional step** — doctors can start encounters directly; delivery/billing status is the real gate
- **Follow-up visits** tied to a parent appointment with inherited billing
- **Doctor reassignment** — change assigned doctor on appointments/follow-ups when the original isn't available
- **Specialist referral** — doctors refer from encounter; reception books in-hospital or marks as external referral
- **Rescheduling** — full reschedule chain with cancellation reasons and rebooking

---

## 2. Current State Analysis

### 2.1 What Exists

| Component | Location | State |
|---|---|---|
| **DoctorQueue model** | `app/Models/DoctorQueue.php` | 57 lines. Fillable: `patient_id, clinic_id, staff_id, receptionist_id, request_entry_id, status, vitals_taken, priority, source, triage_note` |
| **Reception booking** | `ReceptionWorkbenchController::bookConsultation()` L690-748 | Creates `ProductOrServiceRequest` + `DoctorQueue(status=1)`. No date/time field, no priority capture |
| **Doctor queue view** | `resources/views/admin/doctors/my_queues.blade.php` (330 lines) | 5 tabs: New, Continuing, Previous, My Admissions, Other Admissions. **"Scheduled" tab commented out at L64-78**. No auto-refresh. Date range filter only |
| **Nurse vitals queue** | `NursingWorkbenchController::getVitalsQueue()` L3386 | Filters `DoctorQueue` where `vitals_taken=0` + today. Card-based grid with clinic filter, 30s polling |
| **Reception queue** | `workbench.blade.php` L4098-4126 | Sidebar widget with Emergency/Waiting/Vitals/Consultation/Admitted counts. DataTable overlay with clinic filter |
| **FullCalendar** | `public/plugins/fullcalendar/` | v2.2.5 library exists but **never used** in any view |
| **Encounter view** | `new_encounter.blade.php` (4,475 lines) | 10 tabs, no timer UI, no consultation duration tracking |
| **Clinic model** | `app/Models/Clinic.php` | Minimal: only `{name}`, `doctors()` relationship. No hours, no slots |
| **Staff model** | `app/Models/Staff.php` (325 lines) | Has `clinic_id`, `specialization_id`, `consultation_fee`. No availability schedule |
| **Delivery status** | `HmoHelper::canDeliverService()` in `app/Helpers/HmoHelper.php` L126-180 | Determines if service can proceed: checks `payable_amount`+`payment_id` (paid?), `claims_amount`+`validation_status` (HMO validated?). Returns `{can_deliver, reason, hint}`. Used in all queue DataTables as `delivery_status` column (green "Ready" / red "Payment Required" / "HMO Validation Pending" / "HMO Validation Rejected") |
| **Vitals guard** | `EncounterController::create()` L1860-2020 | **No vitals guard exists.** Doctor can open encounter regardless of `vitals_taken`. Only `HmoHelper::canDeliverService()` gates the "Encounter" button |
| **Referral system** | `resources/views/admin/dashboards/tabs/doctor-tab.blade.php` L192-197 | Placeholder dashboard card behind `@if(Route::has('referrals.index'))` — route doesn't exist. **No referral model, controller, migration, or routes** |
| **Follow-up visits** | `AncVisit`, `PostnatalVisit` models | Only maternity modules have `next_appointment` date field. **No general follow-up scheduling mechanism** |

### 2.2 Status Split-Brain Bug

| Integer | Reception Meaning | Doctor Meaning |
|---|---|---|
| 1 | Waiting (queued) | Waiting (new) |
| 2 | Vitals Pending | Continuing encounter |
| 3 | In Consultation | Ended |
| 4 | Completed | *(not used)* |

**This must be fixed first.** The unified status enum (Section 3) replaces both interpretations.

### 2.3 Key Gaps

- ❌ No `DoctorAppointment` model or migration
- ❌ No `scheduled_date` / `appointment_time` on any table
- ❌ No calendar UI anywhere in the app
- ❌ No consultation timer / duration tracking
- ❌ No `consultation_started_at` / `consultation_ended_at` columns on `encounters` or `doctor_queues`
- ❌ No doctor availability / working hours
- ❌ No clinic operating schedule
- ❌ Status system is split-brained across controllers
- ❌ Doctor queue has no auto-refresh (only manual "Fetch Data")
- ❌ No appointment-to-queue conversion flow
- ❌ No specialist referral system (model, controller, routes all missing; dashboard placeholder exists behind dead Route::has check)
- ❌ No follow-up visit scheduling (only maternity `next_appointment` date field, no auto-booking)
- ❌ No doctor reassignment mechanism for appointments
- ❌ Vitals are optional but this isn't explicit in the status flow — `vitals_taken` is informational only
- ❌ Delivery status (`HmoHelper::canDeliverService`) gates encounters but isn't reflected in the appointment system

---

## 3. Unified Status System

### 3.1 Status Enum Definition

**File:** `app/Enums/QueueStatus.php` (new)

```php
<?php

namespace App\Enums;

class QueueStatus
{
    const CANCELLED     = 0;
    const WAITING       = 1;  // Queued, no vitals taken yet
    const VITALS_PENDING = 2; // Nurse picked up patient
    const READY         = 3;  // Vitals done, waiting for doctor
    const IN_CONSULTATION = 4; // Doctor started encounter
    const COMPLETED     = 5;  // Encounter finalized
    const SCHEDULED     = 6;  // Future appointment (not yet in queue)
    const NO_SHOW       = 7;  // Patient didn't arrive

    const LABELS = [
        0 => 'Cancelled',
        1 => 'Waiting',
        2 => 'Vitals Pending',
        3 => 'Ready',
        4 => 'In Consultation',
        5 => 'Completed',
        6 => 'Scheduled',
        7 => 'No-Show',
    ];

    const BADGE_CLASSES = [
        0 => 'bg-secondary',
        1 => 'bg-warning text-dark',
        2 => 'bg-info text-white',
        3 => 'bg-primary',
        4 => 'bg-success',
        5 => 'bg-dark',
        6 => 'bg-purple',
        7 => 'bg-danger',
    ];

    const COLORS = [
        0 => '#6c757d', // grey
        1 => '#ffc107', // yellow
        2 => '#17a2b8', // cyan
        3 => '#0d6efd', // blue
        4 => '#198754', // green
        5 => '#212529', // dark
        6 => '#6f42c1', // purple
        7 => '#dc3545', // red
    ];

    /**
     * Statuses that represent "active" queue entries (visible in live queue).
     */
    const ACTIVE = [1, 2, 3, 4];

    /**
     * Statuses that represent terminal/resolved states.
     */
    const TERMINAL = [0, 5, 7];

    public static function label(int $status): string
    {
        return self::LABELS[$status] ?? 'Unknown';
    }

    public static function badge(int $status): string
    {
        $label = self::label($status);
        $class = self::BADGE_CLASSES[$status] ?? 'bg-secondary';
        return "<span class='badge {$class}'>{$label}</span>";
    }

    public static function color(int $status): string
    {
        return self::COLORS[$status] ?? '#6c757d';
    }
}
```

### 3.2 Status Flow Diagram

```
┌──────────┐   Reception books    ┌──────────┐   Nurse picks up   ┌───────────────┐
│ SCHEDULED │ ──── arrival ──────→ │ WAITING  │ ────────────────→  │ VITALS_PENDING│
│    (6)    │     (future→today)   │   (1)    │                    │      (2)      │
└──────────┘                       └──────────┘                    └───────────────┘
     │                                  │  │                               │
     │ no-show                          │  │ doctor skips vitals           │ vitals done
     ▼                                  │  │ (vitals optional)             ▼
┌──────────┐                       ┌────│──│──┐                    ┌──────────┐
│ NO-SHOW  │                       │CANC│LL│D │                    │  READY   │
│   (7)    │                       │   (0)    │                    │   (3)    │
└──────────┘                       └──────────┘                    └──────────┘
                                                                        │
                              doctor opens encounter (from 1 OR 3)      │
                              ──────────────────────────────────────────→│
                                                                        ▼
                                            ┌─ Delivery Gate ──────────────────────┐
                                            │ HmoHelper::canDeliverService()       │
                                            │ ✅ Ready → proceed                   │
                                            │ ❌ Payment Required → button disabled│
                                            │ ❌ HMO Validation Pending → blocked  │
                                            │ ❌ HMO Validation Rejected → blocked │
                                            └──────────────────────────────────────┘
                                                          │ ✅ passes
                                                          ▼
                                                 ┌─────────────────┐
                                                 │ IN CONSULTATION  │
                                                 │       (4)        │
                                                 └─────────────────┘
                                                    │           │
                                            finalize│           │continue
                                                    ▼           ▼
                                              ┌──────────┐  (stays 4,
                                              │COMPLETED │   new cycle)
                                              │   (5)    │
                                              └──────────┘
                                                    │
                                              doctor schedules
                                              follow-up? ────→ Creates new DoctorAppointment
                                                               (type='follow_up',
                                                                parent_appointment_id=X,
                                                                inherits service_request_id
                                                                if pre-paid)
```

> **Key: Vitals are optional.** The doctor can open an encounter from status 1 (Waiting) or 3 (Ready). The `vitals_taken` flag is informational — the only hard gate is `HmoHelper::canDeliverService()` which checks payment/HMO validation. This matches the current codebase behavior where `EncounterController::create()` never checks `vitals_taken`.

### 3.3 Who Can Transition What

| Transition | Role(s) | Trigger | Delivery Gate? |
|---|---|---|---|
| `6 → 1` (Scheduled → Waiting) | Reception | Patient arrives, "Check-in" action | No — billing created at check-in |
| `6 → 7` (Scheduled → No-Show) | Reception, System | Manual mark or auto after cutoff time | No |
| `6 → 0` (Scheduled → Cancelled) | Reception, Patient (future) | Cancel appointment | No |
| `1 → 2` (Waiting → Vitals Pending) | Nurse | Opens vitals form for patient | No |
| `2 → 3` (Vitals Pending → Ready) | Nurse, System | Vitals saved → `vitals_taken = true` | No |
| `1 → 4` (Waiting → In Consultation) | Doctor | Opens encounter **skipping vitals** (vitals optional) | **Yes** — `HmoHelper::canDeliverService()` must return `can_deliver=true` |
| `3 → 4` (Ready → In Consultation) | Doctor | Opens encounter after vitals | **Yes** — same delivery gate |
| `4 → 5` (In Consultation → Completed) | Doctor | Finalizes encounter | No |
| `4 → 4` (In Consultation → continuing) | Doctor | Saves encounter without finalizing (continuing cycle) | No |
| `* → 0` (Any → Cancelled) | Reception, Admin | Cancel queue entry | No |
| `5 → 6` (Completed → Scheduled) | Doctor, Reception | Doctor schedules follow-up during/after encounter | No — uses parent billing if prepaid |

### 3.4 Vitals-Optional Behavior

Vitals are **informational, not gatekeeping**. Current behavior (preserved):

| Scenario | Nurse Queue | Doctor Queue | Can Start Encounter? |
|---|---|---|---|
| Patient queued, no vitals | Shows in vitals queue | Shows in "New" tab with `vitals_taken=false` | **Yes** — delivery gate is the only blocker |
| Nurse records vitals | Removed from vitals queue | Shows in "New" tab with `vitals_taken=true`, badge changes to "Ready" | **Yes** |
| Payment not made | Shows in vitals queue | Shows in "New" tab but "Encounter" button is **disabled** with "Payment Required" tooltip | **No** |
| HMO not validated | Shows in vitals queue | Shows in "New" tab but "Encounter" button is **disabled** with "HMO Validation Pending" tooltip | **No** |

The `delivery_status` column (already rendered via `HmoHelper::canDeliverService()`) is carried into the appointment system:
- **Calendar view:** Appointment card gets a small icon overlay: ✅ (ready), 💳 (payment required), 🏥 (HMO pending)
- **Table view:** Dedicated "Delivery" column with same badge as current queue DataTable
- **Context menu:** "Start Visit" action is only available when `can_deliver = true`

### 3.4 Migration Strategy for Existing Data

Existing `doctor_queues.status` values must be mapped:

```sql
-- Old reception meaning → new unified
-- 1 (waiting)         → 1 (WAITING)       — no change
-- 2 (vitals_pending)  → 2 (VITALS_PENDING) — no change  
-- 3 (in_consultation) → 4 (IN_CONSULTATION) — shift +1
-- 4 (completed)       → 5 (COMPLETED)       — shift +1

UPDATE doctor_queues SET status = 5 WHERE status = 4; -- completed first (avoid collision)
UPDATE doctor_queues SET status = 4 WHERE status = 3; -- in_consultation
-- status 1 and 2 remain unchanged
```

---

## 4. Database Schema

### 4.1 New Table: `doctor_appointments`

**Migration:** `database/migrations/YYYY_MM_DD_create_doctor_appointments_table.php`

```php
Schema::create('doctor_appointments', function (Blueprint $table) {
    $table->id();
    
    // Core relationships
    $table->foreignId('patient_id')->constrained('patients');
    $table->foreignId('clinic_id')->constrained('clinics');
    $table->foreignId('staff_id')->nullable()->constrained('staff')->comment('Assigned doctor (null = any available)');
    $table->foreignId('booked_by')->constrained('staff')->comment('Receptionist / self-service');
    
    // Schedule
    $table->date('appointment_date')->index();
    $table->time('start_time');
    $table->time('end_time')->nullable()->comment('Calculated from slot duration');
    $table->unsignedSmallInteger('duration_minutes')->default(15);
    
    // Status lifecycle
    $table->unsignedTinyInteger('status')->default(6)->comment('Uses QueueStatus enum: 6=Scheduled');
    $table->string('priority', 20)->default('routine')->comment('routine|urgent|emergency');
    $table->string('source', 30)->default('reception')->comment('reception|phone|online|referral|follow_up');
    $table->string('appointment_type', 30)->default('consultation')->comment('consultation|follow_up|procedure|review');
    
    // Notes
    $table->text('reason')->nullable()->comment('Reason for visit');
    $table->text('notes')->nullable()->comment('Internal notes (receptionist)');
    $table->text('cancellation_reason')->nullable();
    
    // Queue integration
    $table->foreignId('doctor_queue_id')->nullable()->comment('Set when appointment converts to live queue entry');
    $table->foreignId('service_request_id')->nullable()->comment('ProductOrServiceRequest created at check-in');
    
    // Follow-up linkage
    $table->foreignId('parent_appointment_id')->nullable()->comment('Self-ref: the original appointment this follow-up belongs to');
    $table->boolean('is_prepaid_followup')->default(false)->comment('True if billing is inherited from parent appointment');
    
    // Referral linkage
    $table->foreignId('referral_id')->nullable()->comment('Links to specialist_referrals table if booked from a referral');
    
    // Rescheduling
    $table->foreignId('rescheduled_from_id')->nullable()->comment('Self-ref: original appointment if rescheduled');
    $table->unsignedSmallInteger('reschedule_count')->default(0)->comment('How many times this appointment chain has been rescheduled');
    
    // Doctor reassignment
    $table->foreignId('original_staff_id')->nullable()->comment('Original doctor before reassignment (null = never changed)');
    $table->text('reassignment_reason')->nullable()->comment('Why the doctor was changed');
    $table->timestamp('reassigned_at')->nullable();
    
    $table->timestamp('checked_in_at')->nullable();
    $table->timestamp('cancelled_at')->nullable();
    $table->timestamp('no_show_marked_at')->nullable();
    
    $table->timestamps();
    $table->softDeletes();
    
    // Indexes
    $table->index(['appointment_date', 'clinic_id']);
    $table->index(['appointment_date', 'staff_id']);
    $table->index(['patient_id', 'appointment_date']);
    $table->index('status');
});
```

### 4.2 New Table: `clinic_schedules`

**Migration:** `database/migrations/YYYY_MM_DD_create_clinic_schedules_table.php`

```php
Schema::create('clinic_schedules', function (Blueprint $table) {
    $table->id();
    $table->foreignId('clinic_id')->constrained('clinics');
    $table->unsignedTinyInteger('day_of_week')->comment('0=Sun, 1=Mon ... 6=Sat');
    $table->time('open_time');
    $table->time('close_time');
    $table->unsignedSmallInteger('slot_duration_minutes')->default(15);
    $table->unsignedSmallInteger('max_concurrent_slots')->default(1)->comment('How many appointments per slot');
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    
    $table->unique(['clinic_id', 'day_of_week']);
});
```

### 4.3 New Table: `doctor_availabilities`

**Migration:** `database/migrations/YYYY_MM_DD_create_doctor_availabilities_table.php`

```php
Schema::create('doctor_availabilities', function (Blueprint $table) {
    $table->id();
    $table->foreignId('staff_id')->constrained('staff');
    $table->foreignId('clinic_id')->constrained('clinics');
    $table->unsignedTinyInteger('day_of_week');
    $table->time('start_time');
    $table->time('end_time');
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    
    $table->index(['staff_id', 'day_of_week']);
    $table->index(['clinic_id', 'day_of_week']);
});
```

### 4.4 New Table: `doctor_availability_overrides`

For holidays, leave days, or one-off schedule changes.

```php
Schema::create('doctor_availability_overrides', function (Blueprint $table) {
    $table->id();
    $table->foreignId('staff_id')->constrained('staff');
    $table->date('override_date');
    $table->boolean('is_available')->default(false)->comment('false = blocked off, true = extra availability');
    $table->time('start_time')->nullable();
    $table->time('end_time')->nullable();
    $table->string('reason')->nullable();
    $table->timestamps();
    
    $table->unique(['staff_id', 'override_date']);
});
```

### 4.5 New Table: `specialist_referrals`

**Migration:** `database/migrations/YYYY_MM_DD_create_specialist_referrals_table.php`

A referral is created by a doctor during an encounter and actioned by reception.

```php
Schema::create('specialist_referrals', function (Blueprint $table) {
    $table->id();
    
    // Who is being referred
    $table->foreignId('patient_id')->constrained('patients');
    $table->foreignId('encounter_id')->constrained('encounters')->comment('The encounter that generated this referral');
    
    // Referring doctor
    $table->foreignId('referring_doctor_id')->constrained('staff');
    $table->foreignId('referring_clinic_id')->constrained('clinics');
    
    // Referral target
    $table->enum('referral_type', ['internal', 'external'])->default('internal')
          ->comment('internal = in-hospital specialist, external = outside hospital');
    $table->foreignId('target_clinic_id')->nullable()->constrained('clinics')
          ->comment('For internal: the specialist clinic');
    $table->foreignId('target_doctor_id')->nullable()->constrained('staff')
          ->comment('For internal: specific specialist (null = any in target clinic)');
    $table->string('external_facility_name')->nullable()->comment('For external referrals');
    $table->string('external_doctor_name')->nullable();
    $table->string('external_facility_address')->nullable();
    $table->string('external_facility_phone')->nullable();
    
    // Specialization
    $table->foreignId('target_specialization_id')->nullable()->constrained('specializations')
          ->comment('The specialization being referred to');
    
    // Clinical details
    $table->text('reason')->comment('Why the referral is being made');
    $table->text('clinical_summary')->nullable()->comment('Summary of findings for the specialist');
    $table->text('provisional_diagnosis')->nullable();
    $table->enum('urgency', ['routine', 'urgent', 'emergency'])->default('routine');
    
    // Status lifecycle
    $table->enum('status', [
        'pending',      // Doctor created, waiting for reception action
        'booked',       // Reception created an appointment for internal referral
        'referred_out', // Reception marked as referred externally
        'completed',    // Specialist consultation happened
        'declined',     // Specialist/facility declined
        'cancelled',    // Cancelled by reception or doctor
    ])->default('pending');
    
    // Reception action tracking
    $table->foreignId('actioned_by')->nullable()->constrained('staff')->comment('Receptionist who processed the referral');
    $table->timestamp('actioned_at')->nullable();
    $table->text('action_notes')->nullable();
    
    // Links
    $table->foreignId('appointment_id')->nullable()->comment('DoctorAppointment created for internal referral');
    $table->foreignId('referral_letter_attachment_id')->nullable()->comment('Uploaded referral letter for external');
    
    $table->timestamps();
    $table->softDeletes();
    
    $table->index(['patient_id', 'status']);
    $table->index(['referring_doctor_id', 'created_at']);
    $table->index('status');
});
```

### 4.7 Alter Table: `doctor_queues`

```php
// Add columns for timer tracking & appointment link
Schema::table('doctor_queues', function (Blueprint $table) {
    $table->foreignId('appointment_id')->nullable()->after('request_entry_id')
          ->comment('Links back to doctor_appointments if this queue entry came from an appointment');
    $table->timestamp('consultation_started_at')->nullable()->after('triage_note');
    $table->timestamp('consultation_ended_at')->nullable()->after('consultation_started_at');
    $table->unsignedInteger('consultation_paused_seconds')->default(0)
          ->after('consultation_ended_at')
          ->comment('Accumulated pause time in seconds');
    $table->timestamp('last_paused_at')->nullable()->after('consultation_paused_seconds');
    $table->timestamp('last_resumed_at')->nullable()->after('last_paused_at');
    $table->boolean('is_paused')->default(false)->after('last_resumed_at');
});
```

### 4.8 Alter Table: `encounters`

```php
Schema::table('encounters', function (Blueprint $table) {
    $table->foreignId('queue_id')->nullable()->after('patient_id')
          ->comment('Direct link to DoctorQueue entry');
    $table->timestamp('started_at')->nullable()->after('completed')
          ->comment('When doctor first opened encounter');
    $table->timestamp('completed_at')->nullable()->after('started_at')
          ->comment('When encounter was finalized');
});
```

---

## 5. Appointment Calendar & Table View

### 5.1 Shared Component: `appointment-scheduler`

A reusable Blade component used by **reception**, **doctor**, and optionally **nurse** views.

**File:** `resources/views/components/appointment-scheduler.blade.php`

#### 5.1.1 Layout Structure

```
┌─────────────────────────────────────────────────────────────────────────┐
│ ┌─── Header Bar ──────────────────────────────────────────────────────┐ │
│ │ [Calendar ◉ | Table ○]  [< Prev] TODAY [Next >]  April 2026 📅     │ │
│ │                                                [+ NEW APPOINTMENT]  │ │
│ └─────────────────────────────────────────────────────────────────────┘ │
│ ┌─── Filter Bar ──────────────────────────────────────────────────────┐ │
│ │ Doctor: [All ▾]  Clinic: [All ▾]  Status: [All ▾]  Type: [All ▾]  │ │
│ │ Priority: [All ▾]  Search: [🔍 patient name / file no...]         │ │
│ └─────────────────────────────────────────────────────────────────────┘ │
│ ┌─── Status Legend ───────────────────────────────────────────────────┐ │
│ │ ● Scheduled  ● Waiting  ● Vitals Pending  ● Ready                 │ │
│ │ ● In Consultation  ● Completed  ● Cancelled  ● No-Show            │ │
│ └─────────────────────────────────────────────────────────────────────┘ │
│                                                                         │
│ ┌─── Calendar View ─── OR ─── Table View ────────────────────────────┐ │
│ │                                                                     │ │
│ │  (See 5.2 and 5.3 below)                                           │ │
│ │                                                                     │ │
│ └─────────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────┘
```

#### 5.1.2 View Toggle Switch

A Bootstrap toggle switch at the top-left:

```html
<div class="btn-group" role="group">
    <input type="radio" class="btn-check" name="appointment-view" id="view-calendar" checked>
    <label class="btn btn-outline-primary btn-sm" for="view-calendar">
        <i class="mdi mdi-calendar-month"></i> Calendar
    </label>
    <input type="radio" class="btn-check" name="appointment-view" id="view-table">
    <label class="btn btn-outline-primary btn-sm" for="view-table">
        <i class="mdi mdi-table"></i> Table
    </label>
</div>
```

Toggling shows/hides `#calendar-container` vs `#table-container`. Both share the same filter bar — changing filters updates whichever view is active.

#### 5.1.3 Filter Bar (Shared)

| Filter | Type | Reception | Doctor | Notes |
|---|---|---|---|---|
| **Doctor** | `<select>` (Select2) | All doctors | Pre-filled to self, can select others in same clinic | Populated from `/reception/clinics/{id}/doctors` |
| **Clinic** | `<select>` (Select2) | All clinics | Pre-filled to own clinic, can see others | Populated from `/reception/clinics` |
| **Status** | `<select>` multi-checkbox | All 8 statuses | Default: Scheduled, Waiting, Ready, In Consultation | Uses `QueueStatus::LABELS` |
| **Type** | `<select>` | All types | All types | consultation, follow_up, procedure, review |
| **Priority** | `<select>` | All  | All | routine, urgent, emergency |
| **Patient Search** | Text input | ✅ | ✅ | Debounced 300ms, searches name + file_no |
| **Date Range** | Date picker(s) | ✅ | ✅ | For table view; calendar uses its own navigation |

### 5.2 Calendar View

**Library:** FullCalendar v2.2.5 (already at `public/plugins/fullcalendar/`). Upgrade to **FullCalendar v6** (CDN or npm) is recommended for better resource/timeline views, but v2.2.5 can work for MVP.

#### 5.2.1 Calendar Rendering

Inspired by the attached LinkHMS image — a **weekly grid** with:

- **Y-axis:** Days of the week (day name + date)
- **X-axis:** Time slots (configurable: 15min / 30min / 1hr intervals)
- **Cells:** Appointment cards color-coded by status
- **Each card shows:** Patient name, age, phone, time — click to open context menu

#### 5.2.2 Calendar Event Format

```js
// Each appointment maps to a FullCalendar event:
{
    id: appointment.id,
    title: `${appointment.patient_name}, ${appointment.patient_age}`,
    start: `${appointment.appointment_date}T${appointment.start_time}`,
    end: `${appointment.appointment_date}T${appointment.end_time}`,
    color: QueueStatus.COLORS[appointment.status],
    extendedProps: {
        patient_id: appointment.patient_id,
        file_no: appointment.file_no,
        phone: appointment.phone,
        doctor: appointment.doctor_name,
        clinic: appointment.clinic_name,
        status: appointment.status,
        statusLabel: appointment.status_label,
        priority: appointment.priority,
        type: appointment.appointment_type,
        queue_id: appointment.doctor_queue_id,
    }
}
```

#### 5.2.3 Calendar Context Menu (on event click)

Matching the attached LinkHMS image, clicking an appointment card opens a dropdown with:

| Action | Condition | Effect |
|---|---|---|
| ▶ **Start Visit** | status = Waiting or Ready (1, 3) | Opens encounter (doctor) / Sends to queue (reception) |
| ◉ **Patient Confirmed** | status = Scheduled (6) | Updates status to Confirmed (visual only, stays 6) |
| ◉ **Patient Arrived / Check-In** | status = Scheduled (6) | Converts to queue entry, status → 1 (Waiting) |
| ● **Complete Visit** | status = In Consultation (4) | Finalize encounter, status → 5 |
| 📋 **View Patient Profile** | always | Opens patient in new tab |
| ✏ **Edit** | status = Scheduled (6) | Opens edit modal (date, time, doctor, notes) |
| ❌ **Cancel** | status ∈ {1, 2, 3, 6} | Opens cancel reason modal, status → 0 |
| 🚫 **Mark No-Show** | status = Scheduled (6) | status → 7 |
| 🔄 **Reschedule** | status ∈ {0, 6, 7} | Opens reschedule modal (creates new appointment, links via `rescheduled_from_id`) |
| 👨‍⚕️ **Change Doctor** | status ∈ {1, 2, 3, 6} | Opens reassignment modal (see Section 5B) |
| 📅 **Schedule Follow-Up** | status = Completed (5) | Opens follow-up booking modal (see Section 5A) |
| 🏥 **Refer to Specialist** | status = In Consultation (4) or Completed (5) | Opens specialist referral form (see Section 5D) |

#### 5.2.4 Calendar Navigation

- **Today** button — jumps to current week/day
- **< / >** arrows — navigate weeks (week view) or months (month view)
- **Month picker** dropdown (mini calendar in top-right, as shown in the attached image)
- **View modes:** Day / Week / Month toggle buttons

### 5.3 Table View

A server-side DataTable with all appointment data:

| # | Column | Sortable | Searchable |
|---|---|---|---|
| 1 | `#` (row index) | ❌ | ❌ |
| 2 | Patient Name | ✅ | ✅ |
| 3 | File No | ✅ | ✅ |
| 4 | Phone | ❌ | ✅ |
| 5 | Clinic | ✅ | ✅ |
| 6 | Doctor | ✅ | ✅ |
| 7 | Date | ✅ | ❌ |
| 8 | Time | ✅ | ❌ |
| 9 | Type | ✅ | ❌ |
| 10 | Priority | ✅ | ❌ |
| 11 | Delivery | ✅ | ❌ | **`HmoHelper::canDeliverService()` badge — same as current doctor queue.** Shows: "Ready" (green), "Payment Required" (red), "HMO Validation Pending" (orange), "HMO Validation Rejected" (red) |
| 12 | Status | ✅ | ❌ (filter only) |
| 13 | Actions | ❌ | ❌ |

**Actions column** mirrors the calendar context menu: Check-in, Cancel, No-Show, Edit, Reschedule, Change Doctor, View Profile.

---

## 5A. Follow-Up Visits

### 5A.1 Concept

A **follow-up visit** is a new appointment linked to a previously completed encounter/appointment. Follow-ups can be:

1. **Pre-paid** — the original consultation service covers the follow-up (no new billing at check-in)
2. **New billing** — a fresh `ProductOrServiceRequest` is created at check-in (standard flow)

The doctor schedules the follow-up during or after completing an encounter. Reception can also create follow-ups manually.

### 5A.2 Follow-Up Scheduling Flow

```
Doctor finalizes encounter
         │
         ▼
"Schedule Follow-Up" button on encounter screen
         │
         ▼
┌─────────────────────────────────────────────────┐
│ 📋 Schedule Follow-Up                            │
│                                                   │
│ Date:        [2026-03-15     📅]                 │
│ Time:        [10:00 ▾]                           │
│ Doctor:      [Dr. Smith (self) ▾]                │
│ Clinic:      [General Clinic ▾]                  │
│                                                   │
│ Billing:                                          │
│   (●) This follow-up is covered by the           │
│       original consultation (pre-paid)            │
│   ( ) New billing at check-in                     │
│                                                   │
│ Follow-up reason: [________________________]     │
│                                                   │
│ [ 📅 Schedule Follow-Up ]                        │
└─────────────────────────────────────────────────┘
```

**Creates:** `DoctorAppointment` with:
- `appointment_type = 'follow_up'`
- `parent_appointment_id = original_appointment.id`
- `is_prepaid_followup = true/false`
- `service_request_id = original.service_request_id` (if pre-paid)
- `source = 'follow_up'`

### 5A.3 Pre-Paid Follow-Up Check-In

When a pre-paid follow-up is checked in:

1. **No new `ProductOrServiceRequest`** is created — it shares the parent's
2. A `DoctorQueue` entry is created with `source='appointment'`
3. The delivery gate (`HmoHelper::canDeliverService()`) checks the **parent's** service request
4. If the parent was HMO and already validated, the follow-up passes automatically

When a standard follow-up is checked in:
- Normal flow — new `ProductOrServiceRequest` is created, payment/HMO validation required

### 5A.4 Follow-Up Chain Visibility

On the appointment calendar/table, follow-ups are visually linked:

- **Calendar:** Follow-up cards have a small 🔗 chain icon and a tooltip showing "Follow-up from [date] with Dr. [name]"
- **Table:** A "Parent" column (hidden by default, toggleable) with a link to the original appointment
- **Patient's appointment history:** Shows the full chain (original → follow-up 1 → follow-up 2 → ...)

### 5A.5 Follow-Up API Endpoints

| Method | Route | Controller Method | Purpose |
|---|---|---|---|
| `POST` | `/encounters/{encounter}/schedule-followup` | `scheduleFollowUp` | Doctor creates follow-up from encounter |
| `POST` | `/reception/appointments/{id}/create-followup` | `createFollowUp` | Reception creates follow-up for existing appointment |
| `GET` | `/appointments/{id}/chain` | `getAppointmentChain` | Get full chain (parent + all follow-ups) |

---

## 5B. Doctor Reassignment

### 5B.1 Concept

When the assigned doctor is unavailable (sick, on leave, schedule conflict), reception or the doctor themselves can reassign the appointment to a different doctor. The original doctor is preserved in `original_staff_id` for audit.

### 5B.2 Reassignment Rules

| Rule | Detail |
|---|---|
| **Who can reassign** | Reception (any appointment), Doctor (only their own, to a colleague in the same clinic) |
| **When** | Only status = `6` (Scheduled). Once checked-in, the queue entry's doctor can be changed by reception |
| **Target doctor** | Must be in the same clinic (default) or any clinic (reception override) |
| **Availability check** | Target doctor's availability is checked for the appointment slot |
| **Audit** | `original_staff_id`, `reassignment_reason`, `reassigned_at` are recorded |
| **Notification** | Both original and new doctor should see a notice (via queue refresh / future notification) |

### 5B.3 Reassignment UI

**In Calendar/Table context menu:** A new action "Change Doctor":

```
┌─────────────────────────────────────────────────┐
│ 🔄 Reassign Doctor                               │
│                                                   │
│ Current: Dr. Smith (General Clinic)              │
│                                                   │
│ New Doctor: [Dr. Johnson ▾]                      │
│ (filtered to same clinic, showing available only) │
│                                                   │
│ Reason: [Doctor on leave              ▾]         │
│   Options: Doctor on leave | Doctor unavailable  │
│            | Patient request | Schedule conflict │
│            | Other: [________________]            │
│                                                   │
│ [ 🔄 Reassign ]  [ Cancel ]                     │
└─────────────────────────────────────────────────┘
```

### 5B.4 Reassignment API

| Method | Route | Controller Method | Purpose |
|---|---|---|---|
| `POST` | `/appointments/{id}/reassign` | `reassignDoctor` | Change doctor on appointment |
| `GET` | `/appointments/{id}/available-doctors` | `getAvailableDoctors` | Get doctors available for this slot |

---

## 5C. Rescheduling (Enhanced)

### 5C.1 Concept

Rescheduling creates a **new appointment linked to the original** — it does not modify the original in-place. This preserves audit history and allows tracking how many times an appointment was rescheduled.

### 5C.2 Reschedule Rules

| Rule | Detail |
|---|---|
| **Who can reschedule** | Reception (any), Doctor (their own) |
| **Allowed statuses** | `6` (Scheduled), `0` (Cancelled), `7` (No-Show) |
| **Max reschedules** | Configurable via `appsettings('max_reschedule_count', 3)` — prevents infinite rebooking |
| **Billing** | If the original had `is_prepaid_followup=true`, the rescheduled copy inherits it |
| **Original status** | Set to `0` (Cancelled) with `cancellation_reason = 'Rescheduled to [new_date]'` |

### 5C.3 Reschedule UI

```
┌─────────────────────────────────────────────────┐
│ 🔄 Reschedule Appointment                        │
│                                                   │
│ Original: 2026-03-05 at 09:00 with Dr. Smith    │
│ Reschedule #: 1 of 3 allowed                    │
│                                                   │
│ New Date:  [2026-03-10     📅]                   │
│ New Time:  [10:30 ▾]                             │
│ Doctor:    [Dr. Smith (same) ▾]                  │
│ Reason:    [Patient requested          ▾]        │
│   Options: Patient requested | Doctor schedule   │
│            | Emergency | Other                    │
│                                                   │
│ [ 🔄 Reschedule ]  [ Cancel ]                   │
└─────────────────────────────────────────────────┘
```

### 5C.4 Reschedule Chain Tracking

Each rescheduled appointment stores `rescheduled_from_id` (the appointment it replaced) and inherits `reschedule_count + 1`. The full reschedule chain can be queried:

```php
// Get full reschedule history for an appointment
$chain = DoctorAppointment::where('id', $appointment->id)
    ->orWhere('rescheduled_from_id', $appointment->id)
    ->orWhere('rescheduled_from_id', $appointment->rescheduled_from_id)
    ->orderBy('created_at')
    ->get();
```

---

## 5D. Specialist Referral System

### 5D.1 Concept

Doctors need to refer patients to specialists during an encounter. The referral is recorded and appears on reception's workbench for action:

- **Internal referral:** Reception books an appointment in the specialist clinic (in-hospital)
- **External referral:** Reception marks it as referred externally and optionally uploads a referral letter

### 5D.2 Referral Flow

```
Doctor during encounter
         │
         ▼
"Refer to Specialist" button (new tab/section in encounter view)
         │
         ▼
┌─────────────────────────────────────────────────┐
│ 🏥 Refer to Specialist                           │
│                                                   │
│ Type: (●) In-Hospital  ( ) External              │
│                                                   │
│ ┌─ In-Hospital ──────────────────────────────┐   │
│ │ Specialization: [Cardiology        ▾]      │   │
│ │ Clinic:         [Cardiology Clinic ▾]      │   │
│ │ Doctor:         [Any Available     ▾]      │   │
│ └────────────────────────────────────────────┘   │
│                                                   │
│ ┌─ External (shown when selected) ───────────┐   │
│ │ Facility:  [Lagos University Hospital    ]  │   │
│ │ Doctor:    [Dr. Okoro                    ]  │   │
│ │ Address:   [________________________     ]  │   │
│ │ Phone:     [+234-xxx-xxxx               ]  │   │
│ └────────────────────────────────────────────┘   │
│                                                   │
│ Urgency:  [Routine ▾]                           │
│ Reason:   [_____________________________________]│
│ Clinical Summary: [_____________________________]│
│ Diagnosis:        [_____________________________]│
│                                                   │
│ [ 📤 Submit Referral ]                           │
└─────────────────────────────────────────────────┘
```

### 5D.3 Referral Status Lifecycle

```
Doctor creates referral
         │
         ▼
    ┌─────────┐
    │ PENDING  │ ←── Reception sees this in their "Referrals" panel
    └─────────┘
         │
    ┌────┴────┐
    │         │
Internal   External
    │         │
    ▼         ▼
┌────────┐  ┌──────────────┐
│ BOOKED │  │ REFERRED OUT │
└────────┘  └──────────────┘
    │              │
    ▼              ▼
┌───────────┐  ┌───────────┐
│ COMPLETED │  │ COMPLETED │ (feedback received from external)
└───────────┘  └───────────┘
```

### 5D.4 Reception — Referral Management

Add a **"Referrals"** sidebar item in the reception queue widget:

```html
<div class="queue-item" data-filter="referrals" style="border-left: 3px solid #b45309;">
    <span class="queue-item-label"><i class="mdi mdi-account-arrow-right text-warning"></i> <strong>Pending Referrals</strong></span>
    <span class="queue-count" id="queue-referrals-count" style="background: #b45309; color: #fff;">0</span>
</div>
```

Clicking shows pending referrals with actions:

| Action | Type | Effect |
|---|---|---|
| **Book Appointment** | Internal | Opens appointment booking modal pre-filled with referral target clinic/doctor/specialization |
| **Mark Referred Out** | External | Confirms external referral, optionally upload referral letter |
| **Decline** | Any | Mark as declined with reason |
| **Cancel** | Any | Cancel the referral |

When reception books an internal referral as an appointment, the `DoctorAppointment.referral_id` links back to the referral, and the referral status → `booked`.

### 5D.5 Doctor Visibility

On the encounter view, doctors see:
- Their own outgoing referrals for this patient
- A history of past referrals for this patient (with outcomes)
- Incoming referrals where they are the target specialist (with the referring doctor's notes)

### 5D.6 Referral API Endpoints

| Method | Route | Controller Method | Purpose |
|---|---|---|---|
| `POST` | `/encounters/{encounter}/referrals` | `createReferral` | Doctor creates referral from encounter |
| `GET` | `/encounters/{encounter}/referrals` | `getEncounterReferrals` | List referrals for this encounter |
| `GET` | `/referrals/pending` | `getPendingReferrals` | Reception: list all pending referrals |
| `POST` | `/referrals/{id}/book` | `bookReferralAppointment` | Reception: book appointment from referral |
| `POST` | `/referrals/{id}/refer-out` | `referOut` | Reception: mark as externally referred |
| `POST` | `/referrals/{id}/cancel` | `cancelReferral` | Cancel referral |
| `GET` | `/patients/{id}/referral-history` | `patientReferralHistory` | Full referral history for patient |

---

## 6. Reception Workbench — Appointment Management

### 6.1 New Booking Form Fields

Extend the existing booking form (`workbench.blade.php` L4668-4740) to support scheduling:

```
┌─────────────────────────────────────────────────┐
│ 📅 Book Consultation                             │
│                                                   │
│ Service:   [-- Select Service --        ▾]       │
│ Clinic:    [-- Select Clinic --         ▾]       │
│ Doctor:    [Any Available Doctor        ▾]       │
│                                                   │
│ ┌─ Appointment Type ────────────────────────┐    │
│ │ (●) Walk-in (Now)   ( ) Schedule Future   │    │
│ └───────────────────────────────────────────┘    │
│                                                   │
│ ┌─ Schedule Fields (shown when "Schedule") ──┐   │
│ │ Date:     [2026-03-05      📅]             │   │
│ │ Time:     [09:00 ▾] → [09:15]             │   │
│ │ Duration: [15 min ▾]                       │   │
│ │ Type:     [Consultation ▾]                 │   │
│ │ Priority: [Routine ▾]                      │   │
│ │ Reason:   [________________________]       │   │
│ │ Notes:    [________________________]       │   │
│ └────────────────────────────────────────────┘   │
│                                                   │
│ [ 📤 Send to Queue ]  OR  [ 📅 Schedule ]       │
└─────────────────────────────────────────────────┘
```

**Behavior:**
- **Walk-in (default):** Existing flow — creates `ProductOrServiceRequest` + `DoctorQueue(status=1)` immediately
- **Schedule Future:** Creates `DoctorAppointment(status=6)`. No `ProductOrServiceRequest` or `DoctorQueue` until check-in
- Time slot dropdown populated from `clinic_schedules` + `doctor_availabilities`, filtered to show only open slots

### 6.2 "Today's Appointments" Panel

Add a new queue widget item in the reception sidebar (after the existing queue items):

```html
<div class="queue-item" data-filter="appointments-today" style="border-left: 3px solid #6f42c1;">
    <span class="queue-item-label"><i class="mdi mdi-calendar-check text-purple"></i> <strong>Today's Appointments</strong></span>
    <span class="queue-count" id="queue-appointments-count" style="background: #6f42c1; color: #fff;">0</span>
</div>
```

Clicking opens a panel showing today's scheduled appointments with quick actions:
- **Check-in** → Convert to queue entry (status 6 → 1)
- **Cancel** → Mark cancelled (status 6 → 0)
- **No-Show** → Mark no-show (status 6 → 7)
- **Reschedule** → Open reschedule modal

### 6.3 Full Appointment Manager View

A new tab in the reception workbench (alongside existing "Overview", "Patient", "Book Service" tabs):

```html
<button class="workspace-tab" data-tab="appointments-tab">
    <i class="mdi mdi-calendar-month"></i> Appointments
</button>
```

This tab renders the **Appointment Scheduler component** (Section 5.1) with:
- **Full access** to all filters (all clinics, all doctors)
- **Calendar + Table** toggle
- **Create / Edit / Cancel / Reschedule** capabilities
- **Bulk actions:** Select multiple → Cancel / Reschedule / Send reminder (future)

### 6.4 Referral Management Panel

Add a **"Referrals"** sidebar item below "Today's Appointments" in the reception queue widget:

```html
<div class="queue-item" data-filter="pending-referrals" style="border-left: 3px solid #e67e22;">
    <span class="queue-item-label"><i class="mdi mdi-account-arrow-right text-warning"></i> <strong>Pending Referrals</strong></span>
    <span class="queue-count" id="queue-referrals-count" style="background: #e67e22; color: #fff;">0</span>
</div>
```

Clicking opens a panel showing referrals with `status = pending`:

```
┌─────────────────────────────────────────────────────────────────────┐
│ 🏥 Pending Referrals                                      [View All]│
├─────────────────────────────────────────────────────────────────────┤
│ Patient: John Doe (FN-001)                                          │
│ Referred by: Dr. Smith → Cardiology                                 │
│ Reason: Chest pain, ECG abnormality — needs cardio evaluation       │
│ Urgency: 🟡 Routine   |   Date: 2026-03-02                        │
│                                                                     │
│ [📅 Book In-Hospital]  [🏥 Mark External Referral]  [❌ Decline]   │
├─────────────────────────────────────────────────────────────────────┤
│ Patient: Jane Smith (FN-045)                                        │
│ Referred by: Dr. Lee → Orthopedics                                  │
│ Reason: Chronic knee pain unresponsive to treatment                 │
│ Urgency: 🔴 Urgent   |   Date: 2026-03-01                         │
│                                                                     │
│ [📅 Book In-Hospital]  [🏥 Mark External Referral]  [❌ Decline]   │
└─────────────────────────────────────────────────────────────────────┘
```

**"Book In-Hospital"** action:
1. Opens the appointment booking modal pre-filled with: referred specialist clinic, any doctor in that clinic
2. On save: Creates `DoctorAppointment` with `referral_id` linked, updates referral `status = booked`, sets `actioned_at`, `actioned_by`

**"Mark External Referral"** action:
1. Opens mini-form: external facility name, external provider, referral letter generated/uploaded
2. Updates referral `status = referred_out`, sets `external_facility_name`, `external_provider_name`

### 6.5 Follow-Up Appointment Reception Flow

When checking in a patient with a **pre-paid follow-up** appointment:

```
Reception searches patient → system detects follow-up appointment
         │
         ▼
┌─────────────────────────────────────────────────────────────────────┐
│ ℹ This patient has a pre-paid follow-up appointment                 │
│                                                                     │
│ Original Visit: 2026-02-20 — Dr. Smith (Cardiology)               │
│ Follow-Up:      2026-03-02 09:30 — Dr. Smith (Cardiology)         │
│ Billing:        Covered by original payment (Invoice #INV-00123)   │
│                                                                     │
│ [✅ Check In (No Billing)] [📅 Reschedule] [❌ Cancel]            │
└─────────────────────────────────────────────────────────────────────┘
```

- **"Check In (No Billing)"** creates `DoctorQueue` without new `ProductOrServiceRequest` — reuses the original service request
- Reception sees `is_prepaid_followup = true` badge in the table view

### 6.6 Reception API Endpoints

| Method | Route | Controller Method | Purpose |
|---|---|---|---|
| `GET` | `/reception/appointments` | `getAppointments` | Calendar + table data (filterable) |
| `GET` | `/reception/appointments/today` | `getTodayAppointments` | Today's scheduled count + list |
| `POST` | `/reception/appointments` | `createAppointment` | Book future appointment |
| `PUT` | `/reception/appointments/{id}` | `updateAppointment` | Edit appointment details |
| `POST` | `/reception/appointments/{id}/check-in` | `checkInAppointment` | Convert to queue (6 → 1) |
| `POST` | `/reception/appointments/{id}/cancel` | `cancelAppointment` | Cancel (→ 0) |
| `POST` | `/reception/appointments/{id}/no-show` | `markNoShow` | Mark no-show (→ 7) |
| `POST` | `/reception/appointments/{id}/reschedule` | `rescheduleAppointment` | Create new + link |
| `GET` | `/reception/appointments/available-slots` | `getAvailableSlots` | Slot availability for booking |
| `GET` | `/reception/referrals/pending` | `getPendingReferrals` | Pending referrals count + list |
| `POST` | `/reception/referrals/{id}/book` | `bookReferralAppointment` | Book in-hospital specialist appointment from referral |
| `POST` | `/reception/referrals/{id}/external` | `markExternalReferral` | Mark as referred to external facility |
| `POST` | `/reception/referrals/{id}/decline` | `declineReferral` | Decline referral with reason |
| `POST` | `/reception/appointments/{id}/check-in-followup` | `checkInFollowUp` | Check in pre-paid follow-up (no new billing) |

---

## 7. Doctor Queue — Appointment & Schedule View

### 7.1 Redesigned `my_queues.blade.php`

Transform the doctor queue from a plain DataTable page into a **workbench-style interface** with appointment visibility.

#### 7.1.1 New Tab Structure

```
┌──────────────────────────────────────────────────────────────────┐
│ [New (5)] [Continuing (2)] [Scheduled (8)] [Previous] [Admissions]│
└──────────────────────────────────────────────────────────────────┘
```

Uncomment the existing **"Scheduled" tab** (`my_queues.blade.php` L64-78) and wire it to appointment data.

#### 7.1.2 "Scheduled" Tab Content

The "Scheduled" tab renders the **Appointment Scheduler component** (Section 5.1) scoped to the doctor:

- **Default filters:** Own clinic + self as doctor
- **Can toggle** to see all appointments in their clinic (for coverage)
- **Calendar + Table** toggle switch
- **Doctor-specific actions on appointments:**
  - View patient profile
  - See appointment reason/notes
  - Start encounter directly (if patient has arrived → status = Ready/Waiting)

#### 7.1.3 "New" Tab Enhancement

Add visual indicators for appointments vs walk-ins:

```
┌──────────────────────────────────────────────────────────────────┐
│ #  Patient       File No   Priority  Source       Status   Time  │
│ 1  John Doe      FN-001    Emergency 🔴 Walk-in   Ready    09:12│
│ 2  Jane Smith    FN-045    Routine   📅 Scheduled Waiting  09:30│
│ 3  Bob Wilson    FN-112    Urgent    🚑 Emergency Vitals   09:45│
└──────────────────────────────────────────────────────────────────┘
```

- Source badges: `📅 Scheduled`, `🏥 Walk-in`, `🚑 Emergency`
- Status uses the unified `QueueStatus::badge()` method
- **Real-time status column** reflects nurse vitals progress

#### 7.1.4 Auto-Refresh

Add 30-second auto-refresh to the doctor queue (matching nurse/reception workbenches):

```js
setInterval(function() {
    // Reload active DataTable
    const activeTab = $('.nav-link.active').attr('id');
    $(`#${activeTab.replace('_tab', '_consult_list')}`).DataTable().ajax.reload(null, false);
    
    // Update tab counts
    loadDoctorQueueCounts();
}, 30000);
```

### 7.2 Doctor Appointment API Endpoints

| Method | Route | Controller Method | Purpose |
|---|---|---|---|
| `GET` | `/doctor/appointments` | `getDoctorAppointments` | Scoped to doctor's clinic + self |
| `GET` | `/doctor/appointments/counts` | `getDoctorAppointmentCounts` | Tab badge counts |
| `GET` | `/doctor/queue-counts` | `getDoctorQueueCounts` | Unified queue status counts |

---

## 8. Nurse Workbench — Status Sync

### 8.1 Current Vitals Queue Enhancement

The nurse vitals queue currently filters by `vitals_taken = 0`. With the unified status system:

- **Vitals Queue** shows entries where `status = 1` (Waiting) OR `status = 2` (Vitals Pending)
- When nurse opens vitals form → status transitions `1 → 2` (Waiting → Vitals Pending)
- When nurse saves vitals → status transitions `2 → 3` (Vitals Pending → Ready)
- The `vitals_taken` boolean column is **kept for backward compatibility** but the status integer is authoritative

### 8.2 Appointment Awareness

Add a badge to nurse queue cards for scheduled patients:

```html
<!-- Source badge on vitals queue card -->
<span class="badge bg-purple-subtle text-purple" v-if="source === 'appointment'">
    <i class="mdi mdi-calendar-check"></i> Scheduled
</span>
```

### 8.3 Updated Queue Count API

`NursingWorkbenchController::getQueueCounts()` now uses `QueueStatus`:

```php
'vitals' => DoctorQueue::whereIn('status', [
    QueueStatus::WAITING, 
    QueueStatus::VITALS_PENDING
])->whereDate('created_at', today())->count(),
```

---

## 9. Consultation Timer (Pause / Resume)

### 9.1 Overview

Track consultation duration with a visible timer on the encounter screen. The timer:

- **Starts** when doctor opens the encounter (auto or manual)
- **Pauses** when doctor clicks "Pause" (break, phone call, etc.)
- **Resumes** when doctor clicks "Resume"
- **Stops** when encounter is finalized
- **Persists** across page refreshes (stored in DB, not just JS)
- **Visible** to reception and nurse (in queue views) as elapsed time

### 9.2 Database Fields (on `doctor_queues`)

| Column | Type | Purpose |
|---|---|---|
| `consultation_started_at` | `timestamp nullable` | When doctor first opened encounter |
| `consultation_ended_at` | `timestamp nullable` | When encounter was finalized |
| `consultation_paused_seconds` | `unsigned int, default 0` | Accumulated pause time |
| `last_paused_at` | `timestamp nullable` | When current pause started |
| `last_resumed_at` | `timestamp nullable` | When last resumed |
| `is_paused` | `boolean, default false` | Currently paused? |

### 9.3 Timer Calculation

```
Active consultation time = (now - consultation_started_at) - consultation_paused_seconds - current_pause_duration

Where:
  current_pause_duration = is_paused ? (now - last_paused_at) : 0
```

### 9.4 Timer UI Component

**File:** `resources/views/admin/doctors/partials/consultation_timer.blade.php`

```
┌─────────────────────────────────────────────────────┐
│  🕐 Consultation Timer                              │
│                                                      │
│       ┌──────────────┐                               │
│       │   00:23:45   │  ← live counting up           │
│       └──────────────┘                               │
│                                                      │
│  [⏸ Pause]  [⏹ End Consultation]                    │
│                                                      │
│  Started: 09:15 AM                                   │
│  Paused: 2 times (total 5m 30s)                     │
└─────────────────────────────────────────────────────┘
```

When paused:

```
┌─────────────────────────────────────────────────────┐
│  🕐 Consultation Timer  ⚠ PAUSED                    │
│                                                      │
│       ┌──────────────┐                               │
│       │   00:23:45   │  ← frozen, pulse animation   │
│       └──────────────┘                               │
│                                                      │
│  [▶ Resume]  [⏹ End Consultation]                   │
│                                                      │
│  Started: 09:15 AM | Paused at: 09:38 AM            │
│  Total paused: 5m 30s (2 pauses)                    │
└─────────────────────────────────────────────────────┘
```

### 9.5 Timer API Endpoints

| Method | Route | Controller | Purpose |
|---|---|---|---|
| `POST` | `/encounters/{encounter}/timer/start` | `startTimer` | Set `consultation_started_at`, status → 4 |
| `POST` | `/encounters/{encounter}/timer/pause` | `pauseTimer` | Set `last_paused_at`, `is_paused = true` |
| `POST` | `/encounters/{encounter}/timer/resume` | `resumeTimer` | Accumulate pause seconds, `is_paused = false` |
| `GET` | `/encounters/{encounter}/timer/status` | `getTimerStatus` | Get current timer state for JS sync |

### 9.6 Timer JS Logic

```js
class ConsultationTimer {
    constructor(config) {
        this.startedAt = new Date(config.consultation_started_at);
        this.pausedSeconds = config.consultation_paused_seconds || 0;
        this.isPaused = config.is_paused;
        this.lastPausedAt = config.last_paused_at ? new Date(config.last_paused_at) : null;
        this.tickInterval = null;
    }
    
    getElapsedSeconds() {
        const now = new Date();
        let total = Math.floor((now - this.startedAt) / 1000);
        total -= this.pausedSeconds;
        if (this.isPaused && this.lastPausedAt) {
            total -= Math.floor((now - this.lastPausedAt) / 1000);
        }
        return Math.max(0, total);
    }
    
    formatTime(seconds) {
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = seconds % 60;
        return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    }
    
    start() {
        this.tickInterval = setInterval(() => {
            const elapsed = this.getElapsedSeconds();
            $('#timer-display').text(this.formatTime(elapsed));
        }, 1000);
    }
    
    pause() {
        clearInterval(this.tickInterval);
        this.isPaused = true;
        this.lastPausedAt = new Date();
        $('#timer-display').addClass('timer-paused');
        // POST to /timer/pause
    }
    
    resume() {
        this.pausedSeconds += Math.floor((new Date() - this.lastPausedAt) / 1000);
        this.isPaused = false;
        this.lastPausedAt = null;
        $('#timer-display').removeClass('timer-paused');
        this.start();
        // POST to /timer/resume
    }
}
```

### 9.7 Timer Visibility in Queue Views

In reception and doctor queue DataTables, show elapsed consultation time for status = 4 (In Consultation):

```html
<!-- In queue DataTable "Time" column for status 4 -->
<span class="badge bg-success-subtle text-success consultation-timer" 
      data-started="{{ $entry->consultation_started_at }}"
      data-paused-seconds="{{ $entry->consultation_paused_seconds }}"
      data-is-paused="{{ $entry->is_paused }}">
    <i class="mdi mdi-timer"></i> 00:15:32
</span>
```

These mini-timers tick live in the browser (updated by a global 1-second `setInterval`).

---

## 10. Status Sync Architecture

### 10.1 Sync Approach: Server-Side Polling (Phase 1)

Since broadcasting is currently disabled (BroadcastServiceProvider commented out), use **optimized polling**:

| Screen | Poll Interval | What's Polled |
|---|---|---|
| Reception workbench | 30s (existing) | Queue counts, active DataTable |
| Nurse workbench | 30s (existing) | Queue counts, vitals queue |
| Doctor my_queues | **30s (new)** | Tab counts, active DataTable |
| Encounter view | 60s | Timer sync (to prevent drift) |

### 10.2 Status Transition Events (Phase 2 — Broadcasting)

When broadcasting is enabled, emit Laravel events for real-time updates:

```php
// app/Events/QueueStatusChanged.php
class QueueStatusChanged implements ShouldBroadcast {
    public int $queueId;
    public int $oldStatus;
    public int $newStatus;
    public int $clinicId;
    public ?int $doctorId;
    
    public function broadcastOn() {
        return [
            new Channel("clinic.{$this->clinicId}"),
            $this->doctorId ? new PrivateChannel("doctor.{$this->doctorId}") : null,
        ];
    }
}
```

### 10.3 Centralized Status Transition Service

**File:** `app/Services/QueueStatusService.php`

All status changes go through this service to ensure:
1. Transition is valid (e.g., can't go from Completed → Waiting)
2. Related models are updated atomically
3. Events are fired for sync
4. Audit trail is maintained

```php
class QueueStatusService
{
    const ALLOWED_TRANSITIONS = [
        QueueStatus::SCHEDULED      => [QueueStatus::WAITING, QueueStatus::CANCELLED, QueueStatus::NO_SHOW],
        QueueStatus::WAITING        => [QueueStatus::VITALS_PENDING, QueueStatus::IN_CONSULTATION, QueueStatus::CANCELLED],
        QueueStatus::VITALS_PENDING => [QueueStatus::READY, QueueStatus::CANCELLED],
        QueueStatus::READY          => [QueueStatus::IN_CONSULTATION, QueueStatus::CANCELLED],
        QueueStatus::IN_CONSULTATION => [QueueStatus::COMPLETED, QueueStatus::CANCELLED],
        QueueStatus::COMPLETED      => [], // terminal
        QueueStatus::CANCELLED      => [QueueStatus::SCHEDULED], // can reschedule
        QueueStatus::NO_SHOW        => [QueueStatus::SCHEDULED], // can reschedule
    ];

    public function transition(DoctorQueue $queue, int $newStatus, ?string $reason = null): DoctorQueue
    {
        $oldStatus = $queue->status;
        
        if (!in_array($newStatus, self::ALLOWED_TRANSITIONS[$oldStatus] ?? [])) {
            throw new InvalidStatusTransitionException($oldStatus, $newStatus);
        }
        
        DB::transaction(function() use ($queue, $newStatus, $oldStatus, $reason) {
            $queue->update(['status' => $newStatus]);
            
            // Side effects
            if ($newStatus === QueueStatus::IN_CONSULTATION && !$queue->consultation_started_at) {
                $queue->update(['consultation_started_at' => now()]);
            }
            if ($newStatus === QueueStatus::COMPLETED) {
                $queue->update(['consultation_ended_at' => now()]);
            }
            
            // Sync appointment status if linked
            if ($queue->appointment_id) {
                DoctorAppointment::where('id', $queue->appointment_id)
                    ->update(['status' => $newStatus]);
            }
        });
        
        // Future: event(new QueueStatusChanged($queue->id, $oldStatus, $newStatus, ...));
        
        return $queue->fresh();
    }
}
```

### 10.4 Cross-Role Visibility Matrix

| Data Point | Reception | Nurse | Doctor |
|---|---|---|---|
| All clinic appointments | ✅ All clinics | ❌ | ✅ Own clinic |
| Queue status (live) | ✅ All | ✅ Vitals-related only | ✅ Own clinic |
| Consultation timer | ✅ View elapsed | ❌ | ✅ Control (pause/resume) |
| Appointment CRUD | ✅ Full | ❌ | ✅ View + start encounter |
| Cancel / No-Show | ✅ | ❌ | ❌ |
| Reschedule | ✅ | ❌ | ❌ |
| Check-in patient | ✅ | ❌ | ❌ |
| Start encounter | ❌ | ❌ | ✅ |
| Complete encounter | ❌ | ❌ | ✅ |
| Delivery status badge | ✅ View | ✅ View | ✅ View (gates "Start Visit") |
| Create referral | ❌ | ❌ | ✅ From encounter |
| Action referral (book/external) | ✅ | ❌ | ❌ |
| View referral status | ✅ All | ❌ | ✅ Own referrals |
| Schedule follow-up | ✅ Manual | ❌ | ✅ From encounter |
| Check-in pre-paid follow-up | ✅ (billing auto-linked) | ❌ | ❌ |
| Change doctor (reassignment) | ✅ Any appointment | ❌ | ✅ Own clinic only |
| Reassignment audit trail | ✅ View | ❌ | ✅ View |

---

## 11. Implementation Phases

### Phase 1: Foundation (Week 1-2) — ✅ COMPLETE

> All migrations run (batch 155). All models, services, and enum created. Status data migrated.

| # | Task | Files | Priority | Status |
|---|---|---|---|---|
| 1.1 | Create `QueueStatus` enum | `app/Enums/QueueStatus.php` | P0 | ✅ Done — 8 states, labels, badges, colors, ACTIVE/TERMINAL arrays |
| 1.2 | Create `doctor_appointments` migration | `database/migrations/2026_03_02_120000_…` | P0 | ✅ Done & migrated |
| 1.3 | Create `clinic_schedules` migration | `database/migrations/2026_03_02_120001_…` | P0 | ✅ Done & migrated |
| 1.4 | Create `doctor_availabilities` migration | `database/migrations/2026_03_02_120002_…` | P0 | ✅ Done & migrated |
| 1.5 | Create `doctor_availability_overrides` migration | `database/migrations/2026_03_02_120003_…` | P1 | ✅ Done & migrated |
| 1.6 | Alter `doctor_queues` — add timer + appointment columns | `database/migrations/2026_03_02_120005_…` | P0 | ✅ Done & migrated |
| 1.7 | Alter `encounters` — add `queue_id`, `started_at`, `completed_at` | `database/migrations/2026_03_02_120006_…` | P0 | ✅ Done & migrated |
| 1.8 | Create `DoctorAppointment` model | `app/Models/DoctorAppointment.php` | P0 | ✅ Done — 235 lines, all relationships/scopes |
| 1.9 | Create `ClinicSchedule` model | `app/Models/ClinicSchedule.php` | P0 | ✅ Done |
| 1.10 | Create `DoctorAvailability` model | `app/Models/DoctorAvailability.php` | P0 | ✅ Done |
| 1.11 | Migrate existing status values | `database/migrations/2026_03_02_120007_…` | P0 | ✅ Done — 3→4, 4→5 remapped |
| 1.12 | Update `DoctorQueue` model — add casts, scopes, `QueueStatus` usage | `app/Models/DoctorQueue.php` | P0 | ✅ Done — 169 lines, timer accessors, appointment rel |
| 1.13 | Create `QueueStatusService` | `app/Services/QueueStatusService.php` | P0 | ✅ Done — transition(), pause/resume, side effects |
| 1.14 | Create `AppointmentSlotService` | `app/Services/AppointmentSlotService.php` | P1 | ✅ Done — getAvailableSlots(), isSlotAvailable(), getNextAvailableSlot() |
| 1.15 | Create `specialist_referrals` migration | `database/migrations/2026_03_02_120004_…` | P0 | ✅ Done & migrated |
| 1.16 | Create `SpecialistReferral` model | `app/Models/SpecialistReferral.php` | P0 | ✅ Done — 149 lines, status constants, relationships |
| 1.17 | Add follow-up & reassignment columns to `doctor_appointments` | Included in 1.2 | P0 | ✅ Done |

### Phase 2: Backend APIs (Week 2-3) — ✅ COMPLETE

> All controllers created. All routes defined in `routes/appointments.php` and `routes/referrals.php` (both included from `routes/web.php`). Timer endpoints live on `DoctorAppointmentController` (deviation from plan which suggested `EncounterController`).

| # | Task | Files | Priority | Status |
|---|---|---|---|---|
| 2.1 | Create `DoctorAppointmentController` | `app/Http/Controllers/DoctorAppointmentController.php` | P0 | ✅ Done — 20+ methods incl. CRUD, check-in, timer, unified views |
| 2.2 | Add appointment routes | `routes/appointments.php` | P0 | ✅ Done — 21 routes (REST + timer + follow-up + doctor-scoped) |
| 2.3 | Update `ReceptionWorkbenchController` — booking with schedule option | `ReceptionWorkbenchController.php` | P0 | ✅ Done — `bookConsultation()` handles walk-in + schedule paths |
| 2.4 | Update `ReceptionWorkbenchController` — queue counts (use QueueStatus) | Same | P0 | ✅ Done — `getQueueCounts()` uses `QueueStatus` constants |
| 2.5 | Update `EncounterController` — use `QueueStatus`, unified statuses | `EncounterController.php` | P0 | ✅ Done — imports QueueStatus + QueueStatusService, uses in `create()` and `finalizeEncounter()` |
| 2.6 | Update `NursingWorkbenchController` — use `QueueStatus` for vitals queue | `NursingWorkbenchController.php` | P0 | ✅ Done — constructor-injected QueueStatusService, status-based filtering |
| 2.7 | Add consultation timer endpoints | `DoctorAppointmentController` | P0 | ✅ Done — `startTimer`, `pauseTimer`, `getTimerStatus` (start doubles as resume) |
| 2.8 | Add available-slots endpoint | `DoctorAppointmentController` | P1 | ✅ Done — delegated to `AppointmentSlotService` |
| 2.9 | Add appointment check-in, cancel, no-show, reschedule endpoints | `DoctorAppointmentController` | P0 | ✅ Done |
| 2.10 | Update `VitalSignController` — transition through `QueueStatusService` | `VitalSignController.php` | P0 | ✅ Done — constructor-injected, per-queue transition chain |
| 2.11 | Create `SpecialistReferralController` (CRUD + status lifecycle) | `SpecialistReferralController.php` | P0 | ✅ Done — 9 methods covering full lifecycle |
| 2.12 | Add referral routes | `routes/referrals.php` | P0 | ✅ Done — 9 routes (encounter-scoped + reception actions + history) |
| 2.13 | Add follow-up scheduling endpoint | `DoctorAppointmentController::scheduleFollowUp` | P0 | ✅ Done — route `POST /encounters/{encounter}/schedule-followup` |
| 2.14 | Add doctor reassignment endpoint | `DoctorAppointmentController::reassignDoctor` | P0 | ✅ Done — route `POST /appointments/{appointment}/reassign` |
| 2.15 | Add pre-paid follow-up check-in endpoint | `ReceptionWorkbenchController::checkInFollowUp` | P0 | ⚠️ Partial — `bookConsultation()` handles schedule path but no dedicated `checkInFollowUp` method; check-in goes through `DoctorAppointmentController::checkIn()` |
| 2.16 | Add referral-to-appointment booking endpoint | `SpecialistReferralController::bookReferralAppointment` | P0 | ✅ Done — route `POST /referrals/{referral}/book` |

### Phase 3: Reception UI (Week 3-4) — ✅ COMPLETE (implementation deviates from plan)

> Functionality implemented directly in `reception/workbench.blade.php` instead of a standalone `appointment-scheduler.blade.php` component. FullCalendar v2.2.5 used (no upgrade to v6). All actions wired via JS.

| # | Task | Files | Priority | Status |
|---|---|---|---|---|
| 3.1 | Create appointment scheduler Blade component | N/A | P0 | ⚠️ Skipped — Functionality embedded directly in reception workbench (calendar + table + context menu + filters) |
| 3.2 | Add "Appointments" tab to reception workbench | `reception/workbench.blade.php` | P0 | ✅ Done — `workspace-tab[data-tab="appointments"]` + `#appointments-tab` |
| 3.3 | Add "Today's Appointments" sidebar item | Same | P0 | ✅ Done — `data-filter="appointments-today"` with `#queue-appointments-count` |
| 3.4 | Extend booking form with schedule toggle + date/time fields | Same | P0 | ✅ Done — Radio toggle: Walk-in / Schedule Future, with date/time/slot fields |
| 3.5 | Calendar view JS (FullCalendar integration) | Same | P0 | ✅ Done — `initAppointmentsCalendar()`, agendaWeek view, event click → context menu |
| 3.6 | Table view DataTable | Same | P0 | ✅ Done — Hidden `#appointments-table-container` with DataTable |
| 3.7 | Context menu (click appointment → actions) | Same | P0 | ✅ Done — `.appt-context-menu` with Check-In / Cancel / No-Show / Reschedule / Change Doctor / View History |
| 3.8 | New Appointment modal | Same | P0 | ✅ Done — Booking form with schedule option |
| 3.9 | Reschedule modal | Same | P1 | ✅ Done — `#rescheduleAppointmentModal` |
| 3.10 | Cancel reason modal | Same | P1 | ✅ Done — Handled via context menu cancel action |
| 3.11 | Update queue count display to use unified statuses | Same | P0 | ✅ Done — Uses `QueueStatus` constants |
| 3.12 | Add "Pending Referrals" sidebar item + panel | Same | P0 | ✅ Done — `data-filter="referrals"` with `#queue-referrals-count` |
| 3.13 | Referral action modals (Book In-Hospital, Mark External, Decline) | Same | P0 | ✅ Done — Button handlers `.btn-book-referral`, `.btn-refer-out`, `.btn-decline-referral`, `.btn-cancel-referral` |
| 3.14 | Doctor reassignment modal | Same | P0 | ✅ Done — Available via context menu "Change Doctor" action |
| 3.15 | Follow-up check-in flow (detect pre-paid, skip billing) | Same | P0 | ⚠️ Partial — Check-in delegated to `DoctorAppointmentController::checkIn()` which handles pre-paid logic, but no explicit visual "No new billing" indicator confirmed |

### Phase 4: Doctor UI (Week 4-5) — ✅ COMPLETE

> Scheduled tab active. Timer, referrals, and follow-ups all wired in encounter view. Referral tab implemented inline (not as separate partial). Timer JS is inline in the Blade partial, not a standalone `.js` file.

| # | Task | Files | Priority | Status |
|---|---|---|---|---|
| 4.1 | Uncomment + wire "Scheduled" tab in my_queues | `my_queues.blade.php` | P0 | ✅ Done — `status-pill-scheduled` with `data-status="6"` |
| 4.2 | Embed appointment scheduler component in Scheduled tab | Same | P0 | ⚠️ Deviated — No standalone component; scheduled tab uses DataTable with status filter + calendar mini-view |
| 4.3 | Add source badges (Scheduled/Walk-in/Emergency) to New tab | Same | P0 | ✅ Done — `source_badge` DataTable column |
| 4.4 | Add auto-refresh (30s polling) to doctor queue | Same | P0 | ✅ Done — `setInterval(refreshAll, 30000)` |
| 4.5 | Add tab badge counts (dynamic) | Same | P0 | ✅ Done — `loadQueueCounts()` via `appointments.doctor.queue-counts` |
| 4.6 | Add consultation timer to encounter view | `new_encounter.blade.php` | P0 | ✅ Done — `@include('admin.doctors.partials.consultation_timer')` + `ConsultationTimer.init(queueId)` |
| 4.7 | Create consultation timer partial | `partials/consultation_timer.blade.php` | P0 | ✅ Done — 240 lines, full pause/resume UI |
| 4.8 | Consultation timer JS class | Inline in timer partial | P0 | ✅ Done — IIFE with init/start/togglePause/getElapsed, server sync every 60s |
| 4.9 | Timer visibility in doctor queue table (mini-timers) | `my_queues.blade.php` | P1 | ✅ Done — `mini-timer` elements with `data-started` etc., ticked by `initMiniTimers()` JS |
| 4.10 | Update encounter creation to set `consultation_started_at`, `queue_id`, `started_at` | `EncounterController::create()` | P0 | ✅ Done — Uses QueueStatusService, sets queue_id + consultation_started_at |
| 4.11 | Add "Refer to Specialist" tab/button in encounter view | `new_encounter.blade.php` | P0 | ✅ Done — Inline referral form + list in `#referrals` tab pane (not separate partial) |
| 4.12 | Add "Schedule Follow-Up" button in encounter finalization | `new_encounter.blade.php` | P0 | ✅ Done — Checkbox toggle in conclude modal, `finalizeEncounterFromModal()` chains follow-up creation |
| 4.13 | Follow-up chain visibility | `my_queues.blade.php` DataTable | P1 | ⚠️ Partial — Chain endpoint exists (`appointments.chain`) but not confirmed rendered in DataTable UI |
| 4.14 | Doctor reassignment action in Scheduled tab context menu | `my_queues.blade.php` | P1 | ⚠️ Partial — Route exists (`appointments.reassign`), reception context menu has it, doctor-side not confirmed |

### Phase 5: Nurse Sync & Polish (Week 5-6) — ✅ COMPLETE

> All P0/P1 items done. P2 mini-timer in nurse cards deferred.

| # | Task | Files | Priority | Status |
|---|---|---|---|---|
| 5.1 | Update nurse vitals queue to use `QueueStatus` constants | `NursingWorkbenchController` | P0 | ✅ Done — `whereIn('status', [QueueStatus::WAITING, QueueStatus::VITALS_PENDING])` |
| 5.2 | Add source badge (Scheduled/Walk-in) to nurse queue cards | `workbench.blade.php` (nursing) | P1 | ✅ Done — `queue-source-badge` CSS classes for appointment/walk-in/emergency |
| 5.3 | Nurse picks up patient → status `1 → 2` transition via `QueueStatusService` | `NursingWorkbenchController` | P0 | ✅ Done — in `getPatientDetails()`, transition WAITING→VITALS_PENDING |
| 5.4 | Vitals saved → status `2 → 3` transition via `QueueStatusService` | `VitalSignController` | P0 | ✅ Done — per-queue transition chain with WAITING→VITALS_PENDING→READY fallback |
| 5.5 | Add mini consultation timer to nurse queue cards | `workbench.blade.php` (nursing) | P2 | ⏳ Not started — deferred |
| 5.6 | CSS standardization — shared status badge styles across all workbenches | `public/css/queue-status.css` | P1 | ✅ Done — Linked in nursing, reception, and doctor views |

### Phase 6: Advanced Features (Week 6+) — 🔄 IN PROGRESS

> **Approach Changes (2026-03-05):**
> - **Observers over cron jobs:** Auto no-show marking uses `DoctorAppointmentObserver` instead of scheduled tasks
> - **SMTP email notifications:** Appointment emails sent via dynamic SMTP (from `application_status`) with PHP `mail()` fallback
> - **Hospital branding:** Email template uses `appsettings()` for logo, site_name, address, color, footer — same pattern as lab results
> - **SMTP config in Hospital Config:** Admin UI for SMTP credentials + per-recipient email toggles

| # | Task | Priority | Status |
|---|---|---|---|
| 6.1 | Doctor availability management UI (CRUD for weekly schedule) | P2 | ⏳ Not started |
| 6.2 | Clinic schedule management UI (admin) | P2 | ⏳ Not started |
| 6.3 | Auto no-show marking (observer-based — marks past scheduled appointments on any appointment update) | P1 | ✅ Complete |
| 6.4 | Appointment email notifications — SMTP with PHP mail() fallback, hospital-branded template | P1 | ✅ Complete |
| 6.5 | SMTP credentials in `application_status` + Hospital Config UI | P1 | ✅ Complete |
| 6.6 | Email toggle settings — send to doctors / send to patients | P1 | ✅ Complete |
| 6.7 | `DoctorAppointmentObserver` — created/cancelled/rescheduled/checked_in/no_show/reassigned events | P1 | ✅ Complete |
| 6.8 | `AppointmentNotificationMail` Mailable class | P1 | ✅ Complete |
| 6.9 | Hospital-branded email Blade template (`emails/appointment-notification.blade.php`) | P1 | ✅ Complete |
| 6.10 | Conflict detection (double-booking prevention) — patient + doctor overlap checks | P1 | ✅ Complete |
| 6.11 | Double-booking integrated in create + reschedule flows | P1 | ✅ Complete |
| 6.12 | Fix `HospitalConfigController` cache key bug (`application_settings` → `clearAppSettingsCache()`) | P1 | ✅ Complete |
| 6.13 | Fix `MobileEncounterController` hardcoded status integers (GAP-10) | P2 | ✅ Complete |
| 6.14 | Enable Laravel Broadcasting for real-time status sync (Pusher/WebSocket) | P2 | ⏳ Not started |
| 6.15 | Patient self-service booking (portal — future phase) | P3 | ⏳ Not started |
| 6.16 | Recurring appointments (e.g., weekly follow-ups) | P3 | ⏳ Not started |
| 6.17 | Appointment analytics dashboard | P3 | ⏳ Not started |
| 6.18 | Drag-and-drop rescheduling on calendar | P2 | ⏳ Not started |
| 6.19 | Referral letter PDF generation (for external referrals) | P2 | ⏳ Not started |
| 6.20 | Referral analytics (turnaround time, conversion rate) | P3 | ⏳ Not started |
| 6.21 | Follow-up reminder notifications (SMS/push for upcoming follow-ups) | P3 | ⏳ Not started |
| 6.22 | Auto-suggest follow-up date based on diagnosis/protocol | P3 | ⏳ Not started |
| 6.23 | Reassignment analytics (frequency by doctor, reason breakdown) | P3 | ⏳ Not started |

---

## 12. File-by-File Change Map (Updated 2026-03-05)

### New Files

| File | Purpose | Status |
|---|---|---|
| `app/Enums/QueueStatus.php` | Unified status constants, labels, badges, colors | ✅ Created |
| `app/Models/DoctorAppointment.php` | Appointment model with scopes, relationships, casts | ✅ Created |
| `app/Models/ClinicSchedule.php` | Clinic weekly schedule model | ✅ Created |
| `app/Models/DoctorAvailability.php` | Doctor availability model | ✅ Created |
| `app/Models/DoctorAvailabilityOverride.php` | Schedule overrides (holidays, leave) | ✅ Created |
| `app/Services/QueueStatusService.php` | Centralized status transition logic | ✅ Created |
| `app/Services/AppointmentSlotService.php` | Available slot calculator | ✅ Created |
| `app/Http/Controllers/DoctorAppointmentController.php` | CRUD + lifecycle + timer + unified views | ✅ Created |
| `app/Exceptions/InvalidStatusTransitionException.php` | Custom exception | ✅ Created |
| `resources/views/components/appointment-scheduler.blade.php` | Shared calendar/table component | ❌ Skipped — functionality embedded directly in workbench views |
| `resources/views/admin/doctors/partials/consultation_timer.blade.php` | Timer UI partial (includes inline JS) | ✅ Created |
| `public/js/appointment-calendar.js` | FullCalendar integration + context menu | ❌ Skipped — JS embedded inline in reception workbench |
| `public/js/consultation-timer.js` | Timer JS class | ❌ Skipped — JS embedded inline in timer partial |
| `public/css/queue-status.css` | Shared status badge styles | ✅ Created |
| `database/migrations/2026_03_02_120000_create_doctor_appointments_table.php` | Appointments table | ✅ Created & migrated |
| `database/migrations/2026_03_02_120001_create_clinic_schedules_table.php` | Clinic schedules | ✅ Created & migrated |
| `database/migrations/2026_03_02_120002_create_doctor_availabilities_table.php` | Doctor availability | ✅ Created & migrated |
| `database/migrations/2026_03_02_120003_create_doctor_availability_overrides_table.php` | Overrides | ✅ Created & migrated |
| `database/migrations/2026_03_02_120004_create_specialist_referrals_table.php` | Specialist referrals table | ✅ Created & migrated |
| `database/migrations/2026_03_02_120005_add_appointment_timer_columns_to_doctor_queues_table.php` | Timer fields | ✅ Created & migrated |
| `database/migrations/2026_03_02_120006_add_queue_and_timestamps_to_encounters_table.php` | Encounter-queue link | ✅ Created & migrated |
| `database/migrations/2026_03_02_120007_migrate_doctor_queue_status_values.php` | Data migration | ✅ Created & migrated |
| `routes/appointments.php` | Appointment routes (21 routes) | ✅ Created |
| `app/Models/SpecialistReferral.php` | Referral model with scopes, relationships, status lifecycle | ✅ Created |
| `app/Http/Controllers/SpecialistReferralController.php` | Referral CRUD + status actions | ✅ Created |
| `resources/views/admin/doctors/partials/referral_tab.blade.php` | Referral form + history in encounter view | ❌ Skipped — Referral UI embedded inline in `new_encounter.blade.php` |
| `routes/referrals.php` | Referral routes (9 routes) | ✅ Created |
| `app/Services/AppointmentMailService.php` | SMTP/PHP-mail email sender with hospital branding | ✅ Created (Phase 6) |
| `app/Mail/AppointmentNotificationMail.php` | Laravel Mailable for pre-rendered HTML appointment emails | ✅ Created (Phase 6) |
| `app/Observers/DoctorAppointmentObserver.php` | Observer for appointment lifecycle events — emails + auto no-show | ✅ Created (Phase 6) |
| `resources/views/emails/appointment-notification.blade.php` | Hospital-branded email template for all appointment events | ✅ Created (Phase 6) |
| `database/migrations/2026_03_05_100000_add_smtp_and_email_settings_to_application_status.php` | SMTP credentials + email toggle columns | ✅ Created & migrated (Phase 6) |

### Modified Files

| File | Changes | Status |
|---|---|---|
| `app/Models/DoctorQueue.php` | Add casts, scopes, `QueueStatus` imports, appointment relationship, timer accessors | ✅ Updated |
| `app/Models/Encounter.php` | Add `queue_id` to fillable, add `queue()` relationship, `started_at`/`completed_at` casts | ✅ Updated |
| `app/Models/Clinic.php` | Add `schedules()` relationship, `getTodayScheduleAttribute()` | ✅ Updated |
| `app/Models/Staff.php` | Add `availabilities()`, `availabilityOverrides()` relationships | ✅ Updated |
| `app/Http/Controllers/ReceptionWorkbenchController.php` | QueueStatus in queue counts, booking with schedule option | ✅ Updated |
| `app/Http/Controllers/EncounterController.php` | QueueStatus + QueueStatusService in create()/finalize(), timer fields, status_badge column | ✅ Updated |
| `app/Http/Controllers/NursingWorkbenchController.php` | QueueStatusService DI, status-based filtering, nurse pickup transition | ✅ Updated |
| `app/Http/Controllers/VitalSignController.php` | QueueStatusService DI, per-queue transition chain | ✅ Updated |
| `resources/views/admin/reception/workbench.blade.php` | Appointments tab, Today's Appointments sidebar, Pending Referrals sidebar, booking form schedule toggle, FullCalendar, context menu | ✅ Updated |
| `resources/views/admin/doctors/my_queues.blade.php` | Scheduled tab active, auto-refresh, tab counts, source badges, mini-timers, queue-status.css | ✅ Updated |
| `resources/views/admin/doctors/new_encounter.blade.php` | Timer include, referral tab (inline), follow-up in conclude modal, finalizeEncounterFromModal chains | ✅ Updated |
| `resources/views/admin/nursing/workbench.blade.php` | QueueStatus filtering, source badges, queue-status.css | ✅ Updated |
| `routes/web.php` | Include `appointments.php` + `referrals.php` | ✅ Updated |
| `app/Models/ApplicationStatu.php` | Added SMTP + email notification fields to fillable | ✅ Updated (Phase 6) |
| `app/Http/Controllers/HospitalConfigController.php` | Added SMTP validation, email toggle checkboxes, fixed cache key bug | ✅ Updated (Phase 6) |
| `app/Services/AppointmentSlotService.php` | Added `detectDoubleBooking()` method for patient + doctor conflict detection | ✅ Updated (Phase 6) |
| `app/Http/Controllers/DoctorAppointmentController.php` | Integrated double-booking checks in create + reschedule | ✅ Updated (Phase 6) |
| `app/Http/Controllers/API/MobileEncounterController.php` | Replaced hardcoded status integers with QueueStatus enum (GAP-10) | ✅ Updated (Phase 6) |
| `app/Providers/AppServiceProvider.php` | Registered DoctorAppointmentObserver | ✅ Updated (Phase 6) |
| `resources/views/admin/hospital-config/index.blade.php` | Added SMTP config card + appointment email notification toggles | ✅ Updated (Phase 6) |

---

## 13. UI Reference

### 13.1 Design Inspiration (Attached Image — LinkHMS)

The attached image shows a **weekly calendar grid** with:

- **Header:** Doctor name/role, Today button, `<`/`>` navigation, month/year selector with compact month grid
- **Column headers:** Time slots (12:00, 12:15, 12:30, 12:45, 1:30, 1:45)
- **Row headers:** Date + day of week (17th Wednesday, 18th Thursday, etc.)
- **Appointment cards:** Color-coded by status, showing patient name + age + phone
  - Green fill = Confirmed
  - Blue outline = Waiting
  - Yellow fill = Conflict
  - Light grey = Completed
  - Pink/red striped rows = Unavailable (Sunday, blocked days)
- **Context menu (on card click):**
  - ▶ Start Visit
  - ◉ Patient Confirmed
  - ◉ Patient Arrived
  - ● Complete Visit
  - 📋 View Patient Profile
  - ✏ Edit
  - ❌ Cancel
- **Top-right:** `+ NEW VISIT` button

### 13.2 Our Implementation Adaptations

| LinkHMS Feature | Our Adaptation |
|---|---|
| Weekly grid with time columns | FullCalendar `timeGridWeek` or custom grid |
| Color by status | `QueueStatus::COLORS` — 8-state palette |
| Context menu | Bootstrap dropdown on card click with role-scoped actions |
| Doctor header | Replaced with filter bar (multiple doctors visible) |
| "NEW VISIT" button | "New Appointment" button opens modal |
| Month picker popup | FullCalendar's built-in date navigation |
| **Table view toggle** (NEW — not in LinkHMS) | Bootstrap btn-group toggle between Calendar/Table |
| **Live status sync** (NEW — not in LinkHMS) | 30s polling or future WebSocket |
| **Consultation timer** (NEW — not in LinkHMS) | Timer badge on In-Consultation entries |

### 13.3 Status Color Legend (displayed above calendar/table)

```
● Cancelled (grey)    ● Waiting (yellow)    ● Vitals Pending (cyan)    ● Ready (blue)
● In Consultation (green)    ● Completed (dark)    ● Scheduled (purple)    ● No-Show (red)
```

---

## Appendix A: Appointment Check-in Flow (Detailed)

### A.1 Standard Appointment Check-in

```
Reception searches patient
         │
         ▼
Patient has appointment today? ──── No ──→ Normal walk-in flow (existing)
         │
        Yes
         │
         ▼
Show "Patient has an appointment today at 09:30 with Dr. Smith"
         │
         ▼
is_prepaid_followup? ──── Yes ──→ [Check In (No Billing)] button
         │                                    │
         No                                   ▼
         │                          1. DoctorQueue(status=1, source='appointment',
         ▼                             appointment_id=X,
[Check In] button                      request_entry_id = parent's service_request_id)
         │                          2. Update DoctorAppointment(status=1,
         ▼                             checked_in_at=now(), doctor_queue_id=Q)
1. Create ProductOrServiceRequest    3. NO new ProductOrServiceRequest
   (billing entry)
2. Create DoctorQueue(status=1,
   source='appointment',
   appointment_id=X)
3. Update DoctorAppointment(status=1,
   checked_in_at=now(),
   doctor_queue_id=Q)
         │
         ▼
Vitals are OPTIONAL — patient goes to:
  • Nurse vitals queue (if hospital policy requires vitals)
  • Doctor queue directly (vitals skipped)

The real blocker is HmoHelper::canDeliverService():
  ✅ Paid / HMO validated → doctor can start encounter
  ❌ Unpaid / HMO pending → "Encounter" button disabled
```

### A.2 Pre-Paid Follow-Up Detection

When reception loads a patient, the system automatically checks:

```php
// In ReceptionWorkbenchController::getPatient() or patient load handler
$upcomingFollowUps = DoctorAppointment::where('patient_id', $patientId)
    ->where('appointment_date', today())
    ->where('status', QueueStatus::SCHEDULED)
    ->where('is_prepaid_followup', true)
    ->with(['parentAppointment.serviceRequest'])
    ->get();
```

If found, a notification card is shown with the "Check In (No Billing)" option.

## Appendix B: Auto No-Show Cron Job

```php
// app/Console/Commands/MarkNoShowAppointments.php
// Runs daily at end of business (configured via schedule)

DoctorAppointment::where('status', QueueStatus::SCHEDULED)
    ->where('appointment_date', '<', today())
    ->update([
        'status' => QueueStatus::NO_SHOW,
        'no_show_marked_at' => now(),
    ]);

// Also mark today's appointments past their time + grace period
DoctorAppointment::where('status', QueueStatus::SCHEDULED)
    ->where('appointment_date', today())
    ->where('start_time', '<', now()->subMinutes(config('corehealth.no_show_grace_minutes', 30))->format('H:i'))
    ->update([
        'status' => QueueStatus::NO_SHOW,
        'no_show_marked_at' => now(),
    ]);
```

## Appendix C: Available Slot Calculation

```php
// AppointmentSlotService::getAvailableSlots($clinicId, $date, $doctorId = null)

1. Get clinic schedule for day_of_week($date) → open_time, close_time, slot_duration
2. Get doctor availability for day_of_week($date) → start_time, end_time (if doctor specified)
3. Check doctor_availability_overrides for exact $date → blocked or extra hours
4. Generate all possible slots: from open_time to close_time stepping by slot_duration
5. For each slot, count existing appointments → if count >= max_concurrent_slots, mark unavailable
6. Return: [{ time: "09:00", available: true }, { time: "09:15", available: false, reason: "Fully booked" }, ...]
```

## Appendix D: Referral → Appointment Conversion Flow

```
Doctor creates referral during encounter
         │
         ▼
┌──────────────────────────────────────────────────┐
│ SpecialistReferral created (status = 'pending')  │
│ • referral_type: internal or external            │
│ • target_clinic_id / target_doctor_id            │
│ • urgency, reason, clinical_summary              │
└──────────────────────────────────────────────────┘
         │
         ▼
Referral appears in Reception "Pending Referrals" panel
(queue-referrals-count badge increments)
         │
    ┌────┴────────────────────────┐
    │                             │
  Internal                    External
    │                             │
    ▼                             ▼
Reception clicks               Reception clicks
"Book In-Hospital"            "Mark External Referral"
    │                             │
    ▼                             ▼
1. Appointment modal opens     1. External facility form
   pre-filled:                    (name, address, phone)
   - Clinic = target_clinic    2. Optional: generate/upload
   - Doctor = target_doctor       referral letter PDF
   - Type = 'consultation'    3. Update referral:
   - Source = 'referral'          status → 'referred_out'
   - Patient = referral.patient   actioned_by, actioned_at
2. Reception selects date/time
3. On save:
   - DoctorAppointment created
     with referral_id linked
   - Referral status → 'booked'
   - appointment_id set on referral
   - actioned_by, actioned_at set
         │
         ▼
Appointment follows normal lifecycle:
Scheduled → Check-in → Waiting → Vitals → Ready → Consultation → Completed
         │
         ▼
When specialist encounter is finalized:
   - Referral status → 'completed'
   - Specialist's notes visible to original referring doctor
```

## Appendix E: Doctor Reassignment Flow

```
Original doctor unavailable (leave, sick, schedule conflict)
         │
         ▼
Reception (or doctor) opens appointment → clicks "Change Doctor"
         │
         ▼
┌─────────────────────────────────────────────────┐
│ Reassignment Modal                               │
│                                                   │
│ Current: Dr. Smith (General Clinic)              │
│ Showing: Doctors available on [appointment date]  │
│          in [same clinic]                         │
│                                                   │
│ New Doctor: [dropdown filtered by availability]   │
│ Reason: [dropdown + free text]                    │
└─────────────────────────────────────────────────┘
         │
         ▼
On save:
1. appointment.original_staff_id = old doctor (if first reassignment)
2. appointment.staff_id = new doctor
3. appointment.reassignment_reason = reason
4. appointment.reassigned_at = now()
5. If already checked in (queue exists):
   - DoctorQueue.staff_id also updated
   - Patient moves from old doctor's queue to new doctor's queue
6. Audit trail via Auditable trait captures the change
```

## Appendix F: Follow-Up Visit Chain Example

```
Timeline for patient John Doe:

┌─ Original Visit ─────────────────────────────────────────────────┐
│ Date: 2026-02-20  |  Doctor: Dr. Smith  |  Clinic: Cardiology   │
│ Service: Consultation  |  Amount: ₦15,000  |  Status: Completed  │
│ Appointment ID: #101                                              │
└───────────────────────────────────────────────────────────────────┘
         │
         │ Doctor schedules pre-paid follow-up
         ▼
┌─ Follow-Up 1 ────────────────────────────────────────────────────┐
│ Date: 2026-03-06  |  Doctor: Dr. Smith  |  Clinic: Cardiology   │
│ Type: follow_up  |  Billing: Pre-paid (from #101)                │
│ parent_appointment_id: 101  |  is_prepaid_followup: true         │
│ Appointment ID: #115  |  Status: Completed                       │
└───────────────────────────────────────────────────────────────────┘
         │
         │ Doctor unavailable, receptionist reschedules + changes doctor
         ▼
┌─ Follow-Up 2 (Rescheduled + Reassigned) ─────────────────────────┐
│ Date: 2026-03-20  |  Doctor: Dr. Johnson  |  Clinic: Cardiology │
│ Type: follow_up  |  Billing: New billing required                │
│ parent_appointment_id: 101  |  is_prepaid_followup: false        │
│ rescheduled_from_id: null (fresh follow-up, not rescheduled)     │
│ original_staff_id: Dr. Smith (reassigned to Dr. Johnson)         │
│ Appointment ID: #130  |  Status: Scheduled                       │
└───────────────────────────────────────────────────────────────────┘
```

---

*End of plan.*

---

## 14. Gap Analysis (Updated Audit — 2026-03-05)

> **Full top-to-bottom re-audit** after all gap closures and Phase 6 implementation.
> **GAP-1 through GAP-10:** All resolved (GAP-5 acknowledged as by-design deviation).
> **Phase 6 progress:** 11/23 items complete — observer-based emails, double-booking detection, auto no-show, SMTP config, MobileEncounterController fix, cache key bug fix.
> **Remaining Phase 6:** 12 items (availability UI, broadcasting, analytics, recurring, PDF generation, etc.)

### 14.1 Overall Score

| Layer | Planned Items | Complete | Partial | Missing | Score |
|---|---|---|---|---|---|
| **Phase 1: Foundation** | 17 | 17 | 0 | 0 | **100%** |
| **Phase 2: Backend APIs** | 16 | 16 | 0 | 0 | **100%** |
| **Phase 3: Reception UI** | 15 | 15 | 0 | 0 | **100%** |
| **Phase 4: Doctor UI** | 14 | 14 | 0 | 0 | **100%** |
| **Phase 5: Nurse Sync** | 6 | 6 | 0 | 0 | **100%** |
| **Phase 6: Advanced** | 23 | 11 | 0 | 12 | **48%** |
| **Total (Ph 1–5)** | **68** | **68** | **0** | **0** | **100%** |
| **Total (All)** | **91** | **79** | **0** | **12** | **87%** |

#### 14.1.1 Phase 1 Verification Detail (22 files, all PASS)

- **Enum:** `QueueStatus.php` — 135 lines, 8 constants (CANCELLED=0 through NO_SHOW=7), LABELS/BADGE_CLASSES/COLORS arrays, ACTIVE/TERMINAL arrays, 6 static methods
- **Models (6):** DoctorAppointment (235L), ClinicSchedule (78L), DoctorAvailability (69L), DoctorAvailabilityOverride (72L), SpecialistReferral (148L), DoctorQueue (168L updated), Encounter (83L updated)
- **Services (2):** QueueStatusService (151L) with ALLOWED_TRANSITIONS + transition()/pause()/resume(), AppointmentSlotService (157L) with getAvailableSlots()/isSlotAvailable()/getNextAvailableSlot()
- **Exception:** InvalidStatusTransitionException (25L)
- **CSS:** queue-status.css (53L) — linked in 3 workbenches
- **Migrations (8):** All created & migrated (batch 155). doctor_appointments, clinic_schedules, doctor_availabilities, doctor_availability_overrides, specialist_referrals, doctor_queues alter, encounters alter, status value migration
- **Seeders (2):** ClinicScheduleSeeder (186 rows), DoctorAvailabilitySeeder (234 rows)

#### 14.1.2 Phase 2 Verification Detail (API endpoints)

- **DoctorAppointmentController:** 22 public methods (CRUD, check-in, cancel, no-show, reschedule, reassign, follow-up, timer, calendar, unified views, queue counts) + 3 private helpers
- **SpecialistReferralController:** 9 public methods (create, list, pending, count, book, refer-out, cancel, decline, history)
- **Routes:** 21 in appointments.php + 9 in referrals.php = 30 total, all unique, no conflicts
- **Cross-controller integration:** QueueStatus imported in all 5 modified controllers. QueueStatusService DI in NursingWorkbench, VitalSign, EncounterController (via app()). Encounter::create() correctly uses QueueStatusService + sets queue_id/started_at. finalizeEncounter() correctly uses QueueStatusService + fallback with Log::warning().

#### 14.1.3 Phase 3 Verification Detail (Reception UI — 16 checks, all PASS)

All items verified in `reception/workbench.blade.php` (~12K lines):
queue-status.css link, Appointments tab, Today's Appointments sidebar, booking form schedule toggle, FullCalendar integration, table DataTable, context menu, booking form, reschedule modal, unified queue counts, Pending Referrals sidebar, referral action buttons, doctor reassignment modal, pre-paid follow-up detection, custom slot enhancement, available slots loading.

#### 14.1.4 Phase 4 Verification Detail (Doctor UI — 15 checks, all PASS)

`my_queues.blade.php`: Scheduled tab active (data-status="6"), 30s auto-refresh, dynamic tab counts via queue-counts route, source badges, mini-timers with drawCallback, reassign button + SweetAlert2 modal, calendar toggle in scheduled tab, queue-status.css linked.
`new_encounter.blade.php`: Timer include (@include consultation_timer), referral tab with create/list, follow-up scheduling with prepaid checkbox, finalizeEncounterFromModal chains follow-up creation.
`consultation_timer.blade.php`: Full UI + IIFE JS with init/start/togglePause/getElapsed, 60s server sync.
**Follow-up chain visibility:** Rendered server-side in `getUnifiedQueueList()` — `source_badge` column shows chain icons (mdi-link-variant) for follow-ups and prepaid badges (mdi-cash-check) for pre-paid entries.

#### 14.1.5 Phase 5 Verification Detail (Nurse Sync — 13 checks, all PASS)

`nursing/workbench.blade.php`: queue-status.css linked, source badges (appointment/walkin/emergency), consulting queue cards via loadConsultingQueue(), mini-timers via initNurseMiniTimers().
`NursingWorkbenchController`: QueueStatusService DI, getConsultingQueue() endpoint (IN_CONSULTATION filter), getVitalsQueue() uses QueueStatus::WAITING/VITALS_PENDING, getPatientDetails() triggers WAITING→VITALS_PENDING transition.
`VitalSignController`: QueueStatusService DI, VITALS_PENDING→READY transition chain on vitals save.
`routes/nursing_workbench.php`: consulting-queue route registered.

### 14.2 Architecture Deviations from Plan (Unchanged — Intentional)

| # | Plan Said | Implementation Did | Impact |
|---|---|---|---|
| **D1** | Timer endpoints in `EncounterController` | Timer endpoints in `DoctorAppointmentController` | None — co-located with appointment/queue logic |
| **D2** | Separate `appointment-scheduler.blade.php` | Embedded in `reception/workbench.blade.php` | Doctor Scheduled tab uses DataTable + mini-calendar instead of shared component |
| **D3** | Separate `referral_tab.blade.php` partial | Inline in `new_encounter.blade.php` | Works but less reusable |
| **D4** | Separate `.js` files for calendar/timer | Inline JS in Blade templates | No extra HTTP requests; less cacheable separately |
| **D5** | Timer routes keyed by encounter ID | Timer routes keyed by queue ID (`/queue/{queue}/timer/...`) | Correct — timer data lives on DoctorQueue, not Encounter |
| **D6** | `resumeTimer()` separate endpoint | `startTimer()` doubles as resume | Simpler API |
| **D7** | `DoctorAppointment::queue()` relationship | Named `doctorQueue()` instead | Functionally identical, just naming |

### 14.3 Functional Gaps (Things That Need Work)

#### GAP-1: Pre-Paid Follow-Up Check-In UX (P1)
**Plan Section:** 6.5, Appendix A.1
**Status:** ✅ RESOLVED (2026-03-05)
**Description:** `DoctorAppointmentController::checkIn()` handles the pre-paid follow-up path, but the reception workbench didn't have an explicit visual indicator.
**Fix applied:** Added `upcoming_appointments` (with `is_follow_up`, `is_prepaid_followup`, `is_today` flags) to `ReceptionWorkbenchController::getPatient()` response. Added `displayUpcomingAppointments()` JS in reception workbench that shows a green alert for pre-paid follow-ups with a "Check-In (No Billing)" button, info alerts for other today appointments, and a compact section for future appointments. Also added `quickCheckInFollowUp()` with SweetAlert2 confirmation. Fixed hardcoded status integers to use `QueueStatus` constants.

#### GAP-2: Follow-Up Chain Visibility in Doctor Queue (P2)
**Plan Section:** 4.13, 5A.4
**Status:** ✅ RESOLVED (2026-03-05)
**Description:** The `getAppointmentChain()` method existed but the DataTable didn't render chain/parent linkage for follow-up appointments.
**Fix applied:** Added `is_follow_up` and `is_prepaid` fields to both appointment and queue rows in `getUnifiedQueueList()`. Enhanced `source_badge` column to show a chain icon (`mdi-link-variant`) for follow-up entries and a "Prepaid" badge (`mdi-cash-check`) for pre-paid follow-ups.

#### GAP-3: Doctor Reassignment in Doctor's Scheduled Tab (P2)
**Plan Section:** 4.14
**Status:** ✅ RESOLVED (2026-03-05)
**Description:** Backend route existed but doctor's view didn't expose the reassignment action.
**Fix applied:** Added `btn-reassign-queue` button (purple, `mdi-account-switch` icon) to the action column in `getUnifiedQueueList()` for scheduled appointments. Added `openReassignModal()` JS function in `my_queues.blade.php` using SweetAlert2 with doctor dropdown (fetched from `clinic.doctors` API), reason input, and POST to `appointments.reassign`. Also updated the calendar context menu 'reassign' action to use the same modal instead of just showing a toastr message.

#### GAP-4: Nurse Mini-Timer for In-Consultation Patients (P2)
**Plan Section:** 5.5
**Status:** ✅ RESOLVED (2026-03-05)
**Description:** Plan specified showing a mini consultation timer on nurse queue cards for patients currently in consultation.
**Fix applied:** Added `getConsultingQueue()` endpoint to `NursingWorkbenchController` returning IN_CONSULTATION patients with `consultation_started_at`, `is_paused`, `consultation_paused_seconds`, `last_paused_at` fields. Added route `nursing-workbench.consulting-queue`. Added `loadConsultingQueue()`, `renderConsultingCards()`, and `initNurseMiniTimers()` JS functions in nursing workbench view. The consulting section renders below the vitals cards with a "Currently In Consultation" header and ticking mini-timers on each card.

#### GAP-5: `appointment-scheduler` Not Reusable Across Views (P2)
**Plan Section:** 5.1, 7.1.2
**Status:** ⚠️ By design deviation.
**Description:** The plan specified a reusable Blade component (`appointment-scheduler.blade.php`) that could be embedded in both reception and doctor views. Instead, the calendar/table UI was built directly in the reception workbench. The doctor's Scheduled tab uses a DataTable filter, not a calendar.
**Fix needed:** Consider extracting the reception calendar JS into a reusable partial or separate JS file if the doctor Scheduled tab needs a calendar view in the future.

#### GAP-6: Clinic Schedule & Doctor Availability Seeding (P1)
**Plan Section:** 4.2, 4.3, Appendix C
**Status:** ✅ RESOLVED (2026-03-05)
**Description:** The schedule tables existed but were empty.
**Fix applied:** Created `ClinicScheduleSeeder` (Mon–Fri 08:00–17:00, Sat 08:00–13:00, 15-min slots for 31 clinics) and `DoctorAvailabilitySeeder` (same hours for 39 doctors at their assigned clinics). Both seeders ran successfully.

#### GAP-7: `consultation_ended_at` Set Inconsistently in Finalize (P1)
**Plan Section:** 9.2, 10.3
**Status:** ✅ RESOLVED (2026-03-05)
**Description:** The finalize fallback redundantly set `consultation_ended_at` alongside the status update.
**Fix applied:** Removed redundant `consultation_ended_at = now()` from the catch block in `EncounterController::finalizeEncounter()`. The fallback now only sets `status` and logs a warning via `Log::warning()` with queue_id, target_status, and error message for debugging. The `QueueStatusService::transition()` method handles `consultation_ended_at` when transitioning to COMPLETED.

#### GAP-8: Hardcoded Status Integers in `ReceptionWorkbenchController` (P1)
**Plan Section:** Cross-cutting (Phase 1 enum adoption)
**Status:** ✅ RESOLVED (2026-03-06)
**Description:** Three locations in `ReceptionWorkbenchController` still used raw integers instead of `QueueStatus` constants:
1. `getPatientQueueEntries()` — `whereIn('status', [1, 2, 3])` instead of enum constants
2. Dashboard stats `consultations_done` — `where('status', 4)` which mapped to **IN_CONSULTATION** (wrong!) instead of **COMPLETED** (5). This was an actual **bug**: after the status migration remapped old 4→5, this line counted in-progress patients as "done".
3. Dead `getQueueStatusText()` method (L245-253) — no longer called after GAP-1 fix switched to `QueueStatus::label()`.
**Fix applied:**
- Changed `whereIn('status', [1, 2, 3])` → `whereIn('status', [QueueStatus::WAITING, QueueStatus::VITALS_PENDING, QueueStatus::READY])`
- Changed `where('status', 4)` → `where('status', QueueStatus::COMPLETED)` — **bug fix**
- Removed dead `getQueueStatusText()` method entirely

#### GAP-9: Hardcoded Status Integer in `EmergencyIntakeController` (P1)
**Plan Section:** Cross-cutting (Phase 1 enum adoption)
**Status:** ✅ RESOLVED (2026-03-06)
**Description:** `EmergencyIntakeController` used a hardcoded status integer when creating emergency queue entries instead of the `QueueStatus::WAITING` constant.
**Fix applied:** Replaced hardcoded integer with `QueueStatus::WAITING` and added `use App\Enums\QueueStatus` import.

#### GAP-10: `MobileEncounterController` Uses Old Status System (P2)
**Plan Section:** N/A (not part of web enhancement plan)
**Status:** ✅ RESOLVED (2026-03-05)
**Description:** `MobileEncounterController` used the entire pre-migration status integer system (hardcoded 1, 2, 3) for mobile API endpoints.
**Fix applied:** Imported `QueueStatus` enum. Replaced hardcoded status queries with enum-based `$statusMap` (1→[WAITING, VITALS_PENDING, READY], 2→[IN_CONSULTATION], 3→[COMPLETED, CANCELLED, NO_SHOW]). Replaced `$statusLabels` array with enum-backed mapping. Fixed `endOldContinuingEncounters()` to use `QueueStatus::IN_CONSULTATION` → `QueueStatus::COMPLETED` instead of `2 → 3`. Maintains backward compatibility with mobile app's legacy 1/2/3 status parameter.

#### GAP-11: Double-Booking Detection Not Implemented (P1)
**Plan Section:** 6.10
**Status:** ✅ RESOLVED (2026-03-05)
**Description:** No conflict detection existed — a patient or doctor could be double-booked at overlapping times.
**Fix applied:** Added `detectDoubleBooking()` method to `AppointmentSlotService` — checks both patient conflicts (same patient, overlapping time across any clinic) and doctor conflicts (same doctor, overlapping time). Returns structured conflict array with type, message, and appointment_id. Integrated into `DoctorAppointmentController::createAppointment()` and `reschedule()` — returns 422 with conflict details.

#### GAP-12: Hospital Config Cache Key Bug (P1)
**Plan Section:** Cross-cutting
**Status:** ✅ RESOLVED (2026-03-05)
**Description:** `HospitalConfigController::update()` cleared cache key `'application_settings'` but `appsettings()` uses `'app_settings'`. Settings wouldn't refresh after save.
**Fix applied:** Replaced `\Cache::forget('application_settings')` with `clearAppSettingsCache()` which correctly forgets the `'app_settings'` key.

### 14.4 Data Integrity Gaps

| # | Issue | Risk | Status |
|---|---|---|---|
| **DI-1** | `clinic_schedules` table empty — no operating hours configured | `getAvailableSlots()` returns nothing | ✅ RESOLVED — Seeded 31 clinics (ClinicScheduleSeeder) |
| **DI-2** | `doctor_availabilities` table empty — no doctor schedules | Same as above | ✅ RESOLVED — Seeded 39 doctors (DoctorAvailabilitySeeder) |
| **DI-3** | Old encounter records have `queue_id = NULL`, `started_at = NULL` | Historical data lacks queue linkage | ⚠️ Acceptable — only affects analytics on old records |
| **DI-4** | Status migration remapped 3→4, 4→5 but any queues with status=0 (old cancelled) now conflict with WAITING=0 | ✅ VERIFIED CLEAN — 0 rows with status=0 in doctor_queues. Only statuses 1, 3, 4 exist. | No fix needed |
| **DI-5** | `consultations_done` dashboard stat was counting IN_CONSULTATION (4) instead of COMPLETED (5) | Inflated "done" count, missed actually completed patients | ✅ RESOLVED — GAP-8 fix (2026-03-06) |

### 14.5 Route Completeness Audit

| Plan Route | Actual Route | Match |
|---|---|---|
| `POST /encounters/{encounter}/timer/start` | `POST /queue/{queue}/timer/start` | ⚠️ Different URI pattern — plan used encounter ID, implementation uses queue ID. Works correctly (timer is on queue, not encounter). |
| `POST /encounters/{encounter}/timer/pause` | `POST /queue/{queue}/timer/pause` | ⚠️ Same deviation as above |
| `POST /encounters/{encounter}/timer/resume` | N/A — `startTimer()` handles resume | ✅ Simplified |
| `GET /encounters/{encounter}/timer/status` | `GET /queue/{queue}/timer/status` | ⚠️ Same deviation |
| `POST /encounters/{encounter}/schedule-followup` | `POST /encounters/{encounter}/schedule-followup` | ✅ Exact match |
| `POST /encounters/{encounter}/referrals` | `POST /encounters/{encounter}/referrals` | ✅ Exact match |
| `GET /encounters/{encounter}/referrals` | `GET /encounters/{encounter}/referrals` | ✅ Exact match |
| `GET /referrals/pending` | `GET /referrals/pending` | ✅ Exact match |
| `POST /referrals/{id}/book` | `POST /referrals/{referral}/book` | ✅ Match |
| `POST /referrals/{id}/refer-out` | `POST /referrals/{referral}/refer-out` | ✅ Match |
| `POST /referrals/{id}/cancel` | `POST /referrals/{referral}/cancel` | ✅ Match |
| `POST /referrals/{id}/decline` | `POST /referrals/{referral}/decline` | ✅ Match |
| `GET /patients/{id}/referral-history` | `GET /patients/{patient}/referral-history` | ✅ Match |
| `GET /reception/appointments` | `GET /appointments/list` | ⚠️ Different prefix — plan used `/reception/`, implementation uses `/appointments/` |
| `POST /reception/appointments` | `POST /appointments/create` | ⚠️ Same prefix difference |
| `POST /reception/appointments/{id}/check-in` | `POST /appointments/{appointment}/check-in` | ⚠️ Same prefix difference |
| All other appointment CRUD | Under `/appointments/...` prefix | ✅ Functionally complete |

### 14.6 Frontend JS Route Alignment

| View | JS AJAX URL | Matching Route | Status |
|---|---|---|---|
| `consultation_timer.blade.php` | `/queue/{queueId}/timer/status` | `queue.timer.status` | ✅ Aligned |
| `consultation_timer.blade.php` | `/queue/{queueId}/timer/start` | `queue.timer.start` | ✅ Aligned |
| `consultation_timer.blade.php` | `/queue/{queueId}/timer/pause` | `queue.timer.pause` | ✅ Aligned |
| `new_encounter.blade.php` | `/encounters/{id}/referrals` GET | `encounters.referrals.list` | ✅ Aligned |
| `new_encounter.blade.php` | `/encounters/{id}/referrals` POST | `encounters.referrals.create` | ✅ Aligned |
| `new_encounter.blade.php` | `/encounters/{id}/schedule-followup` POST | `encounters.schedule-followup` | ✅ Aligned |
| `new_encounter.blade.php` | follow-up time slots via `appointments.available-slots` | `appointments.available-slots` | ✅ Aligned (uses named route) |
| `reception/workbench.blade.php` | Calendar events via `appointments.calendar-events` | `appointments.calendar-events` | ✅ Aligned (uses named route) |
| `reception/workbench.blade.php` | Check-in via `/appointments/{id}/check-in` | `appointments.check-in` | ✅ Aligned |
| `reception/workbench.blade.php` | Referrals via `referrals.pending` | `referrals.pending` | ✅ Aligned |
| `my_queues.blade.php` | Queue counts via `appointments.doctor.queue-counts` | `appointments.doctor.queue-counts` | ✅ Aligned |

### 14.7 Cross-View CSS Consistency

| View | `queue-status.css` Linked | Badge Classes Used |
|---|---|---|
| `reception/workbench.blade.php` | ✅ L8 | `.queue-status-badge`, `.queue-source-badge` |
| `nursing/workbench.blade.php` | ✅ L8 | `.queue-source-badge`, `.source-appointment`, `.source-walkin`, `.source-emergency` |
| `doctors/my_queues.blade.php` | ✅ L7 | Badge classes + `.mini-timer`, `.timer-paused` |
| `doctors/new_encounter.blade.php` | ❌ Not linked (timer has its own inline CSS) | Timer uses `.consultation-timer-widget` (inline) — no conflict |

### 14.8 Priority Remediation Roadmap

| Priority | Gap | Effort | Description |
|---|---|---|---|
| **P0** | — | — | *No P0 gaps — all critical paths are functional* |
| ~~**P1**~~ | ~~GAP-6~~ | ~~1–2 hrs~~ | ✅ Seeded `clinic_schedules` (31 clinics) + `doctor_availabilities` (39 doctors) |
| ~~**P1**~~ | ~~GAP-1~~ | ~~2 hrs~~ | ✅ Pre-paid follow-up indicator + upcoming appointments in reception patient load |
| ~~**P1**~~ | ~~GAP-7~~ | ~~15 min~~ | ✅ Cleaned finalize fallback — removed redundant `consultation_ended_at`, added `Log::warning()` |
| ~~**P1**~~ | ~~DI-4~~ | ~~30 min~~ | ✅ Verified clean — 0 rows with status=0 |
| ~~**P2**~~ | ~~GAP-2~~ | ~~1 hr~~ | ✅ Follow-up chain + prepaid badges in doctor queue DataTable |
| ~~**P2**~~ | ~~GAP-3~~ | ~~1 hr~~ | ✅ Reassign button + SweetAlert2 modal in doctor's scheduled view |
| ~~**P2**~~ | ~~GAP-4~~ | ~~2 hrs~~ | ✅ Nurse consulting queue with mini-timers (new endpoint + UI) |
| ~~**P1**~~ | ~~GAP-8~~ | ~~30 min~~ | ✅ Fixed hardcoded status integers + bug (`consultations_done` counted IN_CONSULTATION instead of COMPLETED) + removed dead `getQueueStatusText()` in ReceptionWorkbenchController |
| ~~**P1**~~ | ~~GAP-9~~ | ~~10 min~~ | ✅ Fixed hardcoded status integer in EmergencyIntakeController |
| ~~**P2**~~ | ~~GAP-5~~ | ~~—~~ | ✅ BY DESIGN — calendar is inline in workbench for context; no reusable extraction needed |
| ~~**P1**~~ | ~~GAP-11 (6.10)~~ | ~~3 hrs~~ | ✅ Double-booking detection in `AppointmentSlotService` + integrated in create/reschedule |
| ~~**P2**~~ | ~~GAP-10~~ | ~~2 hrs~~ | ✅ Migrated `MobileEncounterController` to `QueueStatus` enum with legacy status mapping |
| ~~**P1**~~ | ~~GAP-12~~ | ~~10 min~~ | ✅ Fixed hospital config cache key bug (`application_settings` → `clearAppSettingsCache()`) |
| **P2** | 6.1 | 4 hrs | Doctor availability management UI (CRUD for weekly schedule) |
| **P2** | 6.2 | 4 hrs | Clinic schedule management UI (admin) |
| **P2** | 6.14 | 4 hrs | Enable Laravel Broadcasting for real-time status sync |
| **P2** | 6.18 | 6 hrs | Drag-and-drop rescheduling on calendar view |
| **P2** | 6.19 | 3 hrs | Referral letter PDF generation |
| **P3** | 6.15 | 8 hrs | Patient self-service booking portal |
| **P3** | 6.16 | 4 hrs | Recurring appointments |
| **P3** | 6.17 | 6 hrs | Appointment analytics dashboard |
| **P3** | 6.20 | 3 hrs | Referral analytics |
| **P3** | 6.21 | 4 hrs | Follow-up reminder notifications (SMS/push) |
| **P3** | 6.22 | 4 hrs | Auto-suggest follow-up date based on diagnosis |
| **P3** | 6.23 | 3 hrs | Reassignment analytics |

### 14.9 Custom Slot Enhancement (2026-03-05)

**Status:** ✅ IMPLEMENTED
**Description:** Enhanced the reception workbench's appointment booking time slot selection to auto-suggest custom time entry when no preset slots are available.

**Changes applied to `reception/workbench.blade.php`:**
1. **Auto-enable custom time:** When `loadAvailableSlots()` returns zero available slots, the custom time toggle is automatically checked and the manual time input is shown.
2. **Visual hint:** An info alert ("No preset slots available for this date. Enter a custom time below.") appears below the manual input field.
3. **Booked slot visibility:** When slots are returned, booked (unavailable) slots are now shown as disabled `<option>` elements with "(booked)" suffix, giving the receptionist visibility into the full schedule.
4. **Smart reset:** If slots become available again (e.g., date change), the auto-enabled custom toggle is reset back to the dropdown view.
5. **Hint cleanup:** The custom time hint is removed when toggling or when new slots are loaded.
