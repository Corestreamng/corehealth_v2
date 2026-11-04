# Branded Homepage & Demo Mode Configuration

**Date**: 2026-11-04  
**Git Release Tag**: v2.6.0.65  

## 1. Architectural Overview & Context
CoreHealth v2 features a high-density, production-grade landing page for route `/` that seamlessly shifts between two tailored operational modalities:
1. **Hospital Branded Clinical Portal (Default / On)**:
   - Adopts the hospital's corporate identity (`site_name`), logo (`logo`), primary color (`hos_color`), facility contact channels (`contact_address`, `contact_emails`, `contact_phones`), and direct staff portals (`/login` or `/home` operations cockpit).
   - Replaces SaaS product marketing, developer API promos, sales pricing, and demo booking with clinical facility content:
     - 24/7 Clinical Emergency Status Announcement Bar.
     - Clinical Services & Specialty Departments Bento Grid (Outpatient & Specialist Consultations, 24/7 Emergency & Trauma, Inpatient & ICU, Maternity & Pediatrics, Diagnostic Laboratory, Pharmacy & Dispensary, Surgical Theatres, Confidential Digital Records).
     - Digital Patient Care & Telehealth Split Section (Real-time EHR integration, WeCare247 tele-consultations, digital lab results delivery).
     - Patient Care Standards & Confidentiality (Qualified specialists, NDPR/HIPAA aligned data protection, continuous infection prevention).
     - Patient & Visitor FAQ (Visiting hours, lab results access, HMO insurance plans, specialist bookings, emergency admissions).
     - Dedicated Staff & Reception CTA Banner linking directly to `@auth /home @else /login @endauth` and emergency telephone lines.
2. **Unbranded Product Platform Showcase (Off / Demo)**:
   - Displays the CoreHealth healthcare operating system product showcase.
   - Core capabilities mega-menu (Clinical, Operations, Data & Care, Callout Card for Voice Dictation).
   - Solution tabs (Hospitals, Clinics, Government & Ministries, Health Networks).
   - Developer & interoperability section (DHIS2, NHMIS, HL7/FHIR, REST APIs, Webhooks).
   - Interactive hospital dashboard simulation preview frame.
   - Lead capture and demo request workflows (`#demo`, `#signup`).

---

## 2. Setting & Database Schema
The operational toggle is governed by `application_status.show_branded_homepage`:
- **Database Column**: `show_branded_homepage` (`tinyint(1) NOT NULL DEFAULT 1`) in `application_status`.
- **Model Definition**: Fillable and cast to `boolean` in `App\Models\ApplicationStatu`.
- **Helper Integration**: `appsettings('show_branded_homepage')` defaults to `1` (true).
- **Administration UI**: Located in Hospital Configuration under the **Branding** tab (`resources/views/admin/hospital-config/index.blade.php`), allowing hospital administrators to toggle the branded landing page with one click.
- **Controller Logic**: `HospitalConfigController::update()` parses and persists `show_branded_homepage`.

---

## 3. Dynamic Resolution Chain & Query Overrides
Route `/` in `routes/web.php` resolves the landing page mode dynamically:
```php
Route::get('/', function (\Illuminate\Http\Request $request) {
    $showBrandedHomepage = (bool) (appsettings('show_branded_homepage') ?? true);

    if ($request->has('branded')) {
        $showBrandedHomepage = $request->boolean('branded');
    } elseif ($request->has('demo')) {
        $showBrandedHomepage = !$request->boolean('demo');
    }

    return view('welcome', compact('showBrandedHomepage'));
});
```

### Modes & Query Parameters:
| Setting / Parameter | Active Mode | Description |
|---|---|---|
| `show_branded_homepage = 1` (default) | Branded | Displays hospital name, logo, custom color palette, contact info, clinical services, and staff login. |
| `show_branded_homepage = 0` | Unbranded / Demo | Displays full CoreHealth product platform landing page with neutral brand identity. |
| `/?branded=0` or `/?demo=1` | Force Demo | Instantly previews unbranded product demo mode without altering hospital settings. |
| `/?branded=1` | Force Branded | Instantly previews branded hospital clinical portal. |

---

## 4. UI Architecture & Design Standards
- **Pure CSS Separation**: In strict adherence to Rule 1 of CoreHealth v2 development standards, all CSS is housed in `public/css/welcome.css` (830+ lines containing modern layout tokens, responsive typography with Inter and Plus Jakarta Sans, interactive bento grids, and mobile navigation).
- **Dynamic CSS Variable Theming**:
  ```html
  @if($isBranded && !empty($hosColor))
  <style>
    :root {
      --hospital-primary: {{ $hosColor }};
    }
  </style>
  @endif
  ```
  `public/css/welcome.css` resolves theme tokens dynamically:
  ```css
  :root {
    --blue: var(--hospital-primary, #2540D9);
    --blue-700: color-mix(in srgb, var(--blue) 80%, #000);
    --blue-50: color-mix(in srgb, var(--blue) 8%, #fff);
    --blue-100: color-mix(in srgb, var(--blue) 16%, #fff);
    --red: var(--hospital-secondary, #8E1B1B);
  }
  ```
- **Modular Standalone JavaScript**: All client-side interactivity is encapsulated in `public/js/welcome.js`:
  - Accessible mobile drawer toggle (`#burger`, `#drawer`) with auto-closing on link selection.
  - Keyboard accessible (`Escape`) and touch-friendly mega-menu dropdowns (`.nav-item`).
  - Solutions tab switcher (`.tab`, `.panel`).
  - Verified with `node --check public/js/welcome.js`.

---

## 5. Verification & Test Coverage
Comprehensive PHPUnit feature test suite in `tests/Feature/Homepage/WelcomePageTest.php`:
- `test_welcome_page_renders_successfully`: Verifies route `/` returns HTTP 200/302 and loads `welcome.css`.
- `test_welcome_page_is_branded_by_default`: Asserts hospital name, `Powered by CoreHealth`, clinical services, and staff portal buttons render.
- `test_welcome_page_renders_unbranded_demo_when_setting_is_disabled`: Asserts CoreHealth product presentation is rendered when toggled off with `Start free` and `Book a demo`.
- `test_welcome_page_respects_query_overrides`: Verifies `?branded=0`, `?demo=1`, and `?branded=1` URL overrides.
- `test_hospital_config_updates_show_branded_homepage_setting`: Verifies PUT `/hospital-config` persists checkbox state.
- **Lint Compliance**: Formatted and strictly verified via `composer lint` / `make lint` against PSR-12 rules.
