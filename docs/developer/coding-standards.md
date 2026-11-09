# Developer Coding Standards & Quality Assurance

All contributions to CoreHealth v2 must adhere strictly to the following standards across backend PHP, frontend JavaScript, and code formatting.

---

## 1. PSR-12 Code Style & Isolated PHP CS Fixer

Code formatting is strictly governed by the PSR-12 standard via `friendsofphp/php-cs-fixer`, configured in `.php-cs-fixer.php`.

### Isolated Tooling Environment
To prevent dependency conflicts with Laravel core packages, PHP CS Fixer is maintained in an isolated `tools/` environment (`tools/vendor/bin/php-cs-fixer`).

### Commands:
```bash
# Check code style (dry-run check)
composer lint
# or: make lint

# Automatically format all code to PSR-12 standard
composer lint-fix
# or: make lint-fix
```

### Automated CI Pipeline Gating
All pull requests and commits are gated by `.github/workflows/ci.yml`. Commits that do not pass `composer lint` are rejected automatically.

---

## 2. Standalone JavaScript & CSS Verification

Before committing any standalone frontend script or stylesheet:
1. **Node Syntax Verification**: Every `.js` file must pass `node --check`:
   ```bash
   node --check public/js/reception-workbench.js
   node --check public/js/billing-core.js
   
   # Or run bulk check script
   npm run check:js
   ```
2. **Dynamic CSS Variables**: All CSS rules must reference `--hospital-primary` and related CSS custom properties with default fallbacks.
3. **No Unparsed Blade Syntax in Static Assets**: Static CSS and JS files in `public/` must never contain raw Blade syntax (<span v-pre><code>{{ ... }}</code></span>).

---

## 3. Name Formatting Invariant

Whenever patient, staff, or doctor names are displayed across views, datatables, or print slips, always include `othername` where available:
```php
$user->surname . ' ' . $user->firstname . ($user->othername ? ' ' . $user->othername : '');
```

---

## 4. Mandatory Documentation & Screenshot Synchronization

Autonomous agents and human developers must keep documentation in lockstep with code:
1. **Two-Track Coverage**: Whenever modifying, creating, or refactoring a clinical workbench or module, update both:
   - **Customer/User Story Guide** (`docs/user-guides/workbenches/*.md`): Real-world personas, step-by-step workflow narratives, and diagrams.
   - **Developer API Reference** (`docs/developer/workbenches/*.md`): Route contracts, controller actions, database tables, and observers.
2. **Mandatory Screenshot Updates**: Whenever adding, restyling, or reorganizing workbench views, action ribbons, or form modals:
   - Capture or update relevant UI screenshots in `docs/public/images/workbenches/`.
   - Embed them in the corresponding story guides with descriptive callouts following [Screenshot Guide](/developer/screenshot-guide).
   - Strictly maintain the visual quota invariant ($1 \le \text{visuals} \le 5$ per page).
3. **Core Story vs Subpages**: The main workbench page (`docs/user-guides/workbenches/<module>.md`) presents the end-to-end clinical journey, while subpages provide deep granular details, field-by-field instructions, and edge cases.
4. **File Length Standards**: Core rule files (`AGENTS.md`, `GEMINI.md`, `README.md`) strictly remain concise ($\le 200$ lines). Documentation engine pages under `docs/` can go up to 400 lines to ensure exhaustive, comprehensive clinical and technical coverage.
5. **No `walkthrough.md` Files**: Never generate temporary or loose walkthrough markdown files; all documentation belongs inside the VitePress engine under `docs/`.
