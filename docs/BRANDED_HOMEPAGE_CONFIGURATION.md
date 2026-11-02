# Branded Homepage & Demo Mode Configuration

**Date**: 2026-11-02  
**Git Release Tag**: v2.6.0.63  

## 1. Architectural Overview & Context
CoreHealth v2 introduces a state-of-the-art, high-conversion landing page for route `/` that gracefully balances two core operational modalities:
1. **Hospital Branded Experience (Default / On)**: Automatically adopts the facility's identity (`site_name`), logo (`logo`), primary color (`hos_color`), facility contact channels (`contact_address`, `contact_emails`, `contact_phones`), and direct staff portals (`/login` or `/home` operations cockpit).
2. **Unbranded Product / Demo Experience (Off)**: Strips away facility-specific branding and renders a clean, neutral CoreHealth ecosystem showcase designed specifically for public demonstration instances, investor presentations, and multi-tenant marketing.

---

## 2. Setting & Database Schema
The operational toggle is governed by `application_status.show_branded_homepage`:
- **Database Column**: `show_branded_homepage` (`tinyint(1) NOT NULL DEFAULT 1`) added to the `application_status` table via migration `2026_11_02_133000_add_show_branded_homepage_to_application_status_table.php`.
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
| `show_branded_homepage = 1` (default) | Branded | Displays hospital name, logo, custom color palette, contact info, and staff login. |
| `show_branded_homepage = 0` | Unbranded / Demo | Displays clean CoreHealth landing page with neutral brand identity. |
| `/?branded=0` or `/?demo=1` | Force Demo | Instantly previews unbranded demo mode without altering hospital settings. |
| `/?branded=1` | Force Branded | Instantly previews branded hospital mode. |

---

## 4. UI Architecture & Design Standards
- **Pure CSS Separation**: In adherence to Rule 1 of CoreHealth v2 development standards, all CSS is housed in `public/css/welcome.css` (over 800 lines of design tokens, layout utilities, card grids, micro-interactions, responsive flex/grid layouts, and accessibility focus rings).
- **CSS Variable Injection**: Dynamic theming is provided via CSS variable `--hospital-primary`:
  ```html
  @if($isBranded && !empty($hosColor))
  <style>
    :root {
      --hospital-primary: {{ $hosColor }};
    }
  </style>
  @endif
  ```
- **Co-Branded Combo Component**: When branded, the navigation header displays the hospital logo alongside a two-tier lockup:
  - Hospital Name
  - `Powered by CoreHealth` badge with teal accent
- **Staff Access**: Fast authentication paths for hospital personnel:
  - Guest: `Staff Portal` linking to `route('login')`
  - Authenticated: `Operations Cockpit` linking directly to `url('/home')`

---

## 5. Verification & Test Coverage
Comprehensive PHPUnit feature test suite created in `tests/Feature/Homepage/WelcomePageTest.php`:
- `test_welcome_page_renders_successfully`: Verifies route `/` returns HTTP 200 and loads `welcome.css`.
- `test_welcome_page_is_branded_by_default`: Asserts hospital name, powered by badge, and staff portal buttons render.
- `test_welcome_page_renders_unbranded_demo_when_setting_is_disabled`: Asserts pure Corestream / CoreHealth product presentation is rendered when toggled off.
- `test_welcome_page_respects_query_overrides`: Verifies `?branded=0`, `?demo=1`, and `?branded=1` URL overrides.
- `test_hospital_config_updates_show_branded_homepage_setting`: Verifies PUT `/hospital-config` persists checkbox state.
- **Lint Compliance**: Formatted and strictly verified via `make lint` against PSR-12 rules.
