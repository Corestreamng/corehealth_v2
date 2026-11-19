# Ecosystem & Release Sync

CoreHealth is one product spread across several repositories. **Every change considers every component**, keeps their contracts and docs in step, and **every release keeps them in sync**. The short rules are in the root `CLAUDE.md` §9; this page holds the detail.

## 1. Components and contracts
| Component | Path / repo | Contract with core | Core side | Docs |
|---|---|---|---|---|
| **CoreHealth core** | this repo | — | — | `docs/` |
| **CoreHMS Super Admin** | `Corehealth-Super-Admin` (submodule) | Sync events (patient, consultation, admission, procedure, dispense, staff, lab) posted to `api/event-notification.php`; facility forms and versions (`api/get-forms.php`, ...) | `App\Jobs\DispatchCoreHmsSyncJob` (`dispatchSafely`), `app/Services/CoreHms/Events/*`, `CoreHmsBackfillCommand`; settings `COREHMS_SUPERADMIN_*`, `goonline` | [CoreHMS](/developer/workbenches/corehms) |
| **Native staff app** | `native_clients/client_app` (submodule) | `/api/mobile/staff\|doctor\|reception\|…/*`; workspace list | `WorkspaceRegistry`, `EnsureNativeWorkspace`, `routes/api.php` | [Native client](/developer/native-client) |
| **Patient app** | `native_clients/patient_app` (submodule) | `/api/mobile/patient/*`, `/api/mobile/patient/chat/*`, patient login | `API\MobilePatientController`, `API\MobileChatController`, `MobileAuthController@patientLogin` | [Native client](/developer/native-client#_5-patient-app) |
| **Scribe SDKs (JS, Python)** | `sdks/js-scribe-sdk`, `sdks/python-scribe-sdk` (submodules) | Interop and scribe REST API; each SDK ships `openapi/` and `postman/` | `routes/api.php` interop routes, interop controllers | [Interop REST registry](/developer/interoperability/rest-api-crud-endpoints) |
| **DHIS2** | external national server | Tracker program, stages and org unit; NHMIS monthly return | `dhis_*` settings (`HospitalConfigController`), `patients.dhis_consult_tracker_id/enrollment_id`, NHMIS workbench | [NHMIS](/user-guides/workbenches/nhmis) |
| **WeCare247** | `wecare247` (submodule) | WeCare integration API | `API\WeCareIntegrationController`, `wecare_*` settings | [WeCare](/developer/workbenches/wecare) |

## 2. Impact check (every change)
Before you finish, go through the table. For each component:
1. **Does the change touch something it relies on?** That means a route, a request or response field, an event payload, a model or column, a setting, a role or permission, a sidebar item, or a status code.
2. **If yes, update it in the same change:**
   - **SDKs:** OpenAPI, Postman and the client methods in both.
   - **Native apps:** their API service, models and screens.
   - **Super Admin:** the event receiver and forms.
   - **WeCare/DHIS:** the payload on both sides.
   - In every case, add or adjust tests and update that component's docs.
3. **Write the result in the commit message:**
   ```
   Ecosystem impact: core updated; client_app updated; patient_app not affected (patient API unchanged);
   super-admin not affected; sdks not affected; dhis2 not affected; wecare247 not affected
   ```

## 3. Submodule workflow
1. Commit inside the submodule and push it to its remote.
2. In core, stage the new pointer (`git add <path>`), check it with `git diff --cached --submodule`, then commit.
3. Never commit a pointer to a commit that isn't pushed, and never include a pointer change you didn't make on purpose.

## 4. Release procedure (keeps versions in sync)
1. **Find the next core tag:** `git tag -l "v*" --sort=-v:refname | head -n 1`, then the next patch.
2. **For every component that changed since the previous tag:**
   - Bump its version: `pubspec.yaml` `version` (apps), `package.json` (JS SDK, WeCare), `pyproject.toml` / `setup.py` (Python SDK).
   - Commit, push, and tag that repo with **the same core tag**: `git tag -a vX -m "Release vX: <summary>"`, then `git push --tags`.
3. **Check that every submodule is clean** (`git submodule foreach git status --short` is empty) and that every pointer in core is the pushed, tagged commit.
4. **Write `docs/releases/<TAG>.md`** with the component matrix:

   | Component | Version | Tag | Commit | Changed |
   |---|---|---|---|---|
   | core | 2.6.0.N | vX | abc1234 | yes |
   | client_app | 1.1.0+2 | vX | def5678 | yes |
   | patient_app | 1.0.0+1 | — | ac2a5df | no |
   | … | | | | |

5. **Tag core** (annotated) and push the commit and the tag.

## 5. Current versions (before the first synced release)
| Component | Version | Latest tag |
|---|---|---|
| core | — | v2.6.0.72 |
| client_app | 1.0.0+1 | none |
| patient_app | 1.0.0+1 | none |
| js-scribe-sdk | 1.0.0 | none |
| python-scribe-sdk | 1.0.0 | none |
| wecare247 | 0.1.0 | v1.0 (+70 commits) |
| Corehealth-Super-Admin | — | none |

The next release brings every changed component onto the core tag.
