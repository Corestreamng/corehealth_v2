# Screenshot Capture & Visual Governance Guide for Agents

All documentation contributors (human developers and autonomous AI agents) must strictly adhere to this visual governance standard. Visual screenshots ground the story guides in reality, allowing hospital staff and executive evaluators to visually verify clinical workflows.

---

## 1. Visual Quota Invariant: At Least 1 Screenshot Per Operational Phase

> [!IMPORTANT]
> **Strict Visual Quota & Mandatory Updates**:
> Every user guide page and subpage under `docs/user-guides/` MUST contain:
> - **At least 1 authentic screenshot per operational phase or section**: If a page has 5 main operational phases (e.g. Queue Intake, Triage Audit, SOAP Notes, Diagnostic Orders, Conclude), it requires **at least 5 authentic screenshots**.
> - **Mermaid Diagrams**: Used wherever relevant to clarify clinical decision logic, data paths, and downstream handovers, but **diagrams DO NOT replace authentic UI screenshots**.
> 
> **Core Story & Subpage Standard**:
> - The main workbench page (`docs/user-guides/workbenches/<module>.md`) presents the complete end-to-end operational journey with screenshots anchoring every phase.
> - Subpages provide deep-dive procedures and MUST include focused screenshots for their specific panels, forms, and workflows.
> - Every distinct operational section or subpage workflow MUST include a relevant screenshot where visual context aids the user.
> 
> **Agent & Developer Invariant**: Whenever an autonomous agent or developer adds, refactors, or modifies a workbench view, form modal, action ribbon, or clinical queue, they **MUST update the corresponding documentation files under `docs/` and capture or refresh real screenshots in `docs/public/images/workbenches/`**. Never leave visual assets out-of-sync with code changes. Pages with missing screenshots fail documentation CI gating.

---

## 2. Automated Screenshot Automation Suite

CoreHealth v2 includes a first-class screenshot automation suite powered by `puppeteer-core` driving local Google Chrome:

```bash
# Capture fresh, authentic screenshots via npm
npm run capture:screenshots

# Or via Makefile target
make screenshots

# Direct script invocation
node tools/capture_workbench_screenshots.js
```

### Script Architecture (`tools/capture_workbench_screenshots.js`)
- Authenticates against the local application using pre-seeded administrator or clinician credentials (`sysadmin@mail.com` / `123456`).
- Standardizes viewport geometry to **Full HD ($1920 \times 1080$) standard** at 92% JPEG quality.
- Automates navigation across workbenches, triggers modals (`#patientFormModal`, `#concludeEncounterModal`), switches tabs (`switch_tab(null, id)`), and controls collapsible demographic ribbons.
- Writes optimized JPEG images directly to `docs/public/images/workbenches/`.

---

## 3. Visual Inspection & Automated Retake Invariant

> [!CAUTION]
> **Mandatory Visual Review Before Embedding**:
> Taking a screenshot is not enough; agents and developers **MUST inspect every captured image using `read_file`** prior to embedding it in documentation:
> 1. **Check for Header Occlusion**: Ensure collapsible banners (such as the Sticky Consultation Header `.sch-details-panel`) are collapsed to their compact 45px bar when capturing workspace content below (e.g. Clinical Notes, Order Sets, Referrals), preventing them from obscuring forms or tables.
> 2. **Verify Scroll Position**: Confirm the page or workspace pane is scrolled to the top (`window.scrollTo(0, 0)`), ensuring headings, action buttons, and form legends are visible.
> 3. **Verify Modal State**: Confirm the target modal is fully open and focused (`.modal.show` with backdrop).
> 4. **Execute Retakes Immediately**: If an image is obscured, clipped, or showing the wrong tab/modal, adjust the script coordinates/delays and retake the screenshot until it is pristine.

---

## 4. Storage Hierarchy & Naming Convention

All screenshots must reside strictly within:
```
docs/public/images/
├── workbenches/          # Clinical & administrative workbench views
├── modals/               # Rapid modals (patient intake, alerts, triage)
└── workflows/            # Exported process flows
```

### Naming Convention:
`<module>-<feature-or-state>.<png|jpg>`

Examples:
- `reception-patient-intake-card.jpg`
- `billing-workbench-cockpit.jpg`
- `nursing-mar-administration-grid.png`
- `maternity-dual-prefix-anc-panc.png`

---

## 4. Privacy, HIPAA & Seed Data Invariant

- **Zero Real PHI**: Never take screenshots containing live patient data.
- **Seeded Mock Patients Only**: Always capture screens populated with standard fixtures:
  - Patient: *John Doe*, *Mary Abah Emeka*, *Fatima Bello*.
  - Staff: *Dr. Ibrahim Abubakar*, *Nurse Chioma Okon*.
  - Hospital Name: Configured via `appsettings()->site_name` (e.g. *General Hospital Example*).

---

## 5. Markdown Embedding Standard

Always reference public image assets with project-relative root URLs (do not use hardcoded filesystem paths):
```markdown
![Reception Workbench Cockpit & Queue View](/images/workbenches/dashboard-overview.jpg)
```
Follow each screenshot with a brief descriptive callout highlighting what the user should observe (e.g., button locations, badge indicators, or zero-scroll viewport boundary).

