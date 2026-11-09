# WeCare247 Integration Contracts & Developer Reference

<span class="badge-role">AUTHORITATIVE ROLE GUARD: SUPERADMIN | ADMIN</span>

## 1. Endpoints Under `/api/corehealth/*`
- `GET /api/corehealth/ping`: Non-blocking liveness check reporting facility status and `wecare_enabled`.
- `GET /api/corehealth/patient/lookup`: SHA256 Basic Auth protected demographic query by file number or phone.
- `POST /api/corehealth/sync/encounter`: Ingestion of tele-consultations into longitudinal EHR.
- `POST /api/integrations/corehealth/webhook/lab-result`: Outbound dispatch of approved lab and imaging results.

## 2. Idempotency & Deduplication
Enforces composite unique keys: `sha1('wecare_encounter:' + wecare_appointment_id)` against `encounters.wecare_sync_ref`.

## 3. Testing Verification
```bash
vendor/bin/phpunit tests/Feature/WeCare/WeCareIntegrationAndSettingsTest.php
```

👉 **[Read User Story Guide for WeCare247](/user-guides/workbenches/wecare)**

