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
| `api/mobile/staff/*` | active staff | `MobileAuthController` (login, `workspaces`, `settings`) |
| `api/mobile/reception/*` | `native.workspace:reception` | `ReceptionWorkbenchController` (queue, search, registration, booking, walk-in, service requests, HMO validation), `SpecialistReferralController` (pending desk), `BillingWorkbenchController` (my transactions, staff/org lists, account summary), `BankController`, adapter `API\MobileReceptionController` (`context`, `process-payment`) |
| `api/mobile/shared/*` | `native.workspace:reception\|doctor` | Registration and lookup, `HospitalContactController`, `WorkbenchPriceListController`, `ClinicalAlertController`, `DoctorAppointmentController`, `EmergencyIntakeController`, `MorgueController@getServices`, `WardController`, `AdmissionRequestController@store`, `AdmissionModuleController` |
| `api/mobile/doctor/*` | `native.workspace:doctor` | `EncounterController`, `MobileEncounterController`, `DoctorAppointmentController` (calendar, timer), `DiagnosisFavoriteController`, `LlmController` (polish, briefing, scribe), `TreatmentPlanController`, `NonPharmOrderController`, `ResultViewController`, `PatientProfileController`, `MedicalReportController`, `Lab/ImagingWorkbenchController` (result entry), `NhmisWorkbenchController@getMappingForService`, `MedicationChartController`, `IntakeOutputChartController`, `SpecialistReferralController`, `VitalSignController` |
| `api/mobile/doctor/chat/*` | `native.workspace:global` | `MobileChatController` |

### Hospital Config in the app
`App\Support\ClientSettings::workbench()` computes the `appsettings()` values that change workbench behaviour (dose mode, structured dosing, treatment plans, emergency intake switches, procedure pricing, result approval, note edit window, `llm_enabled`, ...) with the web's defaults. The web encounter page (`_encounter_scripts`, `dose-mode-toggle`) reads it, and the app receives the same array from login, `staff/workspaces` and `staff/settings` (`ClientSettings` in Dart). Never hard-code these defaults in the app.

**Rules that decide per-row actions** (appointment actions, referral desk actions, result perform/enter/edit) stay on the server: the app reads which buttons the web list rendered, so a permission change on the server changes both.

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
| 3 | Health Records | `reception` | Every reception workbench feature: front desk, full registration and edit (photo, old records, family, fees, age modes), walk-in and emergency registration, queue filters, patient hub (profile, visits, service requests, appointments, admissions), family booking with re-bill and tariff preview, walk-in sales, checkout (all payment methods), HMO validation, referrals desk, appointments, my transactions, alerts, contacts, price list, emergency intake | Pages: All Encounters, NHMIS report. Workbench: reports and charts, printing (card, routing slip, receipts A4), ward dashboard (Nursing), morgue admission (Morgue) |
| 4 | Billing | `billing` | — | All |
| 5 | Pharmacy | `pharmacy` | — | All |
| 6 | Store / Inventory | `store` | — | All |
| 7 | Nursing | `nursing` | — | All |
| 8 | Morgue | `morgue` | — | All |
| 9 | Maternity | `maternity` | — | All |
| 10 | Medical Laboratory | `lab` | — | All |
| 11 | Imaging / Radiology | `radiology` | — | All |
| 12 | Doctors | `doctor` | My Queues (ribbon, filters, calendar, history, open past encounters) and every New Encounter tab and action: treatment plans, clinical story, vitals trends, nurse charts with MAR and I/O actions, notes (favourites, templates, AI polish, web status/course values), orders (order sets, bundles, re-order from past encounter, result entry), care plan, admit/discharge, server timer, alerts, edit patient, AI Scribe Studio, medical report builder, conclude (outcomes, death record, follow-up) | Maternity Workbench. Legacy patient-profile questionnaires (filling), printing |
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
