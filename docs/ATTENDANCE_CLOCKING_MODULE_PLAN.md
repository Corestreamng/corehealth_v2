# Staff Attendance Clocking Module — Implementation Plan

> **Status:** Planning  
> **Date:** April 2026  
> **Module Scope:** Clock-in / Clock-out, HR Workbench hub, ESS portal view, REST API, Flutter kiosk terminal (Windows + Android), ZKTeco USB fingerprint support  
> **Payroll Integration:** Informational only (V1)

---

## Table of Contents

1. [Overview](#1-overview)
2. [Architecture Summary](#2-architecture-summary)
3. [Database Schema](#3-database-schema)
4. [Business Rules](#4-business-rules)
5. [API Design](#5-api-design)
6. [HR Workbench Hub](#6-hr-workbench-hub)
7. [ESS Portal View](#7-ess-portal-view)
8. [Flutter Terminal App](#8-flutter-terminal-app)
9. [ZKTeco Fingerprint Integration](#9-zkteco-fingerprint-integration)
10. [Payroll Integration (Informational)](#10-payroll-integration-informational)
11. [Permissions](#11-permissions)
12. [Implementation Phases](#12-implementation-phases)
13. [Testing Matrix](#13-testing-matrix)
14. [Risks and Mitigations](#14-risks-and-mitigations)

---

## 1. Overview

### 1.1 What This Module Does

| Capability | Who Uses It |
|---|---|
| Clock in and out via terminal app or ESS | Every staff member |
| View personal attendance history | Staff via ESS |
| View full attendance log and exceptions | HR / Admin via HR Workbench |
| Configure attendance policies | HR / Admin |
| Register and manage clock terminals | Admin |
| Provide monthly attendance summary to payroll | HR / Payroll Officer (read-only) |

### 1.2 What Is Explicitly Excluded in V1

- Shift scheduling engine
- Automatic overtime calculation
- Break tracking (mid-shift breaks)
- Salary deductions based on attendance
- Offline sync queue (online-first only in V1)
- Self-service biometric enrollment via ESS (enrollment done at HR)

### 1.3 Key Design Principles

- Every punch is a raw immutable event. Summaries are derived, never authoritative.
- All corrections to raw events go through an explicit HR adjustment workflow with audit trail.
- The terminal app is stateless — it holds no employee data locally beyond the session.
- Payroll reads attendance data but attendance never writes to payroll.

---

## 2. Architecture Summary

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        ATTENDANCE MODULE ARCHITECTURE                        │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌──────────────────────┐   ┌───────────────────┐   ┌───────────────────┐  │
│  │  Flutter Terminal App │   │  CoreHealth Web   │   │  CoreHealth Web   │  │
│  │  (Windows / Android) │   │  HR Workbench     │   │  ESS Portal       │  │
│  └──────────┬───────────┘   └─────────┬─────────┘   └─────────┬─────────┘  │
│             │                         │                         │           │
│             ▼                         ▼                         ▼           │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │                   Attendance REST API                                │   │
│  │  /api/attendance/*                                                   │   │
│  │  Terminal token auth (short-lived) │ Sanctum user token (ESS/HR)    │   │
│  └────────────────────────────┬────────────────────────────────────────┘   │
│                               │                                             │
│  ┌────────────────────────────▼────────────────────────────────────────┐   │
│  │                   AttendanceService (PHP / Laravel)                  │   │
│  │  punch logic · duplicate guard · daily summary · exceptions          │   │
│  └────────────────────────────┬────────────────────────────────────────┘   │
│                               │                                             │
│  ┌────────────────────────────▼────────────────────────────────────────┐   │
│  │                   MySQL / Attendance Tables                           │   │
│  │  attendance_events · attendance_daily_summaries                       │   │
│  │  attendance_terminals · attendance_policies · attendance_adjustments  │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │  Flutter Terminal — Native Scanner Bridge                            │   │
│  │  ZKTeco SDK (Windows: ZKFinger SDK DLL │ Android: ZKFinger AAR)     │   │
│  │  ─── Platform Channel ──► Dart AttendanceTerminalApp                │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 2.1 Laravel Side (CoreHealth Server)

| Component | Location |
|---|---|
| Migration files | `database/migrations/attendance/` |
| Models | `app/Models/Attendance/` |
| Service | `app/Services/AttendanceService.php` |
| Controller (API) | `app/Http/Controllers/API/AttendanceApiController.php` |
| Controller (Web HR) | `app/Http/Controllers/HR/AttendanceWorkbenchController.php` |
| Controller (Web ESS) | `app/Http/Controllers/HR/EssAttendanceController.php` |
| Routes (API) | Added to `routes/api.php` under prefix `attendance` |
| Routes (HR) | Added to `routes/hr.php` |
| Permissions seeder | `database/seeders/AttendancePermissionsSeeder.php` |
| Views (HR) | `resources/views/admin/hr/attendance/` |
| Views (ESS) | `resources/views/admin/ess/attendance/` |

### 2.2 Flutter Terminal App (Separate Repo)

| Component | Details |
|---|---|
| Repository | Separate: `corehealth_attendance_terminal` |
| Target platforms | Android (API 26+), Windows (Win 10+) |
| Architecture | Clean architecture, feature-first folders |
| State management | Riverpod |
| HTTP client | Dio with interceptors |
| Local storage | SharedPreferences (server URL + terminal token) |
| Scanner bridge | Platform Channels to ZKTeco native SDK |

---

## 3. Database Schema

### 3.1 `attendance_terminals`

```sql
CREATE TABLE attendance_terminals (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(120) NOT NULL,            -- "Main Gate Terminal", "Nurses Station"
    terminal_code   VARCHAR(40)  NOT NULL UNIQUE,     -- issued on registration
    platform        ENUM('android','windows','web','unknown') DEFAULT 'unknown',
    token_hash      VARCHAR(255) NOT NULL,            -- bcrypt of issued token
    token_last_used TIMESTAMP    NULL,
    location_note   VARCHAR(255) NULL,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    registered_by   BIGINT UNSIGNED NULL,
    last_seen_at    TIMESTAMP    NULL,
    created_at      TIMESTAMP    NULL,
    updated_at      TIMESTAMP    NULL,

    FOREIGN KEY (registered_by) REFERENCES users(id) ON DELETE SET NULL
);
```

### 3.2 `attendance_policies`

One policy per facility in V1 (HR can add unit-level policies later).

```sql
CREATE TABLE attendance_policies (
    id                       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name                     VARCHAR(120) NOT NULL DEFAULT 'Default Policy',
    expected_in_time         TIME         NOT NULL DEFAULT '08:00:00',
    expected_out_time        TIME         NOT NULL DEFAULT '17:00:00',
    grace_period_minutes     SMALLINT     NOT NULL DEFAULT 15,   -- before late flag
    min_half_day_minutes     SMALLINT     NOT NULL DEFAULT 240,  -- 4 hrs = half day
    is_default               TINYINT(1)   NOT NULL DEFAULT 1,
    created_at               TIMESTAMP    NULL,
    updated_at               TIMESTAMP    NULL
);
```

### 3.3 `attendance_events`

The source of truth. Never updated — only inserted (and soft audit on corrections).

```sql
CREATE TABLE attendance_events (
    id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id              BIGINT UNSIGNED NOT NULL,
    event_type            ENUM('clock_in','clock_out') NOT NULL,
    event_at              DATETIME        NOT NULL,              -- server-normalized time
    source                ENUM('terminal','ess','mobile','admin_manual') NOT NULL,
    terminal_id           BIGINT UNSIGNED NULL,
    verification_method   ENUM('fingerprint','password','admin_override') NOT NULL DEFAULT 'password',
    verification_passed   TINYINT(1)      NOT NULL DEFAULT 1,
    ip_address            VARCHAR(45)     NULL,
    notes                 TEXT            NULL,                  -- admin manual reason
    is_voided             TINYINT(1)      NOT NULL DEFAULT 0,
    voided_by             BIGINT UNSIGNED NULL,
    voided_at             TIMESTAMP       NULL,
    void_reason           TEXT            NULL,
    created_at            TIMESTAMP       NULL,
    updated_at            TIMESTAMP       NULL,

    FOREIGN KEY (staff_id)    REFERENCES staff(id)  ON DELETE CASCADE,
    FOREIGN KEY (terminal_id) REFERENCES attendance_terminals(id) ON DELETE SET NULL,
    FOREIGN KEY (voided_by)   REFERENCES users(id)  ON DELETE SET NULL,

    INDEX idx_staff_event_at (staff_id, event_at),
    INDEX idx_event_at (event_at)
);
```

### 3.4 `attendance_daily_summaries`

Derived and re-computed whenever events for a day change.

```sql
CREATE TABLE attendance_daily_summaries (
    id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id          BIGINT UNSIGNED NOT NULL,
    work_date         DATE            NOT NULL,
    first_in_at       DATETIME        NULL,
    last_out_at       DATETIME        NULL,
    total_minutes     SMALLINT        NOT NULL DEFAULT 0,
    late_minutes      SMALLINT        NOT NULL DEFAULT 0,
    status            ENUM(
                        'present',
                        'present_late',
                        'half_day',
                        'incomplete',    -- clocked in, no clock-out
                        'absent',
                        'on_leave',
                        'holiday',
                        'weekend'
                      ) NOT NULL DEFAULT 'absent',
    computed_at       TIMESTAMP       NULL,
    created_at        TIMESTAMP       NULL,
    updated_at        TIMESTAMP       NULL,

    UNIQUE KEY uq_staff_date (staff_id, work_date),
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,

    INDEX idx_work_date (work_date),
    INDEX idx_status (status)
);
```

### 3.5 `attendance_adjustments`

HR corrections, each linked to voided original events.

```sql
CREATE TABLE attendance_adjustments (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id        BIGINT UNSIGNED NOT NULL,
    work_date       DATE            NOT NULL,
    adjusted_by     BIGINT UNSIGNED NOT NULL,
    reason          TEXT            NOT NULL,
    before_summary  JSON            NULL,    -- snapshot of daily summary before
    after_summary   JSON            NULL,    -- snapshot after
    created_at      TIMESTAMP       NULL,
    updated_at      TIMESTAMP       NULL,

    FOREIGN KEY (staff_id)    REFERENCES staff(id)  ON DELETE CASCADE,
    FOREIGN KEY (adjusted_by) REFERENCES users(id)  ON DELETE RESTRICT
);
```

---

## 4. Business Rules

### 4.1 Punch State Machine

```
No open session
      │
      ▼  clock_in
  CLOCKED IN ──────────────────► open session created
      │
      ▼  clock_out
  CLOCKED OUT ─────────────────► session closed, summary recomputed
```

| Rule | Behavior |
|---|---|
| Clock in when already clocked in | Reject: "You are already clocked in since HH:MM" |
| Clock out without open session | Reject: "No active session to close" |
| Admin can override either | Allowed with mandatory reason, recorded as `admin_manual` source |
| Fingerprint fail | Log failed attempt (not stored as event), prompt fallback |
| Duplicate submission (same second, same staff) | Idempotent guard on `(staff_id, event_at)` ±5 seconds |

### 4.2 Daily Summary Recomputation

The `AttendanceService::recomputeDay(staff_id, date)` method:
1. Queries all non-voided events for the staff/date.
2. Takes the first `clock_in` as `first_in_at`.
3. Takes the last `clock_out` as `last_out_at`.
4. Computes `total_minutes = last_out_at - first_in_at`.
5. Applies policy grace period to set `late_minutes`.
6. Sets `status` based on `total_minutes` vs policy thresholds.
7. Upserts `attendance_daily_summaries`.

### 4.3 Late Flag Logic

```
expected_in_time = 08:00
grace_period_minutes = 15
late_threshold = 08:15

first_in_at > 08:15 ──► late_minutes = (first_in_at - expected_in_time)
first_in_at ≤ 08:15 ──► late_minutes = 0
```

### 4.4 Payroll Cutoff Lock

Once a payroll batch is marked `approved` or `paid` for a period, adjustments to attendance events within that period require the `attendance.adjust.locked-period` permission. Standard HR role cannot override a locked period.

---

## 5. API Design

### 5.1 Base Path and Auth

```
/api/attendance/*
```

| Consumer | Auth Method |
|---|---|
| Terminal app | Bearer token (issued at terminal registration, hashed in DB) |
| ESS (web / mobile) | Sanctum user session or token |
| HR Workbench (web) | Sanctum user session |

### 5.2 Public Bootstrap (No Auth)

```
GET /api/attendance/instance-info
```

Response:
```json
{
  "facility_name": "CoreHealth General Hospital",
  "facility_logo_url": "https://...",
  "timezone": "Africa/Lagos",
  "attendance_enabled": true,
  "fingerprint_supported": true,
  "server_time": "2026-04-21T08:00:00+01:00"
}
```

Purpose: The terminal app calls this immediately after the user enters the server URL to confirm the instance is valid and attendance is enabled — same pattern as the existing mobile `instance-info` endpoint.

### 5.3 Terminal Registration (Admin Web, Not API)

Terminals are registered in HR Workbench. The admin names the terminal, and the system generates a one-time `terminal_code` and `token`. The operator enters this into the Flutter app during initial setup. After this, the app stores the token in SharedPreferences and uses it for all punches.

```
POST /api/attendance/terminal/verify
Body: { "terminal_code": "...", "token": "..." }
Response: { "accepted": true, "terminal_name": "Main Gate", "platform_confirmed": "windows" }
```

### 5.4 Clock In

```
POST /api/attendance/clock-in

Headers:
  Authorization: Bearer <terminal_token>

Body:
{
  "staff_identifier": "EMP-0042",   // or staff_id integer
  "event_at": "2026-04-21T08:04:00+01:00",
  "verification_method": "fingerprint",
  "verification_passed": true,
  "idempotency_key": "uuid-v4"
}

Response 200:
{
  "success": true,
  "event_id": 1234,
  "message": "Welcome, Adaeze Nwosu. Clocked in at 08:04.",
  "staff_name": "Adaeze Nwosu",
  "staff_photo_url": "...",
  "status": "on_time"
}

Response 422 (already clocked in):
{
  "success": false,
  "code": "already_clocked_in",
  "message": "Adaeze Nwosu is already clocked in since 07:58."
}
```

### 5.5 Clock Out

```
POST /api/attendance/clock-out

Headers:
  Authorization: Bearer <terminal_token>

Body:
{
  "staff_identifier": "EMP-0042",
  "event_at": "2026-04-21T17:02:00+01:00",
  "verification_method": "fingerprint",
  "verification_passed": true,
  "idempotency_key": "uuid-v4"
}

Response 200:
{
  "success": true,
  "event_id": 1235,
  "message": "Goodbye, Adaeze Nwosu. Clocked out at 17:02. Total: 9h 04m.",
  "staff_name": "Adaeze Nwosu",
  "total_minutes": 544,
  "status": "present"
}
```

### 5.6 Staff Lookup (for terminal search/display)

```
GET /api/attendance/staff/lookup?q=EMP-0042
Authorization: Bearer <terminal_token>

Response:
{
  "found": true,
  "staff_id": 42,
  "name": "Adaeze Nwosu",
  "employee_id": "EMP-0042",
  "unit": "Nursing",
  "photo_url": "...",
  "current_status": "clocked_out"   // or "clocked_in"
}
```

### 5.7 ESS — My Attendance (Sanctum Auth)

```
GET /api/attendance/me/today
GET /api/attendance/me/history?month=2026-04
```

Today response:
```json
{
  "date": "2026-04-21",
  "status": "clocked_in",
  "first_in_at": "08:04",
  "last_out_at": null,
  "total_minutes": null
}
```

History response:
```json
{
  "month": "2026-04",
  "days_present": 16,
  "days_late": 2,
  "days_absent": 2,
  "incomplete_days": 1,
  "records": [
    {
      "date": "2026-04-01",
      "status": "present",
      "first_in_at": "07:58",
      "last_out_at": "17:05",
      "total_minutes": 547,
      "late_minutes": 0
    }
  ]
}
```

### 5.8 HR Workbench Endpoints (Sanctum Auth)

```
GET  /api/attendance/workbench/today-overview
GET  /api/attendance/workbench/exceptions?date=2026-04-21
GET  /api/attendance/workbench/staff-list?month=2026-04&status=late
POST /api/attendance/adjustments
```

Today overview response:
```json
{
  "date": "2026-04-21",
  "total_staff": 150,
  "present": 131,
  "late": 8,
  "absent": 11,
  "incomplete": 6,
  "on_leave": 2
}
```

### 5.9 Payroll Summary Feed (Informational)

```
GET /api/attendance/payroll/monthly-summary?month=2026-04
Authorization: Sanctum (permission: attendance.payroll-summary.view)

Response:
{
  "month": "2026-04",
  "generated_at": "2026-04-30T17:00:00+01:00",
  "staff": [
    {
      "staff_id": 42,
      "employee_id": "EMP-0042",
      "name": "Adaeze Nwosu",
      "days_present": 20,
      "days_half": 1,
      "days_incomplete": 0,
      "days_absent": 1,
      "days_on_leave": 0,
      "total_attended_hours": 181.5
    }
  ]
}
```

---

## 6. HR Workbench Hub

### 6.1 Location in Existing Workbench

The attendance section is added as a new tab/panel inside `resources/views/admin/hr/workbench/index.blade.php`, following the same card + sidebar pattern as the rest of the HR workbench.

A dedicated route also exists at `hr/attendance` for deep-link access.

### 6.2 Dashboard Cards (Today)

```
┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐
│   Present    │  │   Late       │  │   Absent     │  │  Incomplete  │
│     131      │  │    8         │  │    11        │  │      6       │
│  of 150 staff│  │              │  │              │  │ no clock-out │
└──────────────┘  └──────────────┘  └──────────────┘  └──────────────┘
```

### 6.3 Exceptions Table

Columns: Staff Name | Employee ID | Unit | Status | First In | Last Out | Late (mins) | Action

Actions per row:
- View detail → slide-out panel with all raw events for that day
- Adjust → opens modal: select correction action, enter reason, confirm

### 6.4 Staff Monthly View

- DataTable showing all staff with month-to-date totals
- Filter by: unit, cadre, status
- Export CSV for payroll reference

### 6.5 Policy Management Page

Route: `hr/attendance/policies`

Simple form to configure:
- Expected in/out time
- Grace period minutes
- Half-day threshold minutes

### 6.6 Terminal Management Page

Route: `hr/attendance/terminals`

Table: terminal name, platform, last seen, status (active/inactive)
Actions: Register new, revoke token, re-issue token

Registration modal:
- Enter name and location note
- Select platform
- System generates terminal code + token shown once
- Admin copies credentials and enters them into the Flutter app

---

## 7. ESS Portal View

### 7.1 Location

Added to the existing ESS portal under a new "My Attendance" menu item, consistent with existing ESS items (my-leave, my-payslips, etc.).

Route: `hr/ess/my-attendance`

### 7.2 Page Layout

```
┌────────────────────────────────────────────────────────┐
│  MY ATTENDANCE                          April 2026  ▼  │
├───────────────┬───────────────┬──────────┬─────────────┤
│  Days Present │  Days Late    │  Absent  │ On Leave    │
│      16       │       2       │    2     │     0       │
├───────────────┴───────────────┴──────────┴─────────────┤
│  DATE          IN       OUT     HOURS    STATUS        │
│  Mon 21 Apr   08:04    17:02   9h 04m   ● Present     │
│  Sat 19 Apr    —        —        —      ○ Weekend     │
│  Fri 18 Apr   08:32    17:00   8h 28m   ▲ Late        │
│  ...                                                   │
│  [Request Correction]                                  │
└────────────────────────────────────────────────────────┘
```

- Month selector (same control as ESS leave calendar)
- Status colour: green = present, amber = late/incomplete, red = absent
- "Request Correction" button opens a form: select date, describe issue, submit → creates an HR follow-up task

---

## 8. Flutter Terminal App

### 8.1 Target Platforms

| Platform | Minimum Version | Deployment Mode |
|---|---|---|
| Android | API 26 (Android 8.0+) | APK sideload or internal track |
| Windows | Windows 10 (x64) | MSIX or zip distribution |

macOS and Linux are supported by Flutter but not targeted in V1.

### 8.2 App Repository Structure

```
corehealth_attendance_terminal/
├── android/
│   ├── app/src/main/
│   │   └── java/.../FingerScannerPlugin.java   ← ZKFinger Android bridge
│   └── ...
├── windows/
│   ├── runner/
│   │   └── finger_scanner_plugin.cpp           ← ZKFinger Windows bridge
│   └── ...
├── lib/
│   ├── main.dart
│   ├── app.dart
│   ├── features/
│   │   ├── setup/                  ← server URL + terminal activation
│   │   │   ├── setup_screen.dart
│   │   │   └── setup_provider.dart
│   │   ├── clock/                  ← main kiosk screen
│   │   │   ├── clock_screen.dart
│   │   │   ├── staff_lookup_widget.dart
│   │   │   ├── fingerprint_widget.dart
│   │   │   └── clock_provider.dart
│   │   └── history/                ← optional per-staff quick view
│   ├── services/
│   │   ├── api_service.dart
│   │   └── scanner_service.dart    ← abstract interface
│   └── models/
│       ├── punch_result.dart
│       └── staff_lookup_result.dart
└── pubspec.yaml
```

### 8.3 Setup Flow (First Run)

Mirrors the existing CoreHealth mobile app onboarding:

```
┌────────────────────────────────────────────┐
│  CoreHealth Attendance Terminal            │
│                                            │
│  Server Address                            │
│  ┌──────────────────────────────────────┐  │
│  │ https://yourhospital.corehealthng.com│  │
│  └──────────────────────────────────────┘  │
│                                            │
│  [ Verify Server ]                         │
│                                            │
│  ✓ CoreHealth General Hospital             │
│    Lagos, Nigeria · v2.x                   │
│                                            │
│  Terminal Code    [ ____________ ]         │
│  Terminal Token   [ ____________ ]         │
│                                            │
│  [ Activate Terminal ]                     │
└────────────────────────────────────────────┘
```

After successful activation, credentials are stored in SharedPreferences and the app moves to the clock screen. The setup screen only re-appears if the token is revoked or the user manually resets.

### 8.4 Main Clock Screen

Optimized for shared kiosk use — single screen, no navigation clutter.

```
┌────────────────────────────────────────────────────────────────┐
│  CoreHealth ·  08:14 · Monday 21 April 2026                    │
├────────────────────────────────────────────────────────────────┤
│                                                                │
│   Search staff:  [ EMP-0042 or name __________________ ] 🔍   │
│                                                                │
│   ┌──────────────────────────────────────────┐                │
│   │  Adaeze Nwosu                            │                │
│   │  Nurse · Nursing Unit                    │                │
│   │  [photo]  Status: ● NOT YET CLOCKED IN  │                │
│   └──────────────────────────────────────────┘                │
│                                                                │
│        ┌─────────────────────────────────┐                    │
│        │  Place finger on scanner...     │                    │
│        │         [fingerprint icon]      │                    │
│        │  ── or ──                       │                    │
│        │  [ Skip Fingerprint (PIN) ]     │                    │
│        └─────────────────────────────────┘                    │
│                                                                │
│   ┌───────────────────┐    ┌───────────────────┐              │
│   │   CLOCK IN        │    │   CLOCK OUT       │              │
│   │  (btn-primary)    │    │  (btn-secondary)  │              │
│   └───────────────────┘    └───────────────────┘              │
│                                                                │
├────────────────────────────────────────────────────────────────┤
│  Last action: Emeka Okafor clocked in at 08:09                │
└────────────────────────────────────────────────────────────────┘
```

Behaviour:
- After a successful punch, the screen clears to initial state after 4 seconds.
- Failed fingerprint shows amber warning, allows retry or PIN fallback.
- Buttons are disabled until a staff member is identified.

### 8.5 Key Dependencies (pubspec.yaml)

```yaml
dependencies:
  flutter: { sdk: flutter }
  dio: ^5.4.0                  # HTTP client
  riverpod: ^2.5.1             # state management
  shared_preferences: ^2.2.2   # server URL + token persistence
  intl: ^0.19.0                # date/time formatting
  cached_network_image: ^3.3.1 # staff photos

dev_dependencies:
  flutter_test: { sdk: flutter }
  mocktail: ^1.0.0
```

Scanner bridge is native (no pub package) — see Section 9.

---

## 9. ZKTeco Fingerprint Integration

### 9.1 Why ZKTeco

| Factor | ZKTeco |
|---|---|
| Market presence in Nigeria | Dominant — sold in virtually every computer/security shop |
| Device availability | Nationwide, ₦10,000–₦40,000 range for USB scanners |
| SDK availability | Free SDK download for Windows (DLL) and Android (AAR) |
| Recommended models | ZK9500, ZK7500, ZK6000 (all USB, all tested in West Africa) |
| Support | SDK docs in English, active online communities |

### 9.2 ZKTeco SDK Overview

**Windows:** `ZKFinger SDK` — native DLL (`libzkfp.dll`, `zkfp.dll`)
- C API exposed via P/Invoke or JNA
- Functions: `ZKFP_Init`, `ZKFP_OpenDevice`, `ZKFP_GetImage`, `ZKFP_Extract`, `ZKFP_Identify`, `ZKFP_CloseDevice`

**Android:** `ZKFingerSDK-{version}.aar`
- Java/Kotlin API
- Works via USB Host OTG (Android 8+)
- Functions: `FingerprintReader.open()`, `FingerprintReader.startCapture()`, `FingerprintReader.closeDevice()`

### 9.3 Flutter Platform Channel Bridge

The Flutter app defines an abstract `ScannerService` interface. Each platform implements it via a MethodChannel.

```dart
// lib/services/scanner_service.dart
abstract class ScannerService {
  Future<bool> initialize();
  Future<ScannerStatus> getStatus();
  Future<FingerprintTemplate?> enroll(int staffId);  // only at HR terminal
  Future<VerifyResult> verify(FingerprintTemplate storedTemplate);
  Future<void> dispose();
}

class VerifyResult {
  final bool passed;
  final int confidence;       // 0–100 from SDK match score
  final String? errorMessage;
}
```

The `MethodChannel('com.corehealth/zkfinger')` is implemented:
- In `FingerScannerPlugin.java` (Android)
- In `finger_scanner_plugin.cpp` (Windows via Flutter's C++ plugin API)

### 9.4 Fingerprint Template Storage

Templates are stored **server-side** in a separate `staff_fingerprint_templates` table:

```sql
CREATE TABLE staff_fingerprint_templates (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id        BIGINT UNSIGNED NOT NULL UNIQUE,
    template_data   LONGBLOB        NOT NULL,   -- encrypted AES-256
    sdk_version     VARCHAR(20)     NOT NULL,
    enrolled_by     BIGINT UNSIGNED NULL,
    enrolled_at     TIMESTAMP       NULL,
    created_at      TIMESTAMP       NULL,
    updated_at      TIMESTAMP       NULL,

    FOREIGN KEY (staff_id)    REFERENCES staff(id) ON DELETE CASCADE,
    FOREIGN KEY (enrolled_by) REFERENCES users(id) ON DELETE SET NULL
);
```

- Encrypted at rest using Laravel's `Crypt::encrypt()` before storage.
- Templates are never returned to the terminal as raw data.
- Verification flow: terminal captures sample → sends to server → server decrypts stored template → SDK match → returns pass/fail + confidence.

> **V1 simplification:** Template matching happens server-side via a thin verification endpoint. This avoids needing to push templates to terminals (which is a security risk) and removes the need for per-terminal template sync.

### 9.5 Verification API Endpoint

```
POST /api/attendance/fingerprint/verify
Authorization: Bearer <terminal_token>

Body:
{
  "staff_id": 42,
  "sample": "<base64-encoded raw scan from ZK SDK>"
}

Response 200:
{
  "passed": true,
  "confidence": 87,
  "staff_name": "Adaeze Nwosu"
}

Response 401:
{
  "passed": false,
  "confidence": 0,
  "message": "Fingerprint did not match"
}
```

### 9.6 Enrollment Flow (HR Terminal Only)

Enrollment is done once by HR when onboarding staff. It is not available in the kiosk clock screen.

1. HR logs in to web portal → Staff profile → Biometrics tab.
2. Page prompts HR to open the enrollment app (a mode flag in the same Flutter app, activated by HR login, not terminal token).
3. Flutter app captures 3 scans, averages them via ZK SDK's `ZKFP_GenRegTemplate`.
4. Sends encrypted template to `POST /api/attendance/fingerprint/enroll`.
5. Staff profile shows "Fingerprint enrolled — [date]".

### 9.7 Fallback Handling

| Scenario | Behaviour |
|---|---|
| No scanner connected | Scanner UI hidden; PIN/password prompt shown automatically |
| Scanner connected but fails to init | Warning toast; fallback to PIN |
| Low confidence match (< 60) | Treat as fail; allow 2 retries then fallback to PIN |
| Staff not enrolled | Skip fingerprint, go directly to PIN |
| Staff on policy with mandatory fingerprint | Punch recorded with `verification_method=password`, flagged for HR review |

---

## 10. Payroll Integration (Informational)

### 10.1 Principle

Attendance data in V1 does **not** drive any automatic payroll computation, deduction, or blocking. It serves as a reference layer that payroll officers consult during payroll preparation.

### 10.2 How It Appears in Payroll

During payroll batch generation (`PayrollBatchController::show`), a new "Attendance Summary" panel appears alongside each staff member's salary breakdown:

```
┌──────────────────────────────────────────────────────┐
│  Attendance Summary — April 2026                     │
│  Days Present:        20                             │
│  Days Late:            2                             │
│  Days Absent:          1                             │
│  Half Days:            0                             │
│  Days on Leave:        1                             │
│  Total Attended Hours: 181.5 h                       │
│  [View Full Log →]                                   │
└──────────────────────────────────────────────────────┘
```

This data is fetched from `attendance_daily_summaries` at runtime. No data is copied into payroll tables.

### 10.3 Payroll CSV Export

Payroll batch export (existing functionality) is extended to include attendance columns:
- `days_present`, `days_late`, `days_absent`, `total_hours`

These are labelled "Attendance Reference (informational)" in the export header.

### 10.4 Upgrade Path to V2 (Future)

When the organization is ready to enforce attendance-based deductions:
1. Add a flag to `attendance_policies`: `deduction_enabled` + `deduction_per_absent_day`.
2. The `PayrollBatchController::generate()` method can then call `AttendanceService::getDeductionAmount(staff_id, month)`.
3. This is a zero-disruption upgrade since the data layer is already in place.

---

## 11. Permissions

| Permission | Description |
|---|---|
| `attendance.workbench.access` | View HR attendance dashboard |
| `attendance.policy.manage` | Create and edit attendance policies |
| `attendance.view-all` | View all staff attendance records |
| `attendance.adjust` | Correct attendance events for any staff |
| `attendance.adjust.locked-period` | Adjust events in a payroll-locked period |
| `attendance.terminal.manage` | Register, revoke, and manage terminals |
| `attendance.payroll-summary.view` | Access the monthly payroll summary feed |
| `ess.attendance.view-own` | View own attendance records in ESS |
| `ess.attendance.request-correction` | Submit correction request via ESS |
| `attendance.fingerprint.enroll` | Enroll staff fingerprint templates |

All permissions are added to `AttendancePermissionsSeeder` and grouped under `Attendance` in the permissions management UI.

---

## 12. Implementation Phases

### Phase 1 — Backend Foundation (Week 1)

| Task | File / Location |
|---|---|
| Create migrations for all 5 tables | `database/migrations/attendance/` |
| Create Eloquent models | `app/Models/Attendance/` |
| Write `AttendanceService` (punch logic, recompute) | `app/Services/AttendanceService.php` |
| Write `AttendanceApiController` (clock-in, clock-out, lookup) | `app/Http/Controllers/API/` |
| Register API routes | `routes/api.php` |
| Add permission seeder | `database/seeders/` |
| Unit tests for punch state rules | `tests/Unit/AttendanceServiceTest.php` |

### Phase 2 — ESS View (Week 2 — first half)

| Task | File / Location |
|---|---|
| `EssAttendanceController` | `app/Http/Controllers/HR/` |
| ESS routes | `routes/hr.php` |
| ESS attendance view blade | `resources/views/admin/ess/attendance/` |
| Add "My Attendance" link to ESS sidebar | ESS layout partial |

### Phase 3 — HR Workbench Hub (Week 2 — second half)

| Task | File / Location |
|---|---|
| `AttendanceWorkbenchController` | `app/Http/Controllers/HR/` |
| HR workbench routes | `routes/hr.php` |
| Today overview partial | `resources/views/admin/hr/attendance/` |
| Exceptions table partial | same |
| Policy management page | same |
| Terminal management page | same |
| Add Attendance section to HR Workbench index | `resources/views/admin/hr/workbench/index.blade.php` |

### Phase 4 — Flutter Terminal App (Weeks 3–4)

| Task | Notes |
|---|---|
| Create Flutter project | `flutter create --platforms=android,windows corehealth_attendance_terminal` |
| Setup screen (server URL + activation) | Mirrors mobile app pattern |
| `ApiService` with Dio | Token auth, error handling, retry on timeout |
| Staff lookup widget | Debounced search, photo display |
| Clock In / Clock Out flow | State managed by Riverpod; idempotency key per action |
| Success/failure feedback screen | Full screen, auto-clears after 4 seconds |
| PIN fallback auth | Used when no scanner or scanner fails |
| Windows and Android build pipeline | GitHub Actions or manual |

### Phase 5 — ZKTeco Fingerprint Integration (Weeks 5–6)

| Task | Notes |
|---|---|
| Define `ScannerService` abstract class | Platform-agnostic interface |
| Android MethodChannel bridge (Java) | ZKFinger AAR integration |
| Windows MethodChannel bridge (C++) | ZKFinger DLL P/Invoke |
| Server-side verification endpoint | Decrypt template, SDK compare, return result |
| Enrollment workflow in web portal | HR staff profile → Biometrics tab |
| `staff_fingerprint_templates` migration and model | Encrypted storage |
| Fallback policy handling | Per policy config |
| Integration tests | Simulated scanner pass/fail flows |

### Phase 6 — Pilot and Rollout (Week 7)

| Task | Notes |
|---|---|
| Deploy to one unit (e.g., Nursing) | Monitor for edge cases |
| Gather attendance data for 2 weeks | Verify summaries match expectations |
| HR reviews payroll summary panel | Confirm informational display is correct |
| Fix edge cases from pilot | Overnight shifts, admin overrides, missed clock-outs |
| Roll out to all units | Staggered by unit |
| Staff communication and brief training | 15-minute session per unit |

---

## 13. Testing Matrix

| Test | Type | Tool |
|---|---|---|
| Punch state machine (double in, out without in) | Unit | PHPUnit |
| Daily summary recomputation | Unit | PHPUnit |
| Late flag logic with grace period | Unit | PHPUnit |
| API clock-in / clock-out with valid terminal token | Feature | PHPUnit |
| Idempotency key deduplication | Feature | PHPUnit |
| ESS own-record access (cannot see other staff) | Feature | PHPUnit |
| HR adjustment flow and audit log | Feature | PHPUnit |
| Terminal token revocation | Feature | PHPUnit |
| Flutter setup screen (valid / invalid server) | Widget | flutter_test |
| Flutter clock flow (success, fail, fallback) | Widget | flutter_test |
| Scanner service mock verify pass/fail | Unit | mocktail |
| Payroll summary panel data accuracy | Feature | PHPUnit |

---

## 14. Risks and Mitigations

| Risk | Likelihood | Mitigation |
|---|---|---|
| ZKTeco SDK version varies by scanner model | Medium | Lock to SDK v9.2+ (supports ZK9500/7500/6000); document tested models |
| Staff forget to clock out | High | Daily "incomplete" exceptions visible in HR dashboard; auto-flag for follow-up |
| Terminal token leaked | Low | Short-lived tokens + IP binding optional; revoke from HR Workbench immediately |
| Android device OTG compatibility with ZK scanner | Medium | Test on target hardware before purchase; ZK9500 widely tested on Android OTG |
| HR disputes over locked-period adjustments | Medium | Strict permission control; require second-level approval for locked-period corrections |
| Network issues at clock point | Medium | Show clear "server unreachable" message; V2 can add local queue |
| Staff enroll on wrong finger | Low | Allow HR to re-enroll; show enrollment date on profile for audit |
| Day-boundary errors for overnight staff | Low | Normalize all times to UTC server time; boundary crossing handled by work-date assignment rule |
