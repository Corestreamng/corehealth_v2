# Attendance Clock In and Clock Out Implementation Plan

> Version: 1.0  
> Date: 21 April 2026  
> Status: Planning  
> Target: CoreHealth HR Workbench + ESS + Attendance Terminal App

---

## 1. Executive Summary

This plan delivers a practical attendance module for CoreHealth that supports:

1. Clock in and clock out tracking for all staff.
2. HR Workbench visibility for HR and admin.
3. ESS visibility for each staff member.
4. API endpoints for terminal and app-based clocking.
5. A dedicated attendance terminal client for Windows and Android.
6. USB fingerprint scanner verification using a popular Nigeria-ready vendor.
7. Informational integration with payroll (no automated payroll deductions in V1).

The implementation is intentionally simplified for faster rollout and lower operational risk.

---

## 2. Scope and Boundaries (V1)

### 2.1 In Scope

1. Clock in and clock out events.
2. Daily attendance summaries generated from events.
3. HR and admin attendance views in HR Workbench.
4. Staff self-view attendance pages in ESS.
5. Attendance API endpoints for terminal and app use.
6. Attendance terminal app with server URL setup (instance-based, like existing mobile apps).
7. Fingerprint verification support for supported scanners.
8. Monthly attendance summary feed for payroll review.

### 2.2 Out of Scope (Deferred)

1. Automated salary deductions based on lateness/absence.
2. Full shift and roster engine.
3. Break in and break out tracking.
4. Complex overtime calculation and payroll automation.
5. Offline-first sync queue with conflict resolution.

---

## 3. Recommended Client Framework and Device Strategy

### 3.1 Framework Choice

Use Flutter for the attendance terminal client.

Reason:

1. Single codebase for Windows and Android.
2. Fast UI delivery for kiosk mode and touch-first terminal screens.
3. Supports custom native plugin channels for fingerprint SDK integration.
4. Aligns with CoreHealth mobile-style instance onboarding UX.

### 3.2 Native Platform Focus

1. Windows desktop kiosks at facility entrances, departments, and admin offices.
2. Android tablets or mini-PC touch terminals where mobility is needed.

### 3.3 Fingerprint Vendor Recommendation (Nigeria)

Recommended vendor: ZKTeco.

Why ZKTeco:

1. Widely deployed in Nigeria for attendance/access control.
2. Strong local reseller and support ecosystem.
3. Popular USB scanners for kiosk use.
4. Known compatibility history with attendance workflows in the region.

Suggested first hardware profile:

1. Windows terminal + USB scanner such as ZKTeco ZK9500 series (or locally available equivalent in that family).
2. Android terminal with OTG-compatible ZKTeco scanner where supported by driver stack.

Important note:

Fingerprint integration is always SDK and OS specific. Flutter handles shared UI and business flow, while scanner capture is implemented via platform-native bridge code for Windows and Android.

---

## 4. Module Architecture

### 4.1 Core Components

1. Attendance Domain (Laravel backend)
2. HR Workbench Attendance Hub
3. ESS Attendance Pages
4. Terminal API Layer
5. Flutter Terminal Client (Windows + Android)
6. Fingerprint Adapter Layer (platform-native)

### 4.2 High-Level Flow

1. Terminal app starts and requests server URL.
2. App validates instance via bootstrap endpoint.
3. Staff identifies self (staff ID or search).
4. Fingerprint verification runs (if enabled).
5. App calls clock in or clock out API.
6. Backend records immutable attendance event.
7. Daily summary is updated.
8. ESS and HR views show updated status and history.

---

## 5. Database Design (Simplified)

### 5.1 attendance_terminals

Purpose: register and manage each terminal device.

Core fields:

1. id
2. terminal_code (unique)
3. terminal_name
4. platform (windows, android)
5. location_label (optional)
6. auth_token_hash
7. is_active
8. last_seen_at
9. created_by
10. timestamps

### 5.2 attendance_events

Purpose: immutable source of truth for every punch.

Core fields:

1. id
2. staff_id
3. event_type (clock_in, clock_out)
4. event_at
5. source (ess, terminal, mobile_api, admin)
6. terminal_id (nullable)
7. verification_method (fingerprint, password, pin, admin_override)
8. verification_status (success, failed, bypassed)
9. client_ip (nullable)
10. device_info (nullable)
11. metadata_json (nullable)
12. created_at

### 5.3 attendance_daily_summaries

Purpose: fast reporting and payroll informational feed.

Core fields:

1. id
2. staff_id
3. work_date
4. first_in_at (nullable)
5. last_out_at (nullable)
6. worked_minutes (default 0)
7. status (present, absent, incomplete)
8. late_flag (boolean default false)
9. created_at
10. updated_at

Unique key:

1. unique(staff_id, work_date)

### 5.4 attendance_adjustments

Purpose: controlled corrections by HR/admin.

Core fields:

1. id
2. staff_id
3. work_date
4. original_summary_json
5. adjusted_summary_json
6. reason
7. adjusted_by
8. approved_by (nullable)
9. timestamps

---

## 6. API Design (V1)

Prefix: /api/attendance

### 6.1 Bootstrap and Terminal Auth

1. GET /instance-info
2. POST /terminal/auth
3. POST /terminal/heartbeat

### 6.2 Clock Actions

1. POST /clock-in
2. POST /clock-out

Clock request payload:

1. staff_identifier
2. event_time_client (optional, server time remains authoritative)
3. verification_method
4. verification_payload (fingerprint token or match result metadata)
5. terminal_code
6. device_platform

Clock response payload:

1. success
2. message
3. event_id
4. server_time
5. today_status

### 6.3 ESS Endpoints

1. GET /me/today
2. GET /me/history
3. GET /me/month-summary

### 6.4 HR Workbench Endpoints

1. GET /workbench/overview
2. GET /workbench/attendance-list
3. GET /workbench/exceptions
4. POST /workbench/adjustments
5. GET /workbench/terminal-status

### 6.5 Payroll Informational Endpoint

1. GET /payroll/monthly-summary

Return columns per staff for a selected month:

1. total_present_days
2. total_incomplete_days
3. total_worked_minutes
4. late_days_count

No payroll mutation endpoint in V1.

---

## 7. Business Rules (V1)

1. One open session at a time per staff.
2. Cannot clock out if there is no prior clock in for the day window.
3. Duplicate punch submissions within a short guard window are ignored.
4. Attendance events are immutable; corrections happen only through adjustments.
5. Server timestamp is final for reporting.
6. Fingerprint failure does not create attendance event.
7. HR/admin adjustments require reason and audit trail.

---

## 8. HR Workbench Integration

Add an Attendance Hub in HR Workbench with:

1. Today stats cards:
	- clocked in now
	- absent today
	- incomplete sessions
2. Attendance list table with filters:
	- date range
	- department/unit
	- status
3. Exceptions panel:
	- missing clock out
	- no clock in
4. Adjustment modal for HR/admin.
5. Terminal management panel:
	- register, disable, rename, view last seen

Permissions:

1. attendance.workbench.access
2. attendance.view-all
3. attendance.adjust
4. attendance.terminal.manage

---

## 9. ESS Integration

Add a My Attendance section in ESS:

1. Today state widget (not clocked in, clocked in, completed).
2. Monthly calendar/list view with daily status.
3. Day detail view:
	- first in
	- last out
	- total worked hours
4. Request correction action (creates adjustment request for HR review).

Permissions:

1. ess.attendance.view-own
2. ess.attendance.request-correction-own

---

## 10. Terminal App Specification (Windows and Android)

### 10.1 Onboarding

1. Enter CoreHealth server URL.
2. Validate with /api/attendance/instance-info.
3. Activate terminal with admin-provided terminal code.
4. Save configuration securely.

### 10.2 Main Screen (Kiosk UX)

1. Prominent Clock In and Clock Out actions.
2. Staff identification input (staff ID or quick search).
3. Fingerprint capture prompt and status.
4. Clear success and error feedback.
5. Auto-reset to idle state after each transaction.

### 10.3 Fingerprint Integration Approach

1. Flutter UI and app flow.
2. Native scanner service bridge for Windows.
3. Native scanner service bridge for Android.
4. Unified interface in app:
	- initScanner
	- captureFingerprint
	- verifyFingerprint
	- scannerHealth

### 10.4 Security Controls

1. Terminal token-based API auth.
2. Short session expiry and token refresh.
3. Device registration and revoke support.
4. TLS required.

---

## 11. Payroll Integration at Informational Level

### 11.1 Design Intent

Attendance data informs payroll review but does not automatically alter payroll values in V1.

### 11.2 What Payroll Can Consume

1. Attendance monthly summary endpoint.
2. CSV export generated from attendance summaries.
3. Optional display panel in payroll batch review screen.

### 11.3 What Payroll Will Not Do in V1

1. No automatic deductions for lateness or absence.
2. No automatic overtime payments.
3. No payroll blocking based on attendance status.

This keeps risk low and gives HR time to validate attendance quality.

---

## 12. Implementation Phases and Timeline

### Phase 1: Backend Foundation (Week 1)

1. Migrations and models.
2. Attendance services and rule engine (basic in/out).
3. API endpoints for clock actions and summaries.
4. Permissions and policy wiring.

### Phase 2: HR and ESS UI (Week 2)

1. HR Workbench attendance hub pages.
2. ESS my-attendance pages.
3. Adjustment workflow.

### Phase 3: Terminal Client (Week 3)

1. Flutter app shell for Windows and Android.
2. Server URL onboarding flow.
3. Terminal activation and token management.
4. Clock in and clock out UX.

### Phase 4: Fingerprint and Pilot (Week 4)

1. ZKTeco adapter integration for selected scanner models.
2. Pilot deployment at one location.
3. Bug fixes and rollout checklist.

---

## 13. Testing Plan

1. Unit tests for attendance state transitions.
2. API tests for clock in, clock out, and guard rules.
3. Role and permission tests (ESS vs HR/Admin).
4. UI tests for ESS and Workbench attendance pages.
5. Device tests:
	- Windows + scanner
	- Android + scanner
6. Pilot validation:
	- successful match rate
	- duplicate punch rejection
	- incomplete session detection

---

## 14. Operational Readiness

1. Terminal setup guide (network, USB driver, app install).
2. HR SOP for adjustments and dispute handling.
3. Daily monitoring dashboard for failed punches.
4. Escalation path for scanner downtime (PIN/password fallback by policy).

---

## 15. Risks and Mitigation

1. Scanner driver differences across OS versions.
	- Mitigation: certify exact scanner model list before full rollout.
2. Unstable network at entry points.
	- Mitigation: use reliable LAN where possible and monitor API latency.
3. Data disputes during early rollout.
	- Mitigation: keep immutable events and structured adjustment workflow.
4. Premature payroll automation.
	- Mitigation: keep payroll integration informational in V1.

---

## 16. Deliverables Checklist

1. Attendance DB schema and migrations.
2. Attendance API endpoints.
3. HR Workbench attendance hub pages.
4. ESS attendance pages.
5. Flutter terminal app for Windows and Android.
6. ZKTeco fingerprint integration for approved device model(s).
7. Payroll informational summary endpoint and export.
8. Admin and user documentation.

---

## 17. Go-Live Success Criteria

1. At least 95 percent successful clock event submission rate in pilot.
2. Less than 2 percent duplicate or invalid event rate.
3. HR can view and adjust attendance without direct DB edits.
4. Staff can view personal attendance history in ESS.
5. Payroll team can retrieve monthly attendance summary without manual collation.

