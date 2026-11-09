# CoreHMS Synchronization, Liveness Monitoring & Retroactive Ingest Implementation

**Date**: October 10, 2026  
**Git Release Tag**: `v2.6.0.37`  
**Status**: Production Ready  

## 1. Overview
This document outlines the synchronization architecture, liveness monitoring subsystem, and historical data ingest workflow between **CoreHealth v2** (hospital instance) and **CoreHMS SuperAdmin** (multi-tenant central administrative platform).

---

## 2. Architecture & Data Flow

### A. Real-Time Event Synchronization
When key clinical actions occur in CoreHealth modern workbenches, non-blocking asynchronous jobs (`DispatchCoreHmsSyncJob`) are dispatched through the Laravel database queue (`QUEUE_CONNECTION=database`):
- **Patient Registrations / Edits**: [PatientController.php](app/Http/Controllers/PatientController.php) -> `PatientSyncEvent`
- **Clinical Encounters & Consultations**: [EncounterController.php](app/Http/Controllers/EncounterController.php) & [ReceptionWorkbenchController.php](app/Http/Controllers/ReceptionWorkbenchController.php) -> `ConsultationSyncEvent`
- **Pharmacy Dispensing**: [PharmacyWorkbenchController.php](app/Http/Controllers/PharmacyWorkbenchController.php) -> `DispenseSyncEvent`
- **Lab & Imaging Investigations**: [LabWorkbenchController.php](app/Http/Controllers/LabWorkbenchController.php) -> `LabSyncEvent`
- **Surgical Procedures**: [PatientProcedureController.php](app/Http/Controllers/PatientProcedureController.php) -> `ProcedureSyncEvent`
- **Ward Admissions & Nursing**: [NursingWorkbenchController.php](app/Http/Controllers/NursingWorkbenchController.php) -> `AdmissionSyncEvent`
- **Staff Onboarding & Updates**: [StaffController.php](app/Http/Controllers/StaffController.php) -> `StaffSyncEvent`

### B. Event Payload Interfaces & Data Consistency
Both sides enforce strict contract parity through typed interfaces:
- **CoreHealth**: `app/Contracts/CoreHms/*` (`PatientSyncEventInterface`, `ConsultationSyncEventInterface`, etc.)
- **CoreHMS SuperAdmin**: `Corehealth-Super-Admin/includes/contracts/*` (`PatientEventInterface`, `ConsultationEventInterface`, etc.)

---

## 3. Idempotency & Deduplication Engine
To ensure duplicate dispatches, network retries, and retroactive historical data syncs never cause duplicate statistics:
- **Deduplication Key**: `sha1(facility_id + ':' + event_type + ':' + source_id)`
- **Event Audit**: Recorded in `facility_sync_events` with unique index on `dedup_key`.
- **Domain Rollups**: Aggregated in `facility_clinical_records` and `total_statistics`.

---

## 4. Facility Liveness Monitoring & Health Endpoints

### CoreHealth Endpoints:
1. `GET /api/corehms/ping`: Ultra-fast ping endpoint returning:
   ```json
   { "status": true, "pong": true, "facility_id": "...", "timestamp": "2026-10-10 11:00:15" }
   ```
2. `GET /api/corehms/health`: Deep health inspection returning:
   ```json
   {
     "status": true,
     "healthy": true,
     "facility": { "id": "123", "name": "General Hospital Example", "goonline": 1, "version": "Ver. 1.0" },
     "system": {
       "php_version": "8.3.6",
       "laravel_version": "8.83.29",
       "server_time": "2026-10-10 11:00:15",
       "queue_driver": "database",
       "pending_queue_jobs": 0,
       "failed_queue_jobs": 8,
       "db_connected": true,
       "db_latency_ms": 0.91
     }
   }
   ```

### CoreHMS SuperAdmin Features:
1. **Interactive Single & Bulk Ping**:
   - `ajax/ping-facility.php?id=<id>`: Pings individual facility via cURL, calculates latency in ms, updates `last_heartbeat_at = NOW()` and `system_info` in `facilities`.
   - `ajax/ping-facility.php?action=ping_all`: Asynchronously pings all facilities with a 3-second safe timeout.
2. **Real-Time Visual Indicators**:
   - `assets/js/facility-monitor.js` provides live UI response with badge color changes (`Online (<15m)`, `Idle (<24h)`, `Offline (>24h)`) and dynamic latency badges (e.g., `Online (216ms)`).
3. **Live Dashboard & Facility KPIs**:
   - Real metrics for facilities, online count, patients, encounters, pharmacy dispenses, labs, procedures, admissions, and backups in both `index.php` and `view-facility.php`.

---

## 5. Retroactive Historical Sync & Backfill
- **CoreHMS UI Modal**: `view-facility.php` includes a retroactive historical sync modal with live pagination feedback and progress bar.
- **AJAX Endpoint**: `Corehealth-Super-Admin/ajax/retroactive-fetch.php` pulls paged records from CoreHealth export endpoints (`/api/get-facility-dispenses`, `/api/get-facility-investigations`, `/api/get-facility-procedures`, `/api/get-facility-patients`, `/api/get-facility-consultations`) and pushes them through the SHA1 deduplication engine.
- **CoreHealth CLI Backfill**: `php artisan corehms:backfill --module=all` enables facility-side bulk backfilling.

---

## 6. Automated Backup Tracking & Downloads
- **CoreHealth Endpoint**: `/api/corehms/backup` generates, rotates, and streams compressed SQL database dumps.
- **CoreHMS Backup Console**: `backups.php` enables central backup generation, tracking, and direct cPanel server downloads.
