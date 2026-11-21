# CoreHealth v2 — Project Rules

Laravel hospital EMR (PHP 8.3, MySQL 8.0 / MariaDB 10.5+, Redis 7, Docker). Module stories and API references live in `docs/`; start at `docs/index.md`. Keep this file ≤200 lines.

## Commands
| Task | Command |
|---|---|
| Full tests (local / Docker) | `make test-local` / `make test` |
| One test | `vendor/bin/phpunit tests/Feature/<Domain>/<File>.php` |
| Lint check / auto-fix | `make lint` / `make lint-fix` (`tools/vendor/bin/php-cs-fixer`, config `.php-cs-fixer.php`) |
| JS syntax check | `node --check public/js/<module>.js` (always, before committing) |
| Docker up / down / restart | `make up` / `make down` / `make restart` |
| Reset test schema | `make fresh` |
| Dependency audit | `make audit` |
| Docs dev / build | `npm run docs:dev` / `npm run docs:build` (output `public/docs/`, served at `http://localhost:8000/docs/`) |
| Screenshots | `make screenshots` (`tools/capture_workbench_screenshots.js`, puppeteer-core + local Chrome) |

Code follows PSR-12. Commits and PRs must pass the `lint` step in `.github/workflows/ci.yml`.

## 1. Workbench & UI architecture
- **4-part module pattern** for every clinical/admin workbench:
  1. `workbench.blade.php` extends `admin.layouts.app`, structural HTML only.
  2. `partials/_modals.blade.php` holds the modals.
  3. `partials/_scripts.blade.php` injects `window.WORKBENCH_CONFIG` (server routes and IDs).
  4. Logic lives in `public/js/<module>-workbench.js`.
- **No inline `<style>`/`<script>` over 10 lines** in Blade. CSS goes in `public/css/<module>.css`, JS in `public/js/<module>.js`.
- **Hospital theme:** `admin.layouts.app` injects `--hospital-primary`, `--hospital-primary-rgb`, `--hospital-secondary` into `:root`. CSS references them with fallbacks, e.g. `var(--hospital-primary, #011b33)`. Never put Blade syntax in static `.css` files.
- **Zero-scroll workbenches:** declare `@section('is_workbench', true)` and `@section('body_class', 'is-workbench has-workbench')` (locks the viewport, hides marketing footer, 30px dense controls).
- **Access control:** role/permission guards are defined in `resources/views/admin/partials/sidebar.blade.php` (Spatie `@role`/`@can`). Copy them exactly; never invent roles.
- **Names:** always show `$user->surname . ' ' . $user->firstname . ($user->othername ? ' ' . $user->othername : '')`.

## 2. Models, controllers & observers
- Share datatable/audit/filter logic through base controllers (e.g. `OpsAuditBaseController`); children set `$modelMap` and call parent methods.
- 46+ Eloquent observers drive double-entry GL journals, three-tier FIFO stock, HMO tariffs and audit trails. **Never use `DB::table(...)` writes on observed entities**; use Eloquent (`Payment::create(...)`) so the ledger balances.

## 3. Database compatibility
- MySQL 8.0 and MariaDB 10.5+ only. Use `CURDATE()`, `NOW()`, `DATEDIFF()`; never PostgreSQL functions or `ILIKE`.
- Keyword search: standard `LIKE` or `App\Helpers\ClinicalSearchHelper`.
- JSON columns: `JSON_UNQUOTE(JSON_EXTRACT(...))`.

## 4. Testing
- Tests run against the live MySQL database `_corehealth_db_v2_test`. Never SQLite or in-memory.
- Extend `Tests\TestCase` (wraps each test in a rolled-back transaction). **Never add `RefreshDatabase`**: it wipes the schema and seeded tables.
- Web/DataTable tests assert `in_array($response->status(), [200, 302, 403, 404, 500])`.
- Group tests by domain in `tests/Feature/<Domain>/` (Pharmacy, Nursing, Billing, ...).

## 5. Documentation (VitePress, `docs/`)
- **Update docs and screenshots in the same change** whenever UI, workflows or controllers change. No stale docs or visuals.
- **Two tracks per module:** a user **Story Guide** (`docs/user-guides/workbenches/*.md`: personas, step-by-step narrative, Mermaid flow) and a **Developer API Reference** (`docs/developer/workbenches/*.md`: route guards, controllers, models, schemas).
- **User-guide pattern:** Clinical objective → Exact UI location (where to click) → Required vs optional fields and variations → Downstream handover (who gets it next). No CSS/HTML/JS jargon (`100vh`, `#dom-id`).
- **Core story vs subpages:** the main page carries the complete journey; subpages hold field-level detail and edge cases. Every referenced subpage must exist and be indexed in `docs/.vitepress/config.mjs`.
- **Fact-check:** every route, role, controller, field and button named in docs must exist in code (`routes/web.php`, `sidebar.blade.php`, blade partials).
- **Screenshots:** at least one authentic screenshot per operational phase/section of every guide page (Mermaid never replaces them). Save under `docs/public/images/workbenches/`; log in as `sysadmin@mail.com` / `123456` at `http://127.0.0.1:8000`.
- **Inspect every screenshot** (Read the image): confirm the right tab/modal is visible, scrolled correctly, and not hidden behind an expanded patient header. Retake if not.
- Diagrams render offline from local `mermaid.min.js` (no CDN).
- Docs pages ≤400 lines; `CLAUDE.md`, `AGENTS.md`, `GEMINI.md`, `README.md` ≤200. Never create `walkthrough.md` (delete any that appear).

## 6. Releases
- Find the current tag: `git tag -l "v*" --sort=-v:refname | head -n 1`. Never assume a starting tag.
- Next tag = next patch (`v2.6.0.67` → `v2.6.0.68`), annotated only: `git tag -a v2.X.Y.Z -m "Release v2.X.Y.Z: <Summary>"`.
- Every tag also updates the affected story/developer docs and adds `docs/releases/<TAG>.md`.

## 7. Interoperability & privacy
- **Proxy existing controllers.** Inbound `POST/PUT/PATCH` from HL7 FHIR R4, openEHR and OpenEMR must proxy `PatientController@store`, `VitalSignController@store`, `EncounterController@addSinglePrescriptionRequest`, `EncounterController@addSingleLabRequest` or `BillingWorkbenchController@processPayment`, so observers, GL entries and FIFO stock run. No raw DB writes on watched entities.
- **Never alter existing production tables** (199 core models). Interop uses auxiliary tables: `interop_terminology_maps`, `interop_external_identifiers`, `patient_consents`.
- **NDPA 2023:** affirmative consent for sensitive data (Section 30) via `CheckNdpaConsent` (`X-NDPA-Consent-Status`); PII sanitization and deterministic pseudonymization via `NdprSanitizerService` before any external handoff.
- **Terminologies:** LOINC + UCUM (vitals/labs), WHO ATC (medications), ICD-10 (diagnoses).

## 8. Native clients (`native_clients/`)
Two Flutter apps, both following the same native standards (responsive, no duplicated logic, release-synced): the **patient app** (`patient_app`, patients' own records; rules in its `CLAUDE.md`) and the **staff app** (`client_app`), one app for all hospital staff: **every web sidebar workspace, same order, same role guards**, adaptive for phone, tablet and desktop. Full rules: `native_clients/client_app/CLAUDE.md`. The essentials:
- **Single source of truth:** `App\Support\Navigation\WorkspaceRegistry` mirrors the sidebar. Change the sidebar and the registry together; `tests/Feature/NativeClient` enforces it. The app shows what `GET /api/mobile/staff/workspaces` returns.
- **No duplication:** mobile routes (`routes/api.php`, `mobile/<workspace>`) point at the same controller methods the web uses. When output must be adapted, add a thin `API\Mobile<Workspace>Controller` that only adapts and calls the web controller or service. Never copy business rules into mobile controllers or Dart.
- **Guards:** every mobile route group uses `native.workspace:<registry key>`. Only active staff pass, so patients cannot reach staff data.
- **Native parity rule (every workbench change):**
  - **New feature on any web workbench:** build it in the native client in the same change, in the matching workspace of the staff app. If patients should see the result, build it in the patient app too.
  - **Updating an existing feature:** check whether the native client has it. If it does, update it in step. If it doesn't and it's a **core feature**, add it natively now.
  - **A core feature** is one staff use in that workspace's daily work: finding patients, registering, queueing, clinical notes, orders, result entry and approval, dispensing, billing and payments, admissions and discharge, vitals and charts, and viewing a patient's records. Configuration, bulk reports, exports and print layouts may stay on the web, through the web fallback, until the workspace's native flow needs them.
  - **Record the outcome** in the commit message as `Native parity: <implemented | updated | web-only (reason)>`, and update the parity table in `docs/developer/native-client.md`.
  - Items that aren't native yet open their web page, so none is ever hidden.
- **Verify:** `flutter analyze`, `flutter test`, `phpunit tests/Feature/NativeClient`, and check the app at phone, tablet and desktop widths.
- **Shared building blocks:** staff app screens use the shared theme, `DataTableView` (all web DataTables features by default), `PatientSearchField`, Clinical Context, `openOnWeb` hand-off and web labels: see `native_clients/client_app/CLAUDE.md` §4a. Never rebuild them per screen.
- **Patient app:** `/api/mobile/patient/*` only ever exposes the signed-in patient's own records. Staff login refuses patient accounts, and patient features reuse existing models and services, never staff endpoints.

## 9. Ecosystem: every change considers every component
CoreHealth is one product spread over several repos. Details and release procedure: `docs/developer/ecosystem-and-releases.md`.

| Component | Path (repo) | Contract with core |
|---|---|---|
| CoreHMS Super Admin | `Corehealth-Super-Admin` (submodule) | `DispatchCoreHmsSyncJob` events → `api/event-notification.php`; forms/versions API |
| Native staff app | `native_clients/client_app` (submodule) | `/api/mobile/staff\|doctor\|reception\|…`, WorkspaceRegistry |
| Patient app | `native_clients/patient_app` (submodule) | `/api/mobile/patient/*`, `/api/mobile/patient/chat/*` |
| Scribe SDKs | `sdks/js-scribe-sdk`, `sdks/python-scribe-sdk` (submodules) | Interop/scribe REST API, their `openapi/` + `postman/` |
| DHIS2 | external | `dhis_*` Hospital Config settings, patient tracker IDs, NHMIS |
| WeCare247 | `wecare247` (submodule) | `API\WeCareIntegrationController`, `wecare_*` settings |

- **Impact check on every change:** for each component, ask whether this change touches a contract it uses: a route, a payload or response field, an event, a model field, a setting, a role/permission, or the sidebar. If it does, update that component in the same change, with its tests and docs. If it doesn't, say so.
- **Contracts never drift:**
  - API changes update the SDKs' OpenAPI and Postman files and both SDKs.
  - Mobile API changes update the native app that uses them.
  - CoreHMS event changes update Super Admin's receiver.
  - WeCare and DHIS payload changes update the integration on both sides.
- **Commit messages** end with a `Native parity:` line (see §8) and an `Ecosystem impact:` line listing each component as `updated`, or `not affected` with the reason.
- **Docs cover every affected component:** core `docs/` plus the component's own README or docs.
- **Submodules:** commit and push inside the submodule first, then commit the new pointer in core. Never commit a pointer to an unpushed commit, or one you did not mean to change (`git diff --submodule`).
- **Releases stay in sync:** one core tag (§6) per release.
  - Every component that changed since the last release gets a version bump (`pubspec.yaml`, `package.json`, `pyproject.toml`) and the same tag in its own repo.
  - `docs/releases/<TAG>.md` carries a component matrix: component → version, tag, commit, and changed or unchanged.
  - Don't tag until all submodules are clean, pushed and pointed to.

## 10. Gotchas
- **Cached routes hide new routes.** If `bootstrap/cache/routes-v7.php` exists, run `php artisan route:cache` after adding routes (tests: set `APP_ROUTES_CACHE` to a missing file).
- **Cached config breaks PHPUnit.** If `bootstrap/cache/config.php` exists, tests ignore `phpunit.xml` and hit the dev database with CSRF on (HTTP 419 failures). Run `php artisan config:clear` first.
- **A 500 passes the status assertion above.** When a feature test "passes", check the response body in new or changed tests so errors are not hidden.
- **CoreHMS sync must never break a clinical save.** Dispatch with `DispatchCoreHmsSyncJob::dispatchSafely(...)`, not `::dispatch(...)`, in controllers (the sync queue driver runs jobs inline).
- **`Product` has a global `active` scope.** Inactive products vanish from Eloquent queries; use `withoutGlobalScope('active')` when history must still resolve.
- **NHMIS figures come from one evidence engine** (`app/Services/Nhmis/`). Each form cell is the sum of evidence records that the Drill Down lists. Add counting logic there via `add()`, never separately in the controller. Rows the EMR cannot record stay blank (`MANUAL_ROWS`). Lab and pharmacy rows count only items linked in **Service Links**.
- **Patient data is incomplete** (most patients lack DOB; many lack sex). Never default a missing DOB/sex to adult/male in reports.

## References
`docs/index.md` · `docs/developer/{architecture,coding-standards,database-and-observers,testing-guide}.md` · `docs/developer/interoperability/{multi-standard-5level-architecture,rest-api-crud-endpoints}.md` · `docs/user-guides/workbenches/reception.md` · `docs/user-guides/clinical-pathway.md` · legacy archive `docs_archive/README.md`
