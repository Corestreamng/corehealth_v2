# Semantic Releases & Milestone Changelogs

CoreHealth v2 follows strict semantic versioning governed by dynamic tag discovery:
```bash
git tag -l "v*" --sort=-v:refname | head -n 1
```

Every release tag is bound directly to documentation updates, PHPUnit verification, and UI screenshot captures.

---

## Recent Milestones

- **[v2.6.0.67 — Clinical Consumables & Multi-Token Search Engine](/releases/v2.6.0.67)**  
  `ClinicalSearchHelper` multi-token query parsing, medical typo resilience (`wond` $\rightarrow$ `wound`), nursing consumable service discovery with `[Product]` and `[Service]` badges, and clinical kit combo bundle inspection.

- **[v2.6.0.66 — Billing Account Cockpit Scaling & Dual ANC/PANC Enrollment](/releases/v2.6.0.66)**  
  Restructured billing account transactions panel above KPI cards in cockpit mode, and segregated antenatal (`ANC-`) and postnatal (`PANC-`) maternity registration prefixes.

- **[v2.6.0.65 — Branded Homepage & Encounter Memory Lifecycle Optimization](/releases/v2.6.0.65)**  
  Modern hospital welcome landing page with dynamic hospital branding and demo instance isolation, plus doctor encounter workbench memory leak elimination.

