# Mobile App ↔ Web Parity Plan

## Overview
Comprehensive plan to bring the **Doctor App** and **Patient App** to full feature/UI/UX parity with the web (Laravel) application. This covers all tabs from Patient Info through Referrals, queue management, result rendering, and all missing functionality.

---

## CRITICAL BUGS — ✅ ALL FIXED

### ✅ BUG-1: Duplicate `ConsultationScreen` Class [Doctor App]
**File:** `consultation_screen.dart` — Removed legacy 9-tab duplicate class

### ✅ BUG-2: Duplicate `LabTab` class [Doctor App]
**File:** `lab_tab.dart` — Removed simplified Version 2, kept full Version 1

### ✅ BUG-3: Duplicate `ImagingTab` class [Doctor App]
**File:** `imaging_tab.dart` — Removed simplified Version 2, kept full Version 1

### ✅ BUG-4: Compilation errors in `referrals_tab.dart` [Doctor App]
**File:** `referrals_tab.dart` — Fixed: non-nullable `??`, `ApiResult.cast()`, missing `createReferral` args, immutable `status` field mutation

### ✅ BUG-5: `admission_history_tab.dart` return type mismatch [Doctor App]
**File:** `admission_history_tab.dart` — Fixed: properly parse `ApiResult` instead of casting to `List<dynamic>`

### ✅ BUG-6: Deprecated API warnings + unused imports
**Files:** `referrals_tab.dart`, `inj_imm_tab.dart`, `nurse_charts_tab.dart`, `admission_history_tab.dart` — All fixed

**Current state:** `flutter analyze` reports 0 errors, 0 warnings, 21 info-level style hints only.

---

## PHASE 1: Doctor App — Queue Screen Parity

### 1.1 Queue Architecture Restructure
**Web:** 2 main tabs — "My Appointment Calendar" + "Encounter History/Admissions" (5 sub-tabs)
**Mobile:** 3 flat tabs — New / Continuing / Previous

| # | Gap | Web Feature | Action |
|---|-----|-------------|--------|
| 1 | Tab structure mismatch | Web has 2 tabs with 5 sub-tabs on Tab 2 | Restructure: Tab 1 = "My Queue" (status pill filter), Tab 2 = "History" (sub-tabs: Previous Encounters, My Admissions, Other Admissions, My Referrals, All Referrals) |
| 2 | No 7 stat cards | Waiting/Vitals/Ready/In Consult/Scheduled/Completed/Total with live counts | Add horizontal card bar above list using `getQueueStats()` endpoint |
| 3 | No status pill filter bar | All/Waiting/Vitals/Ready/In Consult/Scheduled/Completed pills filtering the list | Add scrollable status pill bar with tap-to-filter + count badges |
| 4 | No date range filter | From/To date pickers with Fetch button | Add collapsible date range filter above list |
| 5 | No calendar view toggle | FullCalendar month/week/day with color-coded events | Add calendar view option (table_calendar package) with view toggle button |
| 6 | No auto-refresh | Web refreshes every 30 seconds | Add 30-second auto-refresh timer |

### 1.2 Queue Card Enhancements
| # | Gap | Web Feature | Action |
|---|-----|-------------|--------|
| 7 | No priority badges | Emergency (red) / Urgent (amber) / Routine (grey) visible on card | Add priority badge chip on each card |
| 8 | No source badges | Scheduled / Walk-in / Emergency source indicator | Add source chip (calendar/walk-in/ambulance icons) |
| 9 | No appointment time | Scheduled time shown on card | Display scheduled appointment time |
| 10 | No consultation mini-timer | Web shows elapsed time on in-consultation cards | Add mini timer on cards with status = In Consult |
| 11 | No patient photo/avatar | Patient photo from `photo_url` | Show photo if available, otherwise initials avatar (already has initials) |
| 12 | No triage note indicator | Emergency patients show triage note popover on hover | Add triage indicator icon → long-press for detail |
| 13a | No Check-In action | Web: Check-In button transitions patient from Scheduled → Waiting | Add Check-In action (swipe or long-press menu) |
| 13b | No Cancel action | Web: Cancel appointment with confirmation | Add Cancel action with confirmation dialog |
| 13c | No No-Show action | Web: Mark as No-Show (status 7) with confirmation prompt | Add No-Show action with confirmation |
| 13d | No Reschedule action | Web: reschedule modal (new date, time slot, custom time toggle, reason) + reschedule count badge | Add Reschedule dialog with date/time/reason + count badge |
| 13e | No Reassign Doctor action | Web: reassign modal (doctor dropdown, reason) — only for Scheduled status | Add Reassign dialog with doctor selector + reason |
| 13f | No Status 7 (No-Show) | Web has 7 queue statuses (1-7), mobile only handles 1-6 | Add No-Show status handling + display |
| 14 | No DataTable export | Web: Copy/Excel/CSV/PDF/Print buttons | Add share/export button (at least PDF/share) |

### 1.3 Queue Tab 2 — History Sub-tabs
**Web has 5 sub-tabs under "Encounter History/Admissions":**
| # | Sub-tab | Filters | Status |
|---|---------|---------|--------|
| 15 | Previous Encounters | Date range, Clinic, HMO | ❌ Missing — create with DataTable |
| 16 | My Admissions | Date range, HMO | ❌ Missing — create with DataTable |
| 17 | Other Admissions | Date range, Doctor, HMO | ❌ Missing — create with DataTable |
| 18 | My Referrals | Date range, Status, Direction, Type | ❌ Missing — create with DataTable |
| 19 | All Referrals | Date range, Status, Clinic, Doctor, Type | ❌ Missing — create with DataTable |

### 1.4 Queue Modals (Web has, Mobile missing)
| # | Modal | Purpose | Action |
|---|-------|---------|--------|
| 20 | Reschedule Modal | New date, time slot, reason | Create reschedule dialog |
| 21 | Reassign Doctor Modal | New doctor dropdown, reason | Create reassign dialog |
| 22 | Referral Detail Modal | View + accept/decline/print referral | Create referral detail bottom sheet |

---

## PHASE 2: Doctor App — Consultation Action Bar & Encounter Lifecycle

### 2.1 Action Bar (Top of Consultation Screen)
**Web has a rich action bar above tabs. Mobile has a simple AppBar.**

| # | Gap | Web Feature | Action |
|---|-----|-------------|--------|
| 23 | No consultation timer | ⏱️ Running timer with RUNNING/PAUSED state, auto-sync every 60s | Add timer widget in AppBar with start/pause/resume |
| 24 | No admission status badge | 🛏️ Badge showing Admitted/Pending/Discharged | Add admission status badge in AppBar |
| 25 | No Request Admission button | Button in action bar to admit from consult | Add admit button in AppBar actions |
| 26 | No Request Discharge button | Button in action bar to request discharge | Add discharge button in AppBar actions |
| 27 | No Refer Patient quick button | Quick referral shortcut in action bar | Add referral shortcut icon in AppBar |
| 28 | No Medical Report Builder | Full report builder with left sidebar data accordion + right WYSIWYG editor | Add medical report builder screen (see Phase 3A) |

### 2.2 Conclude Encounter Enhancement
**Web has rich conclude modal. Mobile has simple End/Admit dialog.**

| # | Gap | Web Feature | Action |
|---|-----|-------------|--------|
| 29 | No encounter summary in conclude | 4-card summary grid (Diagnosis, Labs, Imaging, Prescriptions counts) | Add summary cards before action buttons |
| 30 | No follow-up scheduling | Toggle → date picker, time picker, priority dropdown, follow-up notes, pre-paid checkbox | Add follow-up appointment section with all fields |
| 31 | No final notes | Textarea for closing notes | Add final notes field |
| 31b | No pre-paid checkbox | Web: pre-paid toggle skips billing at check-in | Add pre-paid checkbox in follow-up scheduling |
| 32 | No discharge option in conclude | Discharge with reason category + note | Add discharge option alongside End/Admit |

### 2.3 Admit/Discharge Modal Enhancement
| # | Gap | Web Feature | Action |
|---|-----|-------------|--------|
| 33 | No reason category for admission | Dropdown: Emergency, Elective, Transfer, Observation, etc. | Add reason category selector |
| 34 | No ward/bed selection | Ward dropdown with live bed occupancy radio buttons (showing X/Y beds occupied per ward) + priority selector | Add ward/bed selection with occupancy display (needs API endpoint) |
| 35 | No discharge workflow | Discharge form: reason category + discharge note | Add discharge dialog with reason + note |

---

## PHASE 3: Doctor App — Tab-by-Tab Feature Parity (All 13 Tabs)

### 3.0 Cross-Tab Features
| # | Gap | Action |
|---|-----|--------|
| 36 | No Previous/Next buttons | Add "← Previous" / "Next →" buttons at bottom of EVERY tab matching web |
| 36b | No "Save & Next" button on Notes tab | Web has Save & Next that saves diagnosis+notes and advances to Labs tab | Add Save & Next button on Clinical Notes tab |

### Tab 1: Patient Data
| # | Web Feature | Mobile Status | Action |
|---|-------------|--------------|--------|
| 37 | Patient photo | ✅ Has | — |
| 38 | Demographics table | ✅ Has | — |
| 39 | Blood group/genotype | ✅ Has | — |
| 40 | Insurance/HMO info | ✅ Has | — |
| 41 | Next of kin details | ✅ Has | — |
| 42 | Allergy management (add/remove) | ✅ Has | — |
| 43 | Admission info section (bed/ward/unit/status badges) | ❌ Missing | Add current admission card with bed/ward/clinic details + status badges |
| 44 | Old records link / Patient encounter history link | ❌ Missing | Add button to navigate to History tab or patient records |
| 44b | Patient Profile Forms accordion | ❌ Missing | Web has "See Patient Profiles" accordion with DataTable of filled forms + "Fill New Patient Profile" button → dynamic form modal. Add profile forms section with form list + fill new form capability |

### Tab 2: Vitals / Allergies
| # | Web Feature | Mobile Status | Action |
|---|-------------|--------------|--------|
| 45 | Vital recording form (10 fields) | ✅ Has | — |
| 46 | BMI auto-calculation | ✅ Has (computed getter) | — |
| 47 | Pain scale 0-10 visual buttons | ❌ Missing | Add tappable 0-10 button row (colored: green→orange→red) |
| 48 | Status indicators (normal/warning/critical) | ✅ Has (color-coded vital cards) | — |
| 49 | Vitals history display | ✅ Has | — |
| 50 | Allergies section | ✅ In Patient tab | — |

### Tab 3: Nurse Charts
**Web has rich date-filtered charts with 4 inner tabs. Mobile is basic read-only.**

| # | Web Feature | Mobile Status | Action |
|---|-------------|--------------|--------|
| 51 | Date range filter + Apply/Reset | ❌ Missing | Add From/To date range filter with Apply/Reset buttons |
| 52 | Medication Chart sub-tab | ❌ Missing | Add medication chart grid — AJAX-loaded calendar-style grid showing all medications per day with status icons (scheduled/given/missed/discontinued), color-coded, tappable items open medication detail bottom sheet |
| 53 | Fluid Intake/Output sub-tab | ❌ Missing | Add fluid I/O chart display |
| 54 | Solid Intake/Output sub-tab | ❌ Missing | Add solid I/O display |
| 55 | Nursing Notes History (DataTable) | ⚠️ Basic cards | Enhance with proper table-style display + date filtering |
| 56 | Summary stats from filtered range | ❌ Missing | Add stats summary bar above charts |

### Tab 4: Injection/Immunization History
| # | Web Feature | Mobile Status | Action |
|---|-------------|--------------|--------|
| 57 | Injection history list | ✅ Has (filters procedures) | — |
| 58 | Immunization records | ✅ Basic display | — |
| 59 | Dedicated endpoint vs keyword filter | ⚠️ Keyword filter | Should use dedicated endpoint if available |

### Tab 5: Clinical Notes / Diagnosis
**Web has CKEditor, template insert, favorites, per-diagnosis status. Mobile has plain text.**

| # | Web Feature | Mobile Status | Action |
|---|-------------|--------------|--------|
| 60 | Notes history sub-tab | ⚠️ Partial | Verify history DataTable exists matching web |
| 61 | ICPC-2 diagnosis search | ✅ Has | — |
| 62 | Reasons for encounter | ✅ Has | — |
| 63 | Presenting complaints field | ✅ Has | — |
| 64 | HPI field | ✅ Has | — |
| 65 | Doctor's diagnosis field | ✅ Has | — |
| 66 | Clinical notes textarea | ✅ Has | — |
| 67 | Auto-save notes | ✅ Has (30s timer) | — |
| 68 | Diagnosis Favorites dropdown | ❌ Missing | Add favorites dropdown to load saved diagnosis sets |
| 69 | Save as Favorite button | ❌ Missing | Add "Save as Favorite" modal to save current diagnosis set |
| 70 | CKEditor rich text | ⚠️ Plain text only | Replace with rich text editor (flutter_quill) for formatting |
| 70b | HTML content rendering | ⚠️ No HTML rendering | Existing web notes are HTML — add flutter_html widget to render HTML notes from web in read-only history views |
| 71 | Insert Template button + Template search modal | ❌ Missing | Add template insertion with search/preview (needs API endpoint) |
| 72 | Autosave visual indicator | ⚠️ Background only | Add visible "Autosaving..."/"Autosaved ✓" indicator |
| 73 | Per-diagnosis comment fields (Query/Differential/Confirmed) | ❌ Missing | Add per-diagnosis toggles and comment fields |
| 74 | Diagnosis status (Acute/Chronic/Recurrent) | ❌ Missing | Add per-diagnosis status selector |
| 75 | 10-second autosave interval | ⚠️ 30 seconds | Match web's 10-second interval |
| 76 | Patient Profile Accordion (view/add forms) | ❌ Missing | Add patient profile accordion section if web has it |

### Tab 6: Laboratory Services
| # | Web Feature | Mobile Status | Action |
|---|-------------|--------------|--------|
| 77 | Lab History + New Request sub-tabs | ✅ Has | — |
| 78 | Service search autocomplete | ✅ Has | — |
| 79 | Auto-save on add | ✅ Has | — |
| 80 | Result display (green box) | ✅ Has | — |
| 81 | Tracking details (billed by, sample taken by, etc.) | ✅ Has (V1) | — |
| 82 | Rejection reason display | ✅ Has (V1) | — |
| 83 | Attachment chips | ✅ Has (V1) | Enhance with actual file download/viewer |
| 84 | Edit note | ✅ Has (V1) | — |
| 84b | Auto-save status line | ⚠️ No visual indicator | Web shows auto-save status line below selected items. Add "Saving..." / "Saved ✓" indicator on Labs/Imaging/Meds tabs |
| 84c | Price + HMO coverage display | ❌ Missing | Web shows price column and HMO approval status on each selected service row. Add price + HMO indicator |
| 85 | Treatment Plans button + modal | ❌ Missing | Add treatment plan selection modal (needs API endpoint) |
| 86 | Save as Template button | ❌ Missing | Add template saving (needs API endpoint) |
| 87 | Delete with reason | ❌ Missing reason dropdown | Add reason dropdown: Ordered by mistake / Patient declined / Already done elsewhere / Duplicate request / Changed treatment plan / Other (with free-text). Match web #deleteConfirmModal |
| 87b | Delete Denied modal | ❌ Missing | Web shows a "Deletion Denied" modal when delete fails (e.g., already billed). Add error dialog explaining why deletion is blocked |

### Tab 7: Imaging Services
| # | Web Feature | Mobile Status | Action |
|---|-------------|--------------|--------|
| 88 | Same structure as Labs | ✅ Has (V1) | — |
| 89 | Treatment Plans modal | ❌ Missing | Same as Labs |
| 90 | Save as Template | ❌ Missing | Same as Labs |
| 91 | Image/DICOM viewer for attachments | ❌ Missing | Add basic image viewer for imaging attachments |

### Tab 8: Medications
| # | Web Feature | Mobile Status | Action |
|---|-------------|--------------|--------|
| 92 | Drug History + New Rx sub-tabs | ✅ Has | — |
| 93 | Product search autocomplete | ✅ Has | — |
| 94 | Stock availability check | ✅ Has | — |
| 95 | Frequency/Duration/Route fields | ✅ Has | — |
| 96 | Special instruction | ✅ Has | — |
| 97 | Dose Mode Toggle (Structured/Simple) | ❌ Missing | Add segmented control: **Structured** (separate fields: amount, unit, route, frequency, duration, quantity — default) vs **Simple** (free text e.g. "500mg BD × 5 days"). Match web's dose-mode-toggle partial |
| 98 | Re-prescribe from encounter dropdown | ❌ No UI | API exists (`getRecentEncounters()`, `getEncounterItems()`, `rePrescribe()`) — add dropdown UI to select past encounter → auto-populate drugs |
| 99 | Treatment Plans modal | ❌ Missing | Same as Labs/Imaging |
| 100 | Save as Template | ❌ Missing | Same as Labs/Imaging |

### Tab 9: Procedures
| # | Web Feature | Mobile Status | Action |
|---|-------------|--------------|--------|
| 101 | Procedure History + New Request sub-tabs | ✅ Has | — |
| 102 | Priority/status/category fields | ✅ Has | — |
| 103 | Procedure detail page | ✅ Has (team, notes, cancel) | — |
| 104 | Procedure Team Management (add/remove members) | ✅ Has | — |
| 105 | Procedure Notes (add/delete) | ✅ Has | — |
| 106 | Cancel procedure with reason | ✅ Has | — |
| 107 | Treatment Plans modal | ❌ Missing | Same as Labs/Imaging/Meds |
| 108 | Save as Template | ❌ Missing | Same as Labs/Imaging/Meds |
| 109 | Procedure Notes sub-modal with CKEditor | ⚠️ Plain text | Use rich text if web uses CKEditor for procedure notes |

### Tab 10: History
| # | Web Feature | Mobile Status | Action |
|---|-------------|--------------|--------|
| 110 | Previous encounters list | ✅ Has (paginated) | — |
| 111 | Lab history list | ✅ Has (paginated) | — |
| 112 | Imaging history list | ✅ Has (paginated) | — |
| 113 | Medication history list | ✅ Has (paginated) | — |
| 114 | Procedure history list | ✅ Has (paginated) | — |
| 115 | Date range filter per sub-tab | ❌ Missing | Add date range filter widget to each history sub-tab |

### Tab 11: Summary
| # | Web Feature | Mobile Status | Action |
|---|-------------|--------------|--------|
| 116 | Status badge | ✅ Has | — |
| 117 | Diagnosis display | ✅ Has | — |
| 118 | Stats cards (labs/imaging/rx/procedures) | ✅ Has | — |
| 119 | All items list | ✅ Has | — |
| 120 | Notes excerpt | ✅ Has | — |

### Tab 12: Admissions
| # | Web Feature | Mobile Status | Action |
|---|-------------|--------------|--------|
| 121 | Current admission display | ✅ Has | — |
| 122 | Admission history list | ✅ Has | — |
| 123 | Bed/ward/clinic details | ✅ Has | — |
| 124 | Admit/discharge dates | ✅ Has | — |

### Tab 13: Referrals
**Web has rich referral system with internal/external types, 3 sub-tabs, and detailed form. Mobile is basic.**

| # | Web Feature | Mobile Status | Action |
|---|-------------|--------------|--------|
| 125 | Referral creation form | ✅ Has (basic) | — |
| 126 | Internal/External type toggle | ❌ Missing | Add type toggle: Internal (clinic/doctor dropdowns) vs External (facility name, doctor name, address, phone) |
| 127 | External facility fields | ❌ Missing | Add: facility name, external doctor name, address, phone fields in create form |
| 128 | Provisional diagnosis field | ❌ Missing | Add provisional diagnosis text field |
| 129 | Clinical summary field | ❌ Missing | Add clinical summary text field |
| 130 | This Encounter referrals sub-tab | ✅ Has | — |
| 131 | All Patient Referrals sub-tab | ❌ Missing | Add "All Patient Referrals" sub-tab using `getPatientReferrals()` endpoint |
| 132 | Incoming To Me sub-tab | ❌ Missing | Add "Incoming To Me" sub-tab using `getIncomingReferrals()` endpoint |
| 133 | Count badges on sub-tabs | ❌ Missing | Add count badges to each referral sub-tab |
| 134 | Accept/Decline referral actions | ⚠️ Status update only | Add explicit Accept/Decline buttons for incoming referrals |
| 135 | Print referral | ❌ Missing | Add print/share referral functionality |

### Referral Model Missing Fields
| # | Field | Status | Action |
|---|-------|--------|--------|
| 136 | `type` (internal/external) | ❌ Missing | Add to `Referral` model |
| 137 | `externalFacilityName` | ❌ Missing | Add to model |
| 138 | `externalDoctorName` | ❌ Missing | Add to model |
| 139 | `externalAddress` | ❌ Missing | Add to model |
| 140 | `externalPhone` | ❌ Missing | Add to model |
| 141 | `clinicalSummary` | ❌ Missing | Add to model |
| 142 | `provisionalDiagnosis` | ❌ Missing | Add to model |
| 143 | `actionNotes` | ❌ Missing | Add to model |
| 144 | `actionedAt` | ❌ Missing | Add to model |

### 3A: Medical Report Builder (Full Screen)
**Web has a complete report builder modal with left data sidebar + right WYSIWYG editor. Mobile has nothing.**

| # | Web Feature | Mobile Status | Action |
|---|-------------|--------------|--------|
| R1 | Left sidebar: 8 copyable data accordion panels (Demographics, Vitals, Diagnoses, Medications, Labs, Imaging, Procedures, Notes) | ❌ Missing | Create left panel with expandable sections, each with "Copy to Editor" button |
| R2 | Report Title + Report Date header fields | ❌ Missing | Add title text field + date picker at top |
| R3 | Report Status badge (Draft/Finalized) | ❌ Missing | Add status indicator |
| R4 | Right panel: CKEditor 5 WYSIWYG editor | ❌ Missing | Add rich text editor (flutter_quill) for composing report body |
| R5 | Save Draft button | ❌ Missing | Add save draft action |
| R6 | Finalize button | ❌ Missing | Lock report as final (non-editable) |
| R7 | Print button / Finalize & Print | ❌ Missing | Add PDF generation + share/print |
| R8 | Medical Report History modal | ❌ Missing | Add previous reports list with load/delete actions |
| R9 | Load previous report into editor | ❌ Missing | Tap historical report → load into editor for editing |

---

## PHASE 4: Doctor App — Home Screen & Dashboard Parity

| # | Web Feature | Mobile Status | Action |
|---|-------------|--------------|--------|
| 145 | Dashboard stat cards (Procedures Seen Today, Admitted Today, Booked Today) | ⚠️ Has queue stats only | Add procedure/admission/booking counts matching web dashboard |
| 146 | Results Tab (Lab & Imaging Results viewer for approval) | ❌ Placeholder only | Implement Lab/Imaging results approval workflow screen |
| 147 | Profile screen with user details | ⚠️ Basic (name, email, sign out) | Add full profile with staff details, roles, specialization display |
| 148 | Settings screen | ❌ Placeholder | Add settings: notification toggles, auto-save interval, theme preferences |
| 149 | Notifications | ❌ No bell notification system | Add push notification or in-app notification |

---

## PHASE 5: Patient App — Feature Parity

### 5.1 Missing Screens
| # | Web/API Feature | Mobile Status | Action |
|---|-----------------|--------------|--------|
| 150 | Admissions history | ❌ API endpoint exists (`getAdmissions()`), no screen | Create `admissions_screen.dart` with paginated list — bed, ward, dates, status |
| 151 | Referrals view | ❌ No screen or endpoint | Add referrals section in encounter detail + standalone referrals screen |
| 152 | Medical Records screen | ❌ Placeholder (empty `onTap`) | Implement file/document viewer screen |
| 153 | Settings screen | ❌ Placeholder (empty `onTap`) | Implement settings: notifications, language, privacy |

### 5.2 Encounter Detail Screen Enhancements
| # | Web Feature | Mobile Status | Action |
|---|-------------|--------------|--------|
| 154 | Encounter header | ✅ Has | — |
| 155 | Diagnosis section | ✅ Has | — |
| 156 | Reasons for encounter | ✅ Has (in detail) | — |
| 157 | Presenting complaints display | ✅ Has | — |
| 158 | Vital signs display | ✅ Has (color-coded) | — |
| 159 | Lab tests with results | ✅ Has (status + result) | — |
| 160 | Imaging with results | ✅ Has (status + report) | — |
| 161 | Prescriptions | ✅ Has (medication + dose) | — |
| 162 | Procedures | ✅ Has (name + date) | — |
| 163 | Clinical notes | ✅ Has | — |
| 164 | Lab result tracking (sample taken by, approved by, etc.) | ❌ Missing | Add tracking rows for each lab result |
| 165 | Imaging tracking details | ❌ Missing | Add tracking rows for each imaging result |
| 166 | Referral information section | ❌ Missing | Add referrals section to encounter detail |
| 167 | Admission info in encounter | ❌ Missing | Add admission status section if patient was admitted |

### 5.3 Lab Results Screen Enhancements
| # | Web Feature | Mobile Status | Action |
|---|-------------|--------------|--------|
| 168 | Service name + status | ✅ Has | — |
| 169 | Result text display | ✅ Has (green box) | — |
| 170 | Notes display | ✅ Has (italic text) | — |
| 171 | Tracking details (sample taken by, billed by, approved by) | ❌ Missing | Add tracking detail rows below each result |
| 172 | Attachments (file links) | ❌ Missing | Add attachment chips with file viewer |
| 173 | Rejection reason | ❌ Missing | Add red rejection box for status=rejected labs |
| 174 | Date range filter | ❌ Missing | Add date range filter controls |
| 175 | Status filter pills (All/Requested/Sample Taken/Ready) | ❌ Missing | Add status pill filter bar |
| 176 | Search | ❌ Missing | Add search bar to filter by service name |

### 5.4 Imaging Results Screen Enhancements
| # | Web Feature | Mobile Status | Action |
|---|-------------|--------------|--------|
| 177 | Service name + status | ✅ Has | — |
| 178 | Report text display | ✅ Has (green box) | — |
| 179 | Attachments (images) | ❌ Missing | Add image viewer for imaging attachments |
| 180 | Rejection reason | ❌ Missing | Add red rejection box |
| 181 | Clinical indication note | ❌ Missing | Add note display |
| 182 | Date range filter | ❌ Missing | Add filter controls |
| 183 | Status filter pills | ❌ Missing | Add status pill filter bar |

### 5.5 Prescriptions Screen Enhancements
| # | Web Feature | Mobile Status | Action |
|---|-------------|--------------|--------|
| 184 | Product name + status | ✅ Has | — |
| 185 | Dose display | ✅ Has | — |
| 186 | Quantity display | ✅ Has | — |
| 187 | Frequency/Duration/Route display | ❌ Missing | Add extended dose details (available in encounter detail model) |
| 188 | Special instruction display | ❌ Missing | Add yellow box for special instructions |
| 189 | Date range filter | ❌ Missing | Add filter controls |
| 190 | Status filter pills (Prescribed/Billed/Dispensed) | ❌ Missing | Add status pill bar |

### 5.6 Procedures Screen Enhancements
| # | Web Feature | Mobile Status | Action |
|---|-------------|--------------|--------|
| 191 | Procedure name + status | ✅ Has | — |
| 192 | Priority chip | ✅ Has (colored) | — |
| 193 | Scheduled date | ✅ Has | — |
| 194 | Outcome display | ✅ Has | — |
| 195 | Pre-operative notes | ❌ Missing | Add pre-op notes display |
| 196 | Date range filter | ❌ Missing | Add filter controls |

### 5.7 Vitals Screen Enhancements
| # | Web Feature | Mobile Status | Action |
|---|-------------|--------------|--------|
| 197 | Vital signs grid | ✅ Has (3-column, color-coded) | — |
| 198 | BMI auto-calculation | ✅ Has (computed) | — |
| 199 | Vital trend charts over time | ❌ Missing | Add line chart for selected vital over time (fl_chart package) |
| 200 | Date range filter | ❌ Missing | Add filter controls |

### 5.8 Home Screen / Dashboard Enhancements
| # | Web Feature | Mobile Status | Action |
|---|-------------|--------------|--------|
| 201 | Greeting card + patient ID | ✅ Has | — |
| 202 | HMO/Plan info | ✅ Has | — |
| 203 | Blood Group/Genotype chips | ✅ Has | — |
| 204 | Quick action tiles | ✅ Has (6 tiles) | — |
| 205 | Upcoming appointments card | ❌ Missing | Add upcoming appointments display (needs API endpoint or infer from encounters) |
| 206 | Active admission banner | ❌ Missing | Show banner if patient is currently admitted |
| 207 | Emergency contact card | ❌ Missing | Show hospital emergency contact |
| 208 | Notifications button | ❌ Placeholder (SnackBar "coming soon") | Implement push notification integration |

### 5.9 Patient App Model Enhancements
| # | Model | Missing Fields | Action |
|---|-------|---------------|--------|
| 209 | PatientLabResult | tracking fields (billedBy, sampleTakenBy, approvedBy, etc.) | Add all tracking fields from API response |
| 210 | PatientLabResult | attachments, rejectionReason | Add attachment + rejection support |
| 211 | PatientImagingResult | tracking fields, attachments, rejectionReason | Same as labs |
| 212 | PatientPrescription | frequency, duration, durationUnit, route, specialInstruction | Add extended dose fields |
| 213 | PatientProcedure | preNotes, operatingRoom, cancellationReason | Add detail fields |
| 214 | NEW: PatientAdmission model | All admission fields | Create admission model |
| 215 | NEW: PatientReferral model | All referral fields | Create referral model |

### 5.10 Patient App — Status Code Alignment
**Current mobile status labels differ from API:**

| Entity | Status 0 | Status 1 | Status 2 | Status 3 | Status 4 | Status 5 | Status 6 |
|--------|----------|----------|----------|----------|----------|----------|----------|
| Lab | Dismissed | Requested | Billed | Sample Taken | Result Ready | Pending Approval | Rejected |
| Imaging | Dismissed | Requested | Billed | - | Result Ready | - | - |
| Prescription | Dismissed | Prescribed | Billed | Dispensed | - | - | - |

| # | Gap | Action |
|---|-----|--------|
| 216 | Lab status labels incomplete | Update to show all 7 statuses (0-6) matching doctor app |
| 217 | Missing status 0 (Dismissed) handling | Add dismissed status display for all entities |

---

## PHASE 6: Shared UI/UX Improvements

### 6.1 Shared Widgets to Create (Doctor + Patient Apps)
| # | Widget | Purpose | Used By |
|---|--------|---------|---------|
| 218 | `DateRangeFilter` | Reusable From/To date picker with Apply/Reset | Queue, History, all list screens |
| 219 | `StatusPillBar` | Horizontal scrollable status filter pills with counts | Queue, Lab/Imaging results |
| 220 | `DeleteReasonDialog` | Delete confirmation with reason dropdown + notes | All delete actions in doctor app |
| 221 | `ConsultationTimer` | Timer widget with start/pause/resume + sync | Consultation action bar, queue cards |
| 222 | `TreatmentPlansModal` | Treatment plan picker (search + select + apply) | Labs, Imaging, Meds, Procedures tabs |
| 223 | `RichTextDisplay` | HTML rendering for clinical notes from web | Notes display in both apps (flutter_html) |
| 224 | `FileViewer` | Document/image viewer for attachments | Labs, Imaging attachments in both apps |
| 225 | `TabNavigationButtons` | Previous/Next + Save & Next buttons for bottom of each tab | All 13 consultation tabs |
| 225b | `AutosaveIndicator` | "Saving..." / "Saved ✓" status line widget | Labs, Imaging, Meds, Notes tabs |
| 225c | `RichTextDisplay` (flutter_html) | HTML content renderer for web-originated clinical notes | Notes history, procedure notes, report viewer |
| 225d | `DeletionDeniedDialog` | Error dialog explaining why deletion is blocked | All delete actions when API rejects |
| 225e | `QueueActionMenu` | Long-press popup: Check-In, Reschedule, Reassign, Cancel, No-Show | Queue cards |
| 225f | `RescheduleDialog` | Date + Time Slot + Custom time toggle + Reason + Reschedule count badge | Queue actions |
| 225g | `ReassignDoctorDialog` | Doctor dropdown + Reason | Queue actions |
| 225h | `MedicationDetailSheet` | Read-only medication detail bottom sheet (dose, route, timing, administered by) | Nurse Charts medication chart items |

### 6.2 Theming & Visual Consistency
| # | Enhancement | Action |
|---|-------------|--------|
| 226 | Status color consistency | Ensure all status colors match web: green=complete, amber=in-progress, red=critical, blue=info |
| 227 | Priority color palette | Standardize: Emergency=red, Urgent=amber, Routine=grey |
| 228 | Dark mode support | Add dark mode toggle in settings (both apps support Material 3) |
| 229 | Consistent card styling | Match web's card shadows, border radius, padding across all cards |
| 230 | Consistent empty states | Standardize empty state styling across all screens |

### 6.3 Navigation & UX
| # | Enhancement | Action |
|---|-------------|--------|
| 231 | Tab navigation buttons | Previous/Next at bottom of every consultation tab |
| 232 | Swipe between tabs | Ensure TabBarView swipe works for all 13 tabs |
| 233 | Return-to-top FAB | Add scroll-to-top button on long lists |
| 234 | Pull-to-refresh everywhere | Ensure every list screen has pull-to-refresh |
| 235 | Loading skeletons | Replace CircularProgressIndicator with shimmer loading skeletons |

---

## Implementation Order

### Sprint 1: Critical Bug Fixes + Doctor Queue ✅ DONE
1. ~~BUG-1~~: Remove duplicate `ConsultationScreen` class ✅
2. ~~BUG-2~~: Remove duplicate `LabTab` version ✅
3. ~~BUG-3~~: Remove duplicate `ImagingTab` version ✅
4. ~~BUG-4~~: Fix referrals_tab.dart compilation errors ✅
5. ~~BUG-5~~: Fix admission_history_tab.dart return type ✅
6. ~~BUG-6~~: Fix deprecated APIs + unused imports ✅

### Sprint 2: Doctor Queue Screen Overhaul
7. Restructure queue to 2 main tabs: "My Queue" + "History"
8. Add 7-card stats bar with live counts
9. Add status pill filter bar (All/Waiting/Vitals/Ready/In Consult/Scheduled/Completed/No-Show)
10. Add date range filter (collapsible)
11. Queue card enhancements: priority badges, source badges, appointment time, mini-timer
12. Queue actions: Check-In, Reschedule (with count badge + custom time), Reassign, Cancel, No-Show
13. Auto-refresh every 30 seconds
14. Calendar view toggle (table_calendar package)

### Sprint 3: Queue History Sub-tabs
15. Previous Encounters sub-tab with date/clinic/HMO filters
16. My Admissions sub-tab with date/HMO filters
17. Other Admissions sub-tab with date/doctor/HMO filters
18. My Referrals sub-tab with date/status/direction/type filters
19. All Referrals sub-tab with date/status/clinic/doctor/type filters
20. Referral Detail bottom sheet with Accept/Decline/Print

### Sprint 4: Consultation Action Bar + Lifecycle
21. Consultation timer widget (AppBar with start/pause/resume + 60s sync)
22. Admission status badge in AppBar
23. Request Admission / Request Discharge buttons in AppBar
24. Enhanced conclude dialog: 4 summary cards + follow-up scheduling (date/time/priority/notes/pre-paid) + final notes
25. Admit modal: reason category + ward/bed with occupancy + priority
26. Discharge modal: reason category + discharge summary + follow-up instructions
27. Tab navigation: Previous/Next + Save & Next buttons

### Sprint 5: Clinical Notes Enhancement
28. Autosave indicator ("Saving..."/"Saved ✓") — reduce interval to 10s
29. Diagnosis Favorites dropdown + Save as Favorite modal
30. Insert Template modal with search/preview
31. Per-diagnosis status (Query/Differential/Confirmed) + course (Acute/Chronic/Recurrent)
32. Rich text editor (flutter_quill) for notes
33. HTML rendering (flutter_html) for existing web notes in history
34. Patient Profile Forms accordion in Patient Data tab
35. Save & Next button on Clinical Notes tab

### Sprint 6: Clinical Orders Enhancement
36. Delete Reason dialog (6 reason categories + notes + Other free-text)
37. Delete Denied dialog for API rejections
38. Auto-save status line on Labs/Imaging/Meds tabs
39. Price + HMO coverage display on selected service rows
40. Treatment Plans modal (all 4 tabs: Labs, Imaging, Meds, Procedures)
41. Save as Template (all 4 tabs)
42. Dose mode toggle: Structured (amount/unit/route/frequency/duration/qty) vs Simple (free text)
43. Re-prescribe from previous encounter UI (dropdown + auto-populate)

### Sprint 7: Referrals + Admissions Enhancement
44. Internal/External referral type toggle
45. External fields: facility name, doctor, address, phone
46. Provisional diagnosis + clinical summary fields
47. 3 referral sub-tabs (This Encounter / All Patient / Incoming To Me) with count badges
48. Accept/Decline buttons for incoming referrals
49. Print/Share referral
50. Referral model: add 9 missing fields

### Sprint 8: Medical Report Builder
51. Report builder screen with split layout
52. Left panel: 8 copyable data accordion sections
53. Right panel: flutter_quill WYSIWYG editor
54. Report title + date + status badge
55. Save Draft / Finalize / Print / Finalize & Print actions
56. Report history modal with load/delete

### Sprint 9: Nurse Charts + Remaining Tabs
57. Nurse Charts: date range filter + summary stats
58. Medication chart grid (calendar-style, tappable → medication detail sheet)
59. Fluid I/O chart (periods, records table, totals)
60. Solid I/O chart
61. Nursing notes history with DataTable-style display
62. Pain scale 0-10 visual buttons (Vitals tab)
63. History tab: date range filter per sub-tab
64. Procedure notes rich text (flutter_quill)

### Sprint 10: Doctor App Dashboard + Results
65. Dashboard: add procedure/admission/booking counts
66. Results tab: implement Lab/Imaging results approval workflow
67. Profile: full staff details, roles, specialization
68. Settings: notification toggles, auto-save interval, theme
69. Notifications (push or in-app)

### Sprint 11: Patient App — New Screens
70. Admissions screen (paginated list with bed/ward/dates/status)
71. Referrals screen (standalone)
72. Medical Records screen (document/file viewer)
73. Settings screen (notifications, language, privacy)
74. Upcoming appointments card on dashboard
75. Active admission banner on dashboard

### Sprint 12: Patient App — Detail Enhancements
76. Encounter detail: add referrals section + admission info section
77. Lab results: tracking details (billedBy, sampleTakenBy, approvedBy) + attachments + rejection reason
78. Imaging results: tracking + attachments + rejection + clinical indication
79. Prescriptions: frequency/duration/route/specialInstruction display
80. Procedures: pre-operative notes display
81. All list screens: date range + status filter pills + search
82. Patient models: add all missing fields (tracking, attachments, dose details)

### Sprint 13: Patient App — Vitals + Status
83. Vital trend charts over time (fl_chart)
84. Date range filter on vitals
85. Status code alignment (all 7 lab statuses, dismissed handling)
86. Emergency contact card on dashboard

### Sprint 14: Polish & Shared
87. Shared theme consistency (status colors, priority colors, card styling)
88. Dark mode toggle (both apps)
89. Loading skeletons (shimmer)
90. Scroll-to-top FAB on long lists
91. Pull-to-refresh audit (every list screen)
92. Swipe-between-tabs audit (all 13 tabs)

---

## Files to Modify

### Doctor App — Existing Files
| File | Changes |
|------|---------|
| `consultation_screen.dart` | ~~Remove duplicate class~~✅, add timer, admission badge, enhanced conclude, tab nav buttons |
| `queue_screen.dart` | Restructure to 2 tabs, add stats bar, status pills, date filter, priority/source badges, mini-timer, queue actions (Check-In/Reschedule/Reassign/Cancel/No-Show), auto-refresh, calendar view |
| `clinical_notes_tab.dart` | Autosave indicator (10s), template insert, favorites, per-diagnosis status/course, rich text editor, Save & Next button |
| `referrals_tab.dart` | Internal/external toggle, 3 sub-tabs, external fields, accept/decline, print/share |
| `vitals_tab.dart` | Pain scale 0-10 visual selector, nav buttons |
| `nurse_charts_tab.dart` | Date range filter, 4 sub-tabs (medication chart grid, fluid I/O, solid I/O, nursing notes) |
| `medications_tab.dart` | Dose mode toggle (Structured/Simple), re-prescribe UI, auto-save line, price/HMO display |
| `lab_tab.dart` | ~~Remove duplicate~~✅, delete with reason, auto-save line, price/HMO, treatment plans, save template |
| `imaging_tab.dart` | ~~Remove duplicate~~✅, same enhancements as lab_tab |
| `procedures_tab.dart` | Treatment plans, save template, procedure notes rich text |
| `patient_info_tab.dart` | Admission info section, patient profile forms accordion, nav buttons |
| `history_tab.dart` | Date range filters per sub-tab |
| `admission_history_tab.dart` | ~~Fix return type~~✅ |
| `encounter_models.dart` | Add 9 Referral fields, add report model |
| `encounter_api_service.dart` | Add: discharge, ward list, timer sync/pause, note templates, treatment plans, follow-up, reschedule, reassign, check-in, cancel, no-show, report CRUD, profile forms |
| `home_screen.dart` | Add procedure/admission/booking stat cards |
| `results_screen.dart` | Implement lab/imaging results approval workflow |
| `profile_screen.dart` | Full staff details, roles, specialization |

### Doctor App — New Files
| File | Purpose |
|------|---------|
| NEW: `report_builder_screen.dart` | Medical report builder (split layout, data accordion, WYSIWYG editor) |
| NEW: `queue_history_tabs.dart` | 5 history sub-tabs (Previous Encounters, My Admissions, Other Admissions, My Referrals, All Referrals) |
| NEW: `settings_screen.dart` | Notification toggles, auto-save interval, theme preferences |

### Patient App — Existing Files
| File | Changes |
|------|---------|
| `home_screen.dart` | Add upcoming appointments, admission banner, emergency contact |
| `encounter_detail_screen.dart` | Add referrals section, admission info section |
| `lab_results_screen.dart` | Add tracking details, attachments, rejection reason, date/status filters, search |
| `imaging_results_screen.dart` | Add tracking, attachments, rejection, clinical indication, filters |
| `prescriptions_screen.dart` | Add frequency/duration/route/specialInstruction, date/status filters |
| `procedures_screen.dart` | Add pre-operative notes, date filter |
| `vitals_screen.dart` | Add vital trend charts (fl_chart), date filter |
| `encounters_screen.dart` | Add search/date filter |
| `patient_api_service.dart` | Add admissions endpoint call, referrals endpoint, filter parameters |
| `patient_models.dart` | Add all missing fields (tracking, attachments, dose details, referral, admission models) |

### Patient App — New Files
| File | Purpose |
|------|---------|
| NEW: `admissions_screen.dart` | Admission history list with bed/ward/dates/status |
| NEW: `referrals_screen.dart` | Patient referral history |
| NEW: `medical_records_screen.dart` | Document/file viewer |
| NEW: `settings_screen.dart` | Notifications, language, privacy |

### Shared New Widgets (Doctor App `core/widgets/`)
| File | Purpose |
|------|---------|
| NEW: `date_range_filter.dart` | Reusable From/To date picker with Apply/Reset |
| NEW: `status_pill_bar.dart` | Horizontal scrollable status filter pills with counts |
| NEW: `delete_reason_dialog.dart` | Delete confirmation with 6 reason categories + notes |
| NEW: `deletion_denied_dialog.dart` | Error dialog for blocked deletions |
| NEW: `consultation_timer.dart` | Timer widget with start/pause/resume + API sync |
| NEW: `treatment_plans_modal.dart` | Treatment plan picker (search + select + apply) |
| NEW: `tab_navigation_buttons.dart` | Previous/Next + Save & Next buttons |
| NEW: `autosave_indicator.dart` | "Saving..."/"Saved ✓" status line |
| NEW: `rich_text_display.dart` | HTML rendering for clinical notes (flutter_html) |
| NEW: `file_viewer.dart` | Document/image viewer for attachments |
| NEW: `queue_action_menu.dart` | Long-press popup: Check-In, Reschedule, Reassign, Cancel, No-Show |
| NEW: `reschedule_dialog.dart` | Date + Time Slot + Custom time + Reason + count badge |
| NEW: `reassign_doctor_dialog.dart` | Doctor dropdown + Reason |
| NEW: `medication_detail_sheet.dart` | Read-only medication detail bottom sheet |

---

## API Endpoints Needed (New/Missing)

### Doctor App
| Endpoint | Purpose | Status |
|----------|---------|--------|
| `GET /api/mobile/doctor/wards` | Ward list with bed occupancy counts | May need new backend route |
| `POST /api/mobile/doctor/encounters/{id}/discharge` | Request discharge | May need new backend route |
| `GET /api/mobile/doctor/encounters/{id}/timer` | Get encounter timer state | May need new backend route |
| `POST /api/mobile/doctor/encounters/{id}/timer/pause` | Pause/resume timer | May need new backend route |
| `GET /api/mobile/doctor/note-templates` | Clinical note templates (search) | May need new backend route |
| `GET /api/mobile/doctor/treatment-plans` | Treatment plan templates (search) | May need new backend route |
| `POST /api/mobile/doctor/encounters/{id}/follow-up` | Schedule follow-up appointment | May need new backend route |
| `POST /api/mobile/doctor/appointments/{id}/reschedule` | Reschedule appointment | May need new backend route |
| `POST /api/mobile/doctor/appointments/{id}/reassign` | Reassign to another doctor | May need new backend route |
| `POST /api/mobile/doctor/appointments/{id}/check-in` | Check in a patient | May need new backend route |
| `POST /api/mobile/doctor/appointments/{id}/cancel` | Cancel appointment | May need new backend route |
| `POST /api/mobile/doctor/appointments/{id}/no-show` | Mark as no-show | May need new backend route |
| `GET /api/mobile/doctor/encounters/{id}/nurse-charts` | Nurse charts data (medication chart, I/O) with date filter | May need new backend route |
| `GET /api/mobile/doctor/diagnosis-favorites` | Saved diagnosis favorites | May need new backend route |
| `POST /api/mobile/doctor/diagnosis-favorites` | Save diagnosis set as favorite | May need new backend route |
| `GET /api/mobile/doctor/encounters/{id}/reports` | Medical reports CRUD | May need new backend route |
| `POST /api/mobile/doctor/encounters/{id}/reports` | Create/update medical report | May need new backend route |
| `GET /api/mobile/doctor/patient/{id}/profile-forms` | Patient profile forms list | May need new backend route |
| `POST /api/mobile/doctor/patient/{id}/profile-forms` | Submit patient profile form | May need new backend route |
| `GET /api/mobile/doctor/queue-history/encounters` | Previous encounters with filters | May need new backend route |
| `GET /api/mobile/doctor/queue-history/admissions` | My/Other admissions with filters | May need new backend route |
| `GET /api/mobile/doctor/queue-history/referrals` | My/All referrals with filters | May need new backend route |
| `GET /api/mobile/doctor/results/labs` | Lab results for approval | May need new backend route |
| `GET /api/mobile/doctor/results/imaging` | Imaging results for approval | May need new backend route |
| `POST /api/mobile/doctor/results/{type}/{id}/approve` | Approve result | May need new backend route |

### Patient App
| Endpoint | Purpose | Status |
|----------|---------|--------|
| `GET /api/mobile/patient/upcoming-appointments` | Future appointments | May need new backend route |
| `GET /api/mobile/patient/referrals` | Patient's referrals | May already exist |
| `GET /api/mobile/patient/medical-records` | Document list | May need new backend route |
| `GET /api/mobile/patient/medical-records/{id}/download` | Download document | May need new backend route |

### Existing Endpoints Already Available ✅
| Endpoint | Used By |
|----------|---------|
| `GET /api/mobile/doctor/queues` | Queue screen |
| `GET /api/mobile/doctor/queue-stats` | Queue stats bar |
| `GET /api/mobile/doctor/encounters/{id}` | All tabs |
| `POST /api/mobile/doctor/encounters/{id}/vitals` | Vitals tab |
| `POST /api/mobile/doctor/encounters/{id}/diagnosis` | Clinical notes |
| `PUT /api/mobile/doctor/encounters/{id}/notes` | Clinical notes auto-save |
| `POST /api/mobile/doctor/encounters/{id}/labs|imaging|prescriptions|procedures` | Clinical orders |
| `DELETE /api/mobile/doctor/encounters/{id}/labs|imaging|prescriptions|procedures/{itemId}` | Delete orders |
| `POST /api/mobile/doctor/encounters/{id}/finalize` | Conclude encounter |
| `GET /api/mobile/doctor/clinics` | Referral form |
| `GET /api/mobile/doctor/doctors` | Referral form |
| `POST /api/mobile/doctor/encounters/{id}/referrals` | Create referral |
| `GET /api/mobile/doctor/encounters/{id}/referrals` | Referral list |
| `GET /api/mobile/doctor/patient/{id}/admissions` | Admission history |
| `GET /api/mobile/doctor/encounters/{id}/recent` | Re-prescribe dropdown |
| `POST /api/mobile/doctor/encounters/{id}/re-prescribe` | Re-prescribe action |
| `GET /api/mobile/patient/encounters` | Patient encounters |
| `GET /api/mobile/patient/vitals` | Patient vitals |
| `GET /api/mobile/patient/lab-results` | Patient lab results |
| `GET /api/mobile/patient/imaging-results` | Patient imaging |
| `GET /api/mobile/patient/prescriptions` | Patient prescriptions |
| `GET /api/mobile/patient/procedures` | Patient procedures |
| `GET /api/mobile/patient/admissions` | Patient admissions (API exists, no screen yet) |

---

## Package Dependencies Needed

| Package | Purpose | Used By |
|---------|---------|--------|
| `flutter_quill` | Rich text editor (replaces CKEditor) | Clinical notes, procedure notes, medical report |
| `flutter_html` | HTML content rendering (read-only web notes) | Notes history, HTML-formatted results |
| `table_calendar` | Calendar view for queue | Queue screen calendar toggle |
| `fl_chart` | Line/bar charts for vitals | Patient app vitals trends |
| `shimmer` | Loading skeleton placeholders | All list screens |
| `share_plus` | Share/export functionality | Referral print, report share |
| `printing` | PDF generation | Medical reports, referral letters |
| `path_provider` | File system access for downloads | Attachment viewer |

---

## Total Gap Count

| Category | Items | Already Done |
|----------|-------|-------------|
| Critical Bug Fixes (Phase 0) | 6 | 6 ✅ |
| Doctor Queue Parity (Phase 1) | 25 | 0 |
| Doctor Consultation Lifecycle (Phase 2) | 12 | 0 |
| Doctor Tab-by-Tab Parity (Phase 3) | ~100 | 0 |
| Medical Report Builder (Phase 3A) | 9 | 0 |
| Doctor Dashboard/Home (Phase 4) | 5 | 0 |
| Patient App Parity (Phase 5) | ~70 | 0 |
| Shared UI/UX (Phase 6) | ~18 | 0 |
| **TOTAL** | **~245** | **6** |

---

*Last updated: 2026-03-25*
