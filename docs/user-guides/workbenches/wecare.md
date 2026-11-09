# WeCare247 Telehealth Integration

<span class="badge-role">ROLE GUARD: SUPERADMIN | ADMIN</span>

CoreHealth v2 maintains real-time bidirectional clinical synchronization with the **WeCare247** telemedicine platform (`wecare247/`), extending acute hospital care to remote patients and mobile consultations.

---

## 1. Visual Tour: Remote Patient Access

![WeCare Mobile Telehealth Landing](/images/workbenches/mobile-welcome-landing.jpg)

> [!NOTE]
> WeCare247 connects outpatients directly with hospital clinicians, auto-syncing remote clinical encounter summaries into CoreHealth longitudinal patient records.

---

## 2. Bidirectional Sync Architecture

```mermaid
sequenceDiagram
    participant WeCare as WeCare247 Mobile / Web Portal
    participant CoreHealth as CoreHealth v2 Hospital Node
    participant EHR as Longitudinal EHR

    WeCare->>CoreHealth: Patient Demographic Lookup (Phone / File No)
    CoreHealth-->>WeCare: Returns Verified Demographics
    WeCare->>CoreHealth: Tele-Consultation Completed (Diagnosis & Notes)
    CoreHealth->>EHR: Ingests Encounter into Hospital Patient Record
    CoreHealth->>WeCare: Pushes Approved Lab & Imaging Diagnostic Results
```

### Key Capabilities:
- **Unified Endpoints**: Standardized under `/api/corehealth/*`.
- **Policy Controls in Hospital Settings**: Master switch `wecare_enabled` with granular toggles for auto-syncing encounters and lab results.
- **Deterministic Deduplication**: Composite sync keys (`wecare_sync_uuid`) prevent duplicate encounter records.

---

## Developer Reference
👉 **[Read the WeCare247 Integration Contracts & API Reference](/developer/workbenches/wecare)**

