# Semantic Releases & Milestone Changelogs

CoreHealth v2 follows strict semantic versioning governed by dynamic tag discovery:
```bash
git tag -l "v*" --sort=-v:refname | head -n 1
```

Every release tag is bound directly to documentation updates, PHPUnit verification, and UI screenshot captures.

---

## Recent Milestones

- **[v2.6.0.70 — Multi-Standard Health Interoperability (HL7 FHIR R4, openEHR, OpenEMR, C-CDA) & NDPA Statutory Privacy Engine](/releases/v2.6.0.70)**  
  Enterprise multi-standard health data interoperability and statutory privacy framework: HL7 FHIR R4 (Levels 1–4 REST API), openEHR ITS-REST (COMPOSITION & AQL Archetype Query Engine), OpenEMR table-parity REST endpoints, and HL7 C-CDA Continuity of Care Document XML generator. Fully governed by the Controller Proxy Pattern (guaranteeing all 46+ Eloquent observers, double-entry General Ledger balancing, and FIFO inventory sync), Zero Core Schema Mutation Invariant, NDPA 2023 statutory consent gatekeeper (Section 30), pediatric minor guardian validation (Section 31), OWASP BOLA compartment isolation, and high-precision clinical PII sanitization.

- **[v2.6.0.69 — LIS Specimen Auto-Increment, Collision Guards & Lab Number Search Engine](/releases/v2.6.0.69)**  
  Overhaul of the Laboratory Workbench accession numbering engine: collision-free sequential auto-increment with whitespace normalization, server-side cross-patient duplicate rejection (422), patient lookup by lab number, sample collect button lifecycle state restoration, and prominent accession visibility across queues, cards, and datatables.

- **[v2.6.0.68 — Clinical AI Scribe Studio ("Full AI Mode")](/releases/v2.6.0.68)**  
  Ambient clinical documentation cockpit, 60 FPS hospital-themed waveform, multi-dialect matrix (`en-NG`, `pcm`, `ha`, `yo`, `ig`, `fr`, `es`, `ar`, etc.), dynamic clinical taxonomy switcher (Nigerian Clerking default, SOAP, POMR, DAR, SBAR, SOAPIE), catalog-grounded orders deck, and session/local draft persistence.

- **[v2.6.0.67 — Clinical Consumables & Multi-Token Search Engine](/releases/v2.6.0.67)**  
  `ClinicalSearchHelper` multi-token query parsing, medical typo resilience (`wond` $\rightarrow$ `wound`), nursing consumable service discovery with `[Product]` and `[Service]` badges, and clinical kit combo bundle inspection.

- **[v2.6.0.66 — Billing Account Cockpit Scaling & Dual ANC/PANC Enrollment](/releases/v2.6.0.66)**  
  Restructured billing account transactions panel above KPI cards in cockpit mode, and segregated antenatal (`ANC-`) and postnatal (`PANC-`) maternity registration prefixes.

- **[v2.6.0.65 — Branded Homepage & Encounter Memory Lifecycle Optimization](/releases/v2.6.0.65)**  
  Modern hospital welcome landing page with dynamic hospital branding and demo instance isolation, plus doctor encounter workbench memory leak elimination.

