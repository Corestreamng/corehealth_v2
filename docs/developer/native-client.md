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
| `api/mobile/surgery/*` | `native.workspace:nursing\|doctor` | `SurgeryWorkbenchController` (queue), `PatientProcedureController` (the procedure page), adapter `API\MobileSurgeryController` (checklist) |
| `api/mobile/doctor/service-combo/*` | `native.workspace:doctor\|nursing\|maternity` | `EncounterController@removeBundleGeneric / removeBundleItemGeneric`, adapter `API\MobileBundleController` (applied bundles) |
| `api/mobile/nursing/beds` | `native.workspace:nursing` | adapter `API\MobileBedController` over `BedController` (list, create, edit) |
| `api/mobile/maternity/*` | `native.workspace:maternity` | `MaternityWorkbenchController` (all enrolment, ANC, delivery, partograph, baby, postnatal, immunization and order actions), shared `API\MobileNursingController@context`, `ProductController@liveSearchProducts` |
| `api/mobile/nursing/*` | `native.workspace:nursing` | `NursingWorkbenchController`, `ShiftController`, adapter `API\MobileNursingController` (`context`: resolved ward store and candidate stores) |
| `api/mobile/doctor/{vitals,nursing-notes,medication-chart,intake-output,llm/*,patients/{id}/clinical-story*}` | `native.workspace:doctor\|nursing\|maternity` | `VitalSignController`, `MedicationChartController`, `IntakeOutputChartController`, `LlmController` (scribe, briefing, polish), `EncounterController` (clinical story by patient): shared by the three workbenches |
| `api/mobile/billing/*` | `native.workspace:billing` (shared lookups also open to reception, doctor, nursing, maternity) | `BillingWorkbenchController` (all billing desk actions), `BankController`, `SalesApi` shared with reception |
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
  - Desktop (≥1200): collapsible sidebar (272 px ↔ 76 px icons), top bar (breadcrumb, patient search, Messenger, account menu with Appearance).
  - Tablet (600–1199): the same with the sidebar collapsed to icons.
  - Phone (<600): compact top bar (workspace switcher, search) and a bottom bar with up to 4 destinations + More.
  - Destinations are kept alive in an `IndexedStack` and built lazily. On desktop/tablet each destination has **its own `Navigator`**, so screens pushed with `Navigator.of(context).push` open in the content area and the sidebar stays. Re-selecting the current sidebar item pops back to its first screen.
- **`features/shell/patient_palette.dart`:** `PatientLookup.forWorkspace(key)` maps a workspace to its existing search endpoint and patient screen (nursing, billing, reception, maternity). `showPatientPalette` is the Ctrl/⌘ K search.
- **`WebFallbackScreen`:** "Open on web" for items that aren't native yet.
- **`core/api/AuthedApi`:** shared authenticated HTTP. Each workspace service only declares endpoints.

### Search, Clinical Context and the web hand-off
- **Global search (Ctrl/⌘ K)** = the web top bar: `GET /api/mobile/staff/search` → `RouteSearchController@search` (same role/permission filtering as `/search/api`). Results marked **In menu** open like their sidebar item (natively when the app has it); **Locate** (Alt+Enter) reveals the item in the sidebar; **Menu (/)** hands the query to the sidebar filter. The sidebar filter follows `sidebar.js` `filterSidebar` (section-title match shows the whole section; Enter opens a single match, otherwise hands off to the global search).
- **Clinical Context** (`features/shared/clinical_context_sheet.dart`) = `admin/partials/clinical_context_modal`: tabs Encounter Notes, Labs, Imaging, Vitals, Medications, Allergies, Inj/Imm History, Procedures, with unviewed-result badges. Routes `api/mobile/clinical-context/*` (`native.workspace:nursing|maternity|doctor|lab|radiology|pharmacy|hmo`) point at `ClinicalContextController`, `NursingWorkbenchController@getInjections/getImmunizationHistory` and `ResultViewController`. Used by the Nursing and Maternity records; new workbenches reuse `showClinicalContext`.
- **Web hand-off** (`core/web/open_on_web.dart`): every "Open on web" asks `POST /api/mobile/staff/web-handoff` (`WebHandoffController@issue`, throttled) for a one-time link; the browser redeems it at `GET /auth/handoff` and lands on the page signed in. `WebHandoffService`: 256-bit code stored only as a SHA-256 hash, 60 s TTL, single use under a lock, bound to the user, the issuing Sanctum token (app sign-out voids it) and the device IP (`WEB_HANDOFF_BIND_IP`), same-site paths only, `no-store`/`no-referrer`, audit log lines. Sign-in rules (deactivated, suspended, locum pass) live in `App\Support\Auth\StaffLoginPolicy`, shared with `LoginController`. Tests: `tests/Feature/NativeClient/WebHandoffTest.php`.
- **Web links**: `tests/Feature/NativeClient/WebLinksTest.php` opens every sidebar item's `web_url` as sysadmin (126 items) and fails on a missing URL or a 404/405/500.
- **Terminology**: sidebar items always show the server's (translated) sidebar labels; desks, queues and record sections use the web workbench wording (e.g. *Nursing Workbench*, *Admitted Patients*, *Medication Chart*, *Clinical Requests*).

### Design system (`lib/core/design/`)
| File | What it gives screens |
|---|---|
| `tokens.dart` | `Space`, `Radii`, `PageWidth`, `StatusTone`, and `AppColors` (light/dark semantic palette as a `ThemeExtension`, read with `context.colors`). The hospital colour (`ThemeProvider.primaryColor`) becomes `accent`, adjusted to 4.5:1 contrast for each mode. |
| `app_theme.dart` | `AppTheme.light/dark(brand)`: every Material component themed (Inter type scale, neutral surfaces, bordered cards, filled inputs). `MaterialApp(theme, darkTheme, themeMode)`; the mode is stored in `LocalStorage.themeMode`. |
| `components.dart` | `AppPage`, `SectionCard`, `KeyValueRow`, `AppBadge`, `MetricTile`/`MetricRow`, `FilterBar`, `ActionBar`/`PageAction`, `EntityList`/`ListRow`, `Avatar`, `StateMessage`, `SkeletonList`. The older `InfoCard`, `KV`, `Tag`, `AsyncView`, `EmptyState`, `StatusBadge` and `SheetScaffold` delegate to these. |
| `record_workspace.dart` | `RecordWorkspace` + `PatientBanner` + `RecordSection` (key, label, icon, group, badge, builder): grouped section list on wide screens, pills on phones. Used by every patient record (nursing, consultation, maternity enrolment and baby, billing, reception, surgery procedure). |

`test/design_guard_test.dart` fails if a feature file uses a raw Material hue, uses the raw `primaryColor` outside the brand screens (splash, onboarding, sign-in), or if a patient record stops using `RecordWorkspace`.

## 3. Workspace parity (update with every change)
| # | Workspace (sidebar order) | Registry key | Native in app | Still on web |
|---|---|---|---|---|
| 1 | Home / Messenger | `global` | Home (workspace tiles), Messenger | — |
| 2 | Staff Workspace (ESS, supplies) | `staff_workspace` | — | All items |
| 3 | Health Records | `reception` | Every reception workbench feature: front desk, full registration and edit (photo, old records, family, fees, age modes), walk-in and emergency registration, queue filters, patient hub (profile, visits, service requests, appointments, admissions), family booking with re-bill and tariff preview, walk-in sales, checkout (all payment methods), HMO validation, referrals desk, appointments, my transactions, alerts, contacts, price list, emergency intake | Pages: All Encounters, NHMIS report. Workbench: reports and charts, printing (card, routing slip, receipts A4), ward dashboard (Nursing), morgue admission (Morgue) |
| 4 | Billing | `billing` | Native: cashier shift, payment queue, patient bill (select, qty/discount, pay: cash/POS/transfer/mobile/split/account/bill-to-staff/org, invoice), walk-in sales, receipts, admissions bill, patient account (deposit/withdraw/adjust/statement), my and manage transactions (void, adjust). Web-only: printing layouts, reports | All |
| 5 | Pharmacy | `pharmacy` | — | All |
| 6 | Store / Inventory | `store` | — | All |
| 7 | Nursing | `nursing` | Nursing Desk: shift start/end with pending handovers, patient search, queues (admitted, vitals due, medication due, bed requests, discharge, deceased), ward dashboard (occupancy, assign bed, bed maintenance), handovers, outreach tallies, new patient and walk-in, emergency intake, contacts. Patient: overview, vitals (record, edit), medications and MAR, notes and intake/output, injections (ward stock, dispensed, patient's own), immunization schedule (generate, give, skip) and unscheduled entry, requests and bills, admission (assign/transfer bed, checklists, discharge), clinical story (shared with doctor), AI Scribe Studio (Focus DAR / SBAR / SOAPIE, signed note filed as the chosen note type, default Others), direct drugs on the chart (ward stock and patient's own: add, schedule, give on schedule, give now), shared clinical orders (re-order, treatment plan templates, bundles with item-level removal), shared Billing tab, Surgery Workbench (queue and procedure workspace), Manage Beds, reports, deceased last office | Printable layouts and exports; adding theatre team members and procedure items; signing consent with a signature; batch fulfilment suggestion (no web screen uses it) |
| 8 | Morgue | `morgue` | — | All |
| 9 | Maternity | `maternity` | Maternity Desk: patient search with enrol, nine queues (active ANC, due visits, high risk, upcoming EDD, postnatal, overdue immunization, admitted, bed requests, discharge), new patient and walk-in, emergency intake, contacts. Enrolment: overview and edit, ANC visits, history and previous pregnancies, clinical orders (lab, imaging, procedure, prescription), delivery, partograph, babies (details, growth, immunization, death), postnatal visits, mother's immunization schedule, vitals, notes, audit trail, discharge | Printable card layouts (the cards open in the app as text; printing stays on the web). Native: Billing tab (shared with nursing), clinical orders with re-order, treatment plans and bundles, investigations, outreach, reports, clinical story and AI Scribe Studio |
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
- **`DoctorQueueStatsScopeTest`:** `GET /api/mobile/doctor/queues/stats` takes optional `start_date` and `end_date`. With them the badges count the same date range as the list and the web stats bar (`getDoctorQueueCounts`); without them every day is counted (older app builds).
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
