# CoreHMS SuperAdmin Contracts & Developer Reference

<span class="badge-role">AUTHORITATIVE ROLE GUARD: SUPERADMIN | ADMIN</span>

## 1. Endpoints & Jobs
- `GET /api/corehms/ping` and `GET /api/corehms/health`: Non-blocking latency and queue monitoring.
- `GET /api/corehms/backup`: Database SQL dump streaming.
- `php artisan corehms:backfill`: CLI retroactive event synchronizer.
- `DispatchCoreHmsSyncJob`: Queued asynchronous dispatcher.

## 2. Event Contract Parity
Contracts in `app/Contracts/CoreHms/*` mirrored exactly in SuperAdmin:
- `PatientSyncEventInterface`
- `ConsultationSyncEventInterface`
- `DispenseSyncEventInterface`
- `LabSyncEventInterface`
- `ProcedureSyncEventInterface`
- `AdmissionSyncEventInterface`
- `StaffSyncEventInterface`
- `HeartbeatSyncEventInterface`

## 3. Testing Verification
```bash
vendor/bin/phpunit tests/Feature/CoreHms/CoreHmsMonitoringAndSyncTest.php
```

👉 **[Read User Story Guide for CoreHMS](/user-guides/workbenches/corehms)**

