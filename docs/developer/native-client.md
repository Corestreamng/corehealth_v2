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
  - Desktop (≥1200): collapsible sidebar (272 px ↔ 68 px icons), top bar (breadcrumb, patient search, Messenger, account menu with Appearance).
  - Tablet (600–1199): collapsed 68 px icon rail showing all switchable workspaces with anchored right-side flyouts (`_WorkspaceFlyoutCard`) for sub-destinations.
  - Phone (<600): compact top bar (workspace switcher, search), full-width content area (no left rail), and a thumb-friendly 60 px bottom navigation bar (`_PhoneBottomBar`) showing primary destinations + More tab. Tapping active tabs reveals anchored upward flyouts (`_BottomBarFlyoutCard`), and tapping More opens the draggable `_MobileMoreDrawer` with instant search across all hospital workspaces and accordions.
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
| 3 | Health Records | `reception` | Every reception workbench feature: front desk, full registration and edit (photo, old records, family, fees, age modes), walk-in and emergency registration, queue filters, patient hub (profile, visits, service requests, appointments, admissions), family booking with re-bill and tariff preview, walk-in sales, checkout (all payment methods), HMO validation, referrals desk, appointments, my transactions, alerts, contacts, price list, emergency intake File number management in every registration (new, walk-in, emergency, department, edit): Auto/Manual, series prefix and switch, next and recent numbers, live duplicate check. | Pages: All Encounters, NHMIS report. Workbench: reports and charts, printing (card, routing slip, receipts A4), ward dashboard (Nursing), morgue admission (Morgue) |
| 4 | Billing | `billing` | Native: cashier shift, payment queue, patient bill (select, qty/discount, pay: cash/POS/transfer/mobile/split/account/bill-to-staff/org, invoice), walk-in sales, receipts, admissions bill, patient account (deposit/withdraw/adjust/statement), my and manage transactions (void, adjust). Web-only: printing layouts, reports | All |
| 5 | Pharmacy | `pharmacy` | Pharmacy desk (store, paged prescription queue with emergency first, patient search, walk-in/emergency/new patient, contacts, price list), patient prescriptions in the web's stages (bill, hold, ready, free-form) with bulk bill/dispense/dismiss/slip, adjust price and quantity, adapt, dispense with store, stock check and batch choice, new request with bundles and free-form, history, returns (list, detail, approve/reject/refund, bulk, new return), damages (list, detail, approve/reject, report), stock reports (overview, by store/category, valuation, expiring, low stock), summary reports (all web reports and executive summary) and the shared Transactions Console | Print layouts and exports (the web), movement analysis (a stub on the web), the product master data pages |
| 6 | Store / Inventory | `store` | — | All |
| 7 | Nursing | `nursing` | Nursing Desk: shift start/end with pending handovers, patient search, queues (admitted, vitals due, medication due, bed requests, discharge, deceased), ward dashboard (occupancy, assign bed, bed maintenance), handovers, outreach tallies, new patient and walk-in, emergency intake, contacts. Patient: overview, vitals (record, edit), medications and MAR, notes and intake/output, injections (ward stock, dispensed, patient's own), immunization schedule (generate, give, skip) and unscheduled entry, requests and bills, admission (assign/transfer bed, checklists, discharge), clinical story (shared with doctor), AI Scribe Studio (Focus DAR / SBAR / SOAPIE, signed note filed as the chosen note type, default Others), direct drugs on the chart (ward stock and patient's own: add, schedule, give on schedule, give now), shared clinical orders (re-order, treatment plan templates, bundles with item-level removal), shared Billing tab, Surgery Workbench (queue and procedure workspace), Manage Beds, reports, deceased last office Medication chart: searchable drug list beside the month calendar (list then full-screen calendar on a phone), next/previous drug. | Printable layouts and exports; adding theatre team members and procedure items; signing consent with a signature; batch fulfilment suggestion (no web screen uses it) |
| 8 | Morgue | `morgue` | — | All |
| 9 | Maternity | `maternity` | Maternity Desk: patient search with enrol, nine queues (active ANC, due visits, high risk, upcoming EDD, postnatal, overdue immunization, admitted, bed requests, discharge), new patient and walk-in, emergency intake, contacts. Enrolment: overview and edit, ANC visits, history and previous pregnancies, clinical orders (lab, imaging, procedure, prescription), delivery, partograph, babies (details, growth, immunization, death), postnatal visits, mother's immunization schedule, vitals, notes, audit trail, discharge Medication chart: searchable drug list beside the month calendar (list then full-screen calendar on a phone), next/previous drug. | Printable card layouts (the cards open in the app as text; printing stays on the web). Native: Billing tab (shared with nursing), clinical orders with re-order, treatment plans and bundles, investigations, outreach, reports, clinical story and AI Scribe Studio |
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

- **Responsive App Shell & Navigation:**
  - **Phone (<600px):** Full-width content, 60px bottom navigation bar with primary tabs (Home, Visits, Results, Prescriptions) + More tab, upward flyouts for sub-items on active tab tap/long-press, and a comprehensive More bottom drawer with real-time record search, theme toggle, and categorized module access.
  - **Tablet (600–1199px):** 68px collapsed vertical icon rail with anchored right-side flyouts for sub-items.
  - **Desktop (≥1200px):** 260px full sidebar with hospital branding, patient header, menu search filter, and grouped sections (Overview, Medical Records, Care & Schedule, Preferences).
- **Design System & Theming:**
  - Dynamic hospital theme palette (`hos_color`), light and dark mode support with `LocalStorage` persistence (`ThemeMode.system | light | dark`), and semantic tokens via `AppTheme`, `AppColors`, `SectionCard`, `MetricTile`, `AppBadge`, `Avatar`, `KeyValueRow`, and `ContentWidth`.
- **Isolation:** patients can't sign in to the staff app, and staff endpoints refuse patient tokens (`native.workspace`).

## 6. Tests
```bash
vendor/bin/phpunit tests/Feature/NativeClient          # registry ↔ sidebar parity, guards, login, wrapper parity
(cd native_clients/client_app && flutter analyze && flutter test)
(cd native_clients/patient_app && flutter analyze && flutter test)
```
- **`DoctorQueueStatsScopeTest`:** `GET /api/mobile/doctor/queues/stats` takes optional `start_date` and `end_date`. With them the badges count the same date range as the list and the web stats bar (`getDoctorQueueCounts`); without them every day is counted (older app builds).
- **`PharmacyMobileTest`:** `mobile/pharmacy` is guarded like the sidebar (pharmacist and admins); the prescription queue answers a DataTables page when asked with `length` (the app) and the whole list otherwise (the web); returns and damages feeds and stats (`stats_only=1`); report dates apply (`date_from`/`date_to`).
- **`MyActivityTest`:** the Transactions Console's "What I did": `MyActivityService` declares each workbench's stages (reception booked; pharmacy billed, dispensed, returns; lab billed, sample, result, approved; imaging billed, result, approved) and `GET .../my-activity?workbench=&stage=&state=` returns the stage counts (with how many wait on others or need me), the items and rollups. A stage is shown when the user can do it (approval only for approvers when the hospital requires it) or did it in the period. `can_collect` follows the web's biller rule (`ReceptionWorkbenchController::userCanBill`); the cash views are separate (`getMyTransactions`, `getManageTransactions`, also on the reception routes now).
- **`ClinicalSearchCategoryTest`:** `context` returns the service category ids (`lab`, `imaging`, `procedure`, from settings) and `search-services` honours `category_id`, so lab and imaging searches are filtered by the server (the 15-row cap used to cut off every lab item for common terms).
- **`FileNumberManagementTest`:** `GET .../patient/next-file-number` returns `prefix_options` (the series a department may use, e.g. maternity `ANC-`/`PANC-`, from `Patient::getPrefixOptionsForWorkbench`) and `POST .../patient/check-file-number` answers `exists`/`patients` (warn, never block). The staff app's `FileNumberField` (Auto/Manual, series switch, recent numbers, live duplicate check) is used by the patient form (new, walk-in, department registrations, edit) and emergency intake. `POST /emergency/intake` honours `file_no` only when `file_no_manual=1` (kept in the `EX-` series); the web form never sends it.
- **`MedicationChartCycleTest`:** the chart's schedule, give, edit (comment optional), delete and calendar. The calendars include the end date's own doses, the prescribed calendar opens without a start date, and a schedule with a deleted dose still cannot be removed.
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

## Notes, AI toolbar and consultation frame (reference)
- **One toolbar:** `lib/features/shared/clinical_ai_toolbar.dart` (`ClinicalAiToolbar` + `AiNoteConfig`) is the native `admin.partials.speech_dictation`: AI Scribe Studio, Quick Polish (in place, Undo polish) and dictation. Doctor notes, nursing notes and maternity notes all use it; screens only supply an `AiNoteConfig` (`noteType` doctor | nurse | maternity, patient, studio target). `FieldSpec(..., ai: AiNoteConfig)` gives any `showFieldSheet` form this note field. The server's `llm_polish_enabled` (`App\Support\ClientSettings`, from `llm_config.polish_note_enabled`) gates Quick Polish; `llm_enabled` gates the rest. Polish and the studio go through `POST /api/mobile/doctor/llm/*` (LlmController), served to doctor, nursing and maternity.
- **One editor:** `lib/core/rich/rich_note_editor.dart` (`RichNoteController`, `RichNoteEditor`, Fleather) is the native CKEditor; `lib/core/rich/note_html.dart` converts between the HTML the server stores and the editable document (paragraphs, headings, bold/italic/underline, lists, quote; the AI's markdown is converted first). Notes are always saved as HTML. The controller also behaves as a `TextEditingController` so dictation keeps working with formatting intact. Do not run AI text through `plain()` (it flattens line breaks).
- **Consultation save model:** the notes editor reports HTML to `ConsultationScreen`, which autosaves after 10 s (`POST encounters/autosave`), shows the state in `_ConsultActionBar`, saves on **Save** (`PUT encounters/{id}/notes`) and saves first on **Conclude**. `RecordWorkspace` takes `bottomBar` (docked) and `phonePrimary` (section keys shown as phone pills, the rest in a **More** tile sheet, like the web's bottom navigation); sections have `shortLabel` and `tileColor`.
- **Tests:** `test/note_html_test.dart` (codec, dictation keeps formatting, AI markdown), `tests/Feature/NativeClient` (settings).
- **AI Scribe Studio and existing content:** like the web's `baselineEditorContent`, the toolbar hands the studio the note *as it stands in the editor now* (`AiNoteConfig.studio(baseline)`: HTML for the rich editor, unsaved edits included). The studio shows it in "Existing note in the editor", sends it as `editor_content` with every `clinical-scribe/process` call (LlmController: "Preserve and merge with transcript") and as `baseline_editor_content` when signing. After a doctor signs, the encounter reloads and the editor shows the merged note; nothing is left unsaved. Nurse and maternity studios get the sheet's text the same way.
- **AI calls are slow:** a structured note takes about 30 s, so the AI endpoints use `AuthedApi.aiTimeout` (180 s) instead of the default 30 s. A failed structuring (for example the provider's quota) stays visible in the note card.

