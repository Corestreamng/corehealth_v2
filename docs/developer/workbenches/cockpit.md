# Cockpit & Telemetry Developer API Reference

<span class="badge-role">AUTHORITATIVE ROLE GUARD: SUPERADMIN | ADMIN | ALL CLINICAL & ADMINISTRATIVE ROLES</span>

## 1. Controllers & Dynamic Layout Resolution
- **Primary Controller**: `App\Http\Controllers\HomeController@index`.
- **Mode Resolution Chain**:
  ```php
  $mode = $request->query('mode', appsettings('dashboard_mode', 'cockpit'));
  ```
  Returns `admin.dashboards.cockpit` or `admin.dashboards.classic`.

## 2. Frontend Modules & Assets
- **Cockpit Assets**: `public/css/dashboard-cockpit.css`, `public/js/dashboard-cockpit.js`.
- **Classic Assets**: `public/css/dashboard-classic.css`, `public/js/dashboard-classic.js`.
- Both modules validated via `node --check`.

👉 **[Read User Story Guide for Cockpit](/user-guides/workbenches/cockpit)**

