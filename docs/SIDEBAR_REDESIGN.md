# CoreHealth v2 — Sidebar Architecture & UX Redesign

**Date**: 2026-10-12  
**Git Release Tag**: v2.6.0.39  
**Status**: Completed & Verified  

---

## 1. Executive Summary

The CoreHealth v2 sidebar navigation was refactored from a monolithic 1,487-line Blade file (`resources/views/admin/partials/sidebar.blade.php`) and ~600 lines of inline layout CSS in `resources/views/admin/layouts/app.blade.php` into a modern, modular, accessible, and responsive navigation subsystem.

All active links, custom role guards, route patterns, test IDs, and clinical domain permissions were preserved character-for-character, while introducing:
- **Three-Tier Responsive Layout**:
  - **Desktop (≥1200px)**: Full 260px sidebar with optional persisted icon-only compact mode (72px) and hover-intent flyout menus.
  - **Tablet (768px–1199px)**: 80px navigation rail with top-centered icons and legible small text underneath, clean section dividers, and tap/hover flyout panels.
  - **Mobile (<768px)**: 280px off-canvas drawer with smooth backdrop dim/blur overlay, swipe-to-close gesture support, body scroll locking, and explicit close button.
- **Client-Side Live Filter Search (`Ctrl+K` / `⌘K`)**: Debounced real-time fuzzy filtering of navigation items with visual clear button and empty state.
- **Section Collapse Memory**: User preference persisted in `localStorage`.
- **Pure CSS Submenu Transitions**: Replaced clunky transitions with high-performance CSS animations.
- **Strict Compliance with Project Standards**: Standalone `public/css/sidebar.css` and `public/js/sidebar.js` with zero inline script or style violations in Blade files.

---

## 2. Architecture & File Structure

### File Mapping

```
resources/views/admin/
├── layouts/app.blade.php                # Master layout (links sidebar.css & sidebar.js)
├── partials/
│   ├── sidebar.blade.php                # Modular shell view (75 lines)
│   └── sidebar/
│       ├── _brand.blade.php             # Logo, app branding, version, mobile close
│       ├── _search.blade.php            # Quick-search filter bar (Ctrl+K trigger)
│       ├── _nav-global.blade.php        # Global: Dashboard, Profile, Messenger, Stock
│       ├── _nav-ess.blade.php           # Employee Self-Service (ESS)
│       ├── _nav-reception.blade.php     # Health Records / Receptionist
│       ├── _nav-billing.blade.php       # Biller / Accounts
│       ├── _nav-pharmacy.blade.php      # Pharmacy
│       ├── _nav-store.blade.php         # Store / Inventory Management
│       ├── _nav-nursing.blade.php       # Nursing, Maternity, Surgery
│       ├── _nav-morgue.blade.php        # Morgue Management
│       ├── _nav-maternity.blade.php     # Maternity Standalone
│       ├── _nav-lab.blade.php           # Medical Laboratory
│       ├── _nav-radiology.blade.php     # Imaging / Radiology
│       ├── _nav-doctor.blade.php        # Doctor / Consultations
│       ├── _nav-hmo.blade.php           # HMO Executive & Tariffs
│       ├── _nav-accounting.blade.php    # Accounting & Finance (16 sub-items)
│       ├── _nav-hr.blade.php            # Human Resources & Payroll
│       ├── _nav-audit.blade.php         # Internal Audit & Ops Audit
│       ├── _nav-admin.blade.php         # Administration & System Setup
│       └── _user-profile.blade.php      # User card, leadership badges, logout form

public/css/
└── sidebar.css                          # Modular styling (CSS variables only)

public/js/
└── sidebar.js                           # Modular front-end interactivity
```

---

## 3. Responsive Strategy

### 3.1 Desktop (≥1200px)
- Width: `260px` (or `72px` minimized).
- Toggle button: `.sidebar-toggle-btn` toggles `.sidebar-icon-only` on `body`.
- Minimization state persisted in `localStorage` key `ch_sidebar_state`.
- Flyout submenus open smoothly on hover with a 200ms delay.

### 3.2 Tablet (768px–1199px)
- Width: `80px` permanent navigation rail.
- **Vertical Stacked Item**: Centered icon (`font-size: 20px`) with **small text underneath** (`font-size: 10.5px`, centered, max 2 lines truncated with ellipsis).
- Section headers render as subtle 1px dividers between domain groups.
- **Portal-Based Floating Flyout (`#ch-sidebar-flyout`)**: 
  - To prevent CSS container overflow clipping (`overflow-y: auto; overflow-x: hidden;` on `.ch-sidebar-nav-scrollable`), submenus are rendered into a body-level floating card portal (`#ch-sidebar-flyout`) at `left: 80px`.
  - Dynamically aligns vertically with the hovered/tapped `.nav-item`'s bounding client rect.
  - Viewport-clamped so submenus never render off-screen.
  - Touch-tap enabled for iPads and Android tablets with outside-click and Escape dismissal.
- Main page body wrapper and top navigation bar offset by `80px`.

### 3.3 Mobile (<768px)
- Width: `280px` (max `85vw`) off-canvas drawer.
- Drawer hidden off-screen (`transform: translateX(-100%)`).
- Tapping `.sidebar-mobile-toggle-btn` slides drawer in (`translateX(0)`).
- Semi-transparent backdrop overlay (`#ch-sidebar-backdrop`).
- Body scroll locked when drawer is active.
- Swipe gesture: Swiping left closes the drawer.
- Close button (`#ch-sidebar-close-btn`) in brand header.

---

## 4. Role Guards & Permission Integrity

All authorization rules were audited and preserved:
- **Global**: Dashboard, Profile, Messenger (`appsettings('chat_enabled')`), Requisitions, My Stock.
- **ESS**: `@auth` + `$sidebarStaff` + `@can('ess.access')`.
- **Receptionist**: `@hasanyrole('SUPERADMIN|ADMIN|RECEPTIONIST')` with dynamic NHMIS monthly report guard (`SUPERADMIN|ADMIN|super-admin` OR `RECEPTIONIST` with `is_unit_head|is_dept_head`).
- **Biller**: `@hasanyrole('SUPERADMIN|ADMIN|ACCOUNTS|BILLER')`.
- **Pharmacy**: `@hasanyrole('SUPERADMIN|ADMIN|PHARMACIST')`.
- **Store**: `@hasanyrole('SUPERADMIN|ADMIN|STORE')` with Store Governance guard (`@can('store-governance.view')` + `@hasrole('STORE')`).
- **Nursing**: `@hasanyrole('SUPERADMIN|ADMIN|NURSE')`.
- **Morgue**: `@hasanyrole('SUPERADMIN|ADMIN|MORGUE')`.
- **Maternity**: `@hasanyrole('SUPERADMIN|ADMIN|MATERNITY')`.
- **Medical Lab**: `@hasanyrole('SUPERADMIN|ADMIN|LAB SCIENTIST')`.
- **Radiology**: `@hasanyrole('SUPERADMIN|ADMIN|RADIOLOGIST')`.
- **Doctor**: `@hasanyrole('SUPERADMIN|ADMIN|DOCTOR')`.
- **HMO**: `@hasanyrole('SUPERADMIN|ADMIN|HMO Executive')`.
- **Accounting**: `@hasanyrole('SUPERADMIN|ADMIN|super-admin|ACCOUNTS')`.
- **Human Resources**: `@hasanyrole('SUPERADMIN|ADMIN|super-admin|HR MANAGER')` with granular `@can` and `@canany` guards for Leave, Payroll, Disciplinary, Staff Tracking, and HR Configuration.
- **Internal Audit**: `hasAnyRole(['SUPERADMIN','ADMIN','super-admin','AUDITOR','AUDIT'])`.
- **Administration**: `@hasanyrole('SUPERADMIN|ADMIN|super-admin')`.

---

## 5. Testing & Verification

- **PHPUnit Feature Tests**: `tests/Feature/UI/SidebarRedesignTest.php`
  - Validates asset existence and content integrity.
  - Verifies presence of all 20 partials.
  - Confirms asset references and cache-busting version params in master layout.
  - Asserts sidebar renders without error for authenticated roles.
  - Validates tablet responsive CSS rules for icons and text labels.
  - Confirms DOM portal flyout generation, dedicated `.ch-sidebar-flyout-menu` classes, and event handlers.
  - Verifies Doctor vs Reception encounter route distinctions (`encounters.index` vs `allPrevEncounters`).
  - Tests Unit Head and Dept Head leadership badges under user profile.
  - Verifies brand `APP_NAME` and tablet group badges in CSS/Blade.
  - Tests search filtering rules, `.ch-sidebar-section-header` selectors, and empty section hiding.
  - **Results**: 10 tests, 70 assertions, 100% passing.
- **Linting & Code Style**:
  - `make lint` / `composer lint` passed with 0 violations across 1,286 files (100% PSR-12 compliant).
  - `node --check public/js/sidebar.js` passed syntax check with 0 errors.

---

## 6. Granular Enhancements

### 6.1 Doctor vs Reception Encounters Navigation
- **Doctor Consultation Dropdown**:
  - `#sidebar-doctor-consultations` toggles sub-links:
    - `#sidebar-doctor-encounters`: `All Encounters` (`route('encounters.index')`).
    - Dedicated flyout extraction dynamically builds `.ch-sidebar-flyout-menu` and `.ch-sidebar-flyout-link` items with clean DOM nodes and isolated styles, ensuring "All Encounters" renders with full height, contrasting typography, and active highlights.
- **Health Records / Receptionist Dropdown**:
  - `#sidebar-receptionist-encounters`: Direct link to `All Encounters` (`route('allPrevEncounters')`).
  - `#sidebar-receptionist-consultations`: Dropdown toggling `#sidebar-receptionist-prev-consultations` (`All Previous Consultations` -> `route('allPrevEncounters')`).

### 6.2 Real-Time Search Filtering & Empty Section Header Hiding
- **Strict Section Filtering**:
  - Section headers use the `.ch-sidebar-section-header` class across all Blade navigation partials.
  - `filterSidebar(term)` calculates matching items per section using `.nextUntil('.ch-sidebar-section-header', '.nav-item')`.
  - Empty sections with zero matching items are force-hidden via `.ch-sidebar-search-hidden` and inline `display: none !important;`, preventing orphaned section headings from displaying without content.
  - Heading-name matching: if a user searches for a department name (e.g. "Doctor", "Accounting", "Pharmacy"), all items under that section are preserved and displayed.
  - Tablet CSS `:not(.ch-sidebar-search-hidden)` guarantees tablet rail flex rules never resurrect hidden section headers.

### 6.3 Dedicated Portal Flyouts for Tablet & Rail Modes
- Submenu contents are isolated inside `#ch-sidebar-flyout` using `.ch-sidebar-flyout-menu`, `.ch-sidebar-flyout-item`, and `.ch-sidebar-flyout-link` to avoid CSS conflicts with legacy StarAdmin `.sub-menu` suppression rules.
- Viewport boundary detection automatically clamps flyout positioning within visible screen dimensions.

### 6.4 Asset Cache-Busting
- In `resources/views/admin/layouts/app.blade.php`, both `sidebar.css` and `sidebar.js` are referenced using the safe `versioned_asset('css/sidebar.css')` and `versioned_asset('js/sidebar.js')` helper function, ensuring browsers immediately load current CSS/JS bundles with fallback resilience.

