# Ecosystem Architectural Independence & Decoupling Standards

**Date**: October 12, 2026  
**Git Release Tag**: `v2.6.0.38`  
**Status**: Active / Enforced  

---

## 1. Overview & Architectural Principles

The CoreHealth software ecosystem consists of three major platforms:
1. **CoreHealth v2**: Hospital HMIS / EHR instance managing clinical workbenches, appointments, billing, pharmacy, laboratory, and inpatient care.
2. **WeCare247**: Multi-tenant telehealth platform facilitating telemedicine video/chat consultations, remote patient monitoring, digital prescriptions, and partner diagnostic lab workflows.
3. **CoreHMS SuperAdmin**: Central administrative, analytics, and telemetry aggregation platform for multi-facility governance.

### Mandatory Independence Standard
**All three repositories are designed and maintained to operate, build, test, and deploy with complete independence.**
- **No Sibling Filesystem Assumptions**: No codebase may make assumptions about the filesystem location of any other repository.
- **Pure Network API Contracts**: All cross-application integrations occur strictly via standard HTTP REST APIs, Webhooks, or message queues.
- **Independent CI/CD & Testing**: Each repository contains its own self-contained test suites, linters, dependency manifests, and documentation.
- **Zero Local Filesystem Paths**: Absolute paths (e.g., `/home/username/...`) are strictly forbidden in committed code and documentation; only relative paths are permitted.

---

## 2. Platform Autonomy Matrix

| Platform | Primary Tech Stack | Standalone Operation Capabilities | Integration Role |
|---|---|---|---|
| **CoreHealth v2** | PHP 8.3 FPM, Laravel, MySQL 8.0, Redis | Full on-premise or cloud hospital HMIS. Works without SuperAdmin or WeCare247. | Exposes `/api/corehealth/*` and `/api/corehms/*` endpoints; dispatches async queue jobs when enabled. |
| **WeCare247** | Next.js (App Router), TypeScript, MongoDB | Full standalone telehealth SaaS platform. Operates with or without external hospital EHRs. | Dynamically queries configured hospital EHRs via `CoreHealthClient` when facility enables integration. |
| **CoreHMS SuperAdmin** | PHP 8.3, MariaDB / MySQL 8.0 | Standalone cloud monitoring and management console. Works with any number of facilities (0 to N). | Probes liveness via cURL; ingests webhook events and backfills on-demand. |

---

## 3. Configuration & Credential Isolation

- **No Shared Environment Variables for Facility Identity**:
  - Facilities connecting WeCare247 to CoreHealth configure connection credentials (URL, client ID, client secret) directly within their database record in MongoDB (`facilities.corehealth_integration`).
  - There is zero reliance on hardcoded environment variables for facility credentials, ensuring multi-tenant isolation.
- **Generic Documentation & Examples**:
  - All documentation, setup guides, and configuration examples must use generic names (e.g., `General Hospital Example`, `Integrated Healthcare Facility`) and generic placeholders rather than facility-specific or developer-specific references.

---

## 4. Documentation & Versioning Governance

- **Zero `walkthrough.md` Files**: `walkthrough.md` files are strictly purged.
- **Dedicated Docs Directory**: Every architectural enhancement and integration is documented within the corresponding repository's `docs/` folder.
- **Date & Tag Associations**: Every document in `docs/` must specify:
  - **Date**: `YYYY-MM-DD`
  - **Git Release Tag / Version**: `vX.Y.Z`
- **Strict Linting Compliance**: All PHP code complies with PSR-12 standard via CS Fixer; all TypeScript code compiles cleanly with `tsc --noEmit`.
