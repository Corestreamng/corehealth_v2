# Native Clients Developer Reference

<span class="badge-role">GUARDS: identical to the web sidebar (per workspace)</span>

Two Flutter apps live in `native_clients/`:
- **Staff app** (`client_app`): one app for every staff workspace on phone, tablet and desktop. Rules: `native_clients/client_app/CLAUDE.md`.
- **Patient app** (`patient_app`): patients' own records. Rules: `native_clients/patient_app/CLAUDE.md`. See §5.

## 1. Server foundation
| Piece | Location | Purpose |
|---|---|---|
| Workspace registry | `app/Support/Navigation/WorkspaceRegistry.php` | Mirrors `resources/views/admin/partials/sidebar.blade.php`: section order, `@hasanyrole` guards, nested `@can`/`@hasanyrole`, and the sidebar's computed conditions (`ess_access`, `nhmis_report`, `hmo_mgmt`, `chat_enabled`, `procedure_category_set`). |
| Guard middleware | `app/Http/Middleware/EnsureNativeWorkspace.php`, alias `native.workspace` | `native.workspace:<key>[\|<key>]`: only active staff, and only when the registry allows that section. Returns JSON 403 otherwise. |
| Workspaces endpoint | `GET /api/mobile/staff/workspaces` → `MobileAuthController@staffWorkspaces` | Visible sections and items in sidebar order: `key`, translated `label`, `icon`, items with `key`, `label`, `web_url`. Also returned by `POST /api/mobile/staff/login` as `data.workspaces`. |
| Staff login | `MobileAuthController@staffLogin` | Refuses inactive accounts and patient-only accounts (403). Token name `mobile-staff` (legacy `mobile-doctor` tokens are revoked on sign-in). |

### Mobile route groups
| Prefix | Guard | Controllers (same as web) |
|---|---|---|
| `api/mobile/reception/*` | `native.workspace:reception` | `ReceptionWorkbenchController` (today-stats, queue-counts, queue-list, search-patients, patient, visits, queue, next-file-number, check-file-number, check-duplicate, quick-register, update, clinics, doctors, consultation services, hmos, tariff-preview, book-consultation) |
| `api/mobile/doctor/*` | `native.workspace:doctor` | `EncounterController`, `MobileEncounterController`, `SpecialistReferralController`, `VitalSignController`, `MedicationChartController`, `DoctorAppointmentController`, ... |
| `api/mobile/doctor/chat/*` | `native.workspace:global` | `MobileChatController` |

## 2. App architecture
- **`core/workspaces/`:**
  - `WorkspaceController` (Provider): cached, then refreshed workspaces, and the active one.
  - `NativeScreens`: registry of item key → native screen, plus workspace extras.
- **`features/shell/AppShell`:** adaptive navigation.
  - Phone (<600): bottom bar with up to 4 destinations + More.
  - Tablet (<1200): navigation rail.
  - Desktop: full sidebar.
  - Destinations are kept alive in an `IndexedStack` and built lazily.
- **`WebFallbackScreen`:** "Open on web" for items that aren't native yet.
- **`core/api/AuthedApi`:** shared authenticated HTTP. Each workspace service only declares endpoints.

## 3. Workspace parity (update with every change)
| # | Workspace (sidebar order) | Registry key | Native in app | Still on web |
|---|---|---|---|---|
| 1 | Home / Messenger | `global` | Home (workspace tiles), Messenger | — |
| 2 | Staff Workspace (ESS, supplies) | `staff_workspace` | — | All items |
| 3 | Health Records | `reception` | Front Desk (search, today and queue stats), New Patient (duplicate check, next file number), Queue (filters, paging), Patient record, Book consultation | All Encounters, NHMIS report; web workbench extras: walk-in services and cart, HMO validation, service requests, reports, routing slip, patient edit, family links, photo/documents |
| 4 | Billing | `billing` | — | All |
| 5 | Pharmacy | `pharmacy` | — | All |
| 6 | Store / Inventory | `store` | — | All |
| 7 | Nursing | `nursing` | — | All |
| 8 | Morgue | `morgue` | — | All |
| 9 | Maternity | `maternity` | — | All |
| 10 | Medical Laboratory | `lab` | — | All |
| 11 | Imaging / Radiology | `radiology` | — | All |
| 12 | Doctors | `doctor` | Dashboard, My Queues + consultation (13 tabs), Investigations | Maternity Workbench; remaining items in `docs_archive/MOBILE_WEB_PARITY_PLAN.md` |
| 13 | HMO Executive | `hmo` | — | All |
| 14 | Accounting | `accounting` | — | All |
| 15 | Human Resources | `hr` | — | All |
| 16 | Internal Audit | `audit` | — | All |
| 17 | Administration | `admin` | — | All |

## 4. Ecosystem
Every change to these apps or their API considers the patient app, CoreHMS Super Admin, the SDKs, DHIS2 and WeCare247, and releases stay in sync. See [Ecosystem & Release Sync](/developer/ecosystem-and-releases).

## 5. Patient app
`native_clients/patient_app`: patients' own records and chat with their own doctors. Rules: `native_clients/patient_app/CLAUDE.md`.

| Area | API (server) | App |
|---|---|---|
| Sign in | `POST /api/mobile/patient/login` (file number and phone) | `features/auth` |
| Profile | `GET/PUT /api/mobile/patient/profile`, `POST change-password` | `features/profile` |
| Records | `GET /api/mobile/patient/{encounters, vitals, lab-results, imaging-results, prescriptions, procedures, admissions, referrals, appointments}` (`API\MobilePatientController`) | `features/health` |
| Chat | `/api/mobile/patient/chat/*` (`API\MobileChatController`, own doctors only) | `features/chat` |

- **Isolation:** patients can't sign in to the staff app, and staff endpoints refuse patient tokens (`native.workspace`).
- **Known gaps:** the patient app is still portrait-only, with no tablet or desktop layouts yet. The remaining record-screen parity items are in `docs_archive/MOBILE_WEB_PARITY_PLAN.md` (Phase 5).

## 6. Tests
```bash
vendor/bin/phpunit tests/Feature/NativeClient          # registry ↔ sidebar parity, guards, login, wrapper parity
(cd native_clients/client_app && flutter analyze && flutter test)
```
- **`WorkspaceRegistryParityTest`:**
  - Sections follow the sidebar's include order.
  - Every section's role guard equals its partial's.
  - Nested guards match.
  - Every sidebar route is mirrored and exists.
- **`NativeStaffApiTest`:**
  - Patient and inactive accounts are refused.
  - The workspaces list follows roles.
  - Cross-workspace calls get 403.
  - `mobile/reception/today-stats` returns exactly the web response.

> **Known web-side quirk mirrored as-is:** the sidebar's `$canAccessEss` uses `!empty($user->staff_profile)`, which is always true because `staff_profile()` uses `withDefault()`. The registry reproduces this, so Self-service visibility depends only on the `ess.access` permission, as on the web. Staff login uses a real staff-row check instead.

👉 **[User guide: CoreHealth Staff App](/user-guides/native-client)**
