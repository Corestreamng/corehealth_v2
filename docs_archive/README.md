# CoreHealth v2 Legacy Documentation Archive

This directory (`docs_archive/`) preserves historical engineering artifacts, gap analyses, implementation plans, and sprint audits from 2024 through early November 2026.

> [!NOTE]
> Active, human-readable user guides, developer API references, and release notes are now maintained in the new documentation engine under `docs/`.

---

## Archive Index by Clinical & Domain Area

### 1. Clinical Workbenches & Care Delivery
- `CLINICAL_CONSUMABLES_AND_KITS_SEARCH_ENHANCEMENT.md` — Multi-token search for nursing consumables and clinical kit bundles (v2.6.0.67)
- `ENCOUNTER_WORKBENCH_MEMORY_OPTIMIZATION.md` — Memory leak resolution, Select2 cleanup, and rapid procedure lookup (v2.6.0.65)
- `CLINICAL_NOTE_POLISHING_MULTI_MODALITY_ENGINE.md` — AI note polishing and clinical summary alignment (v2.6.0.64)
- `LLM_NOTE_POLISHING_AND_CLINICAL_SUMMARY_ALIGNMENT.md` — Multi-modality clinical note summarization
- `CLINICAL_ORDERS_PLAN.md` — Clinical orders design and order sets
- `LOCUM_ACCESS_AND_CONSULTATION_LOGBOOK.md` — Visiting clinician access, locum passes, and consultation log book (v2.6.0.57)
- `INVESTIGATION_RESULT_SIGNATURES_AND_APPROVAL_GOVERNANCE.md` — Dual attestation and result approval governance (v2.6.0.58)
- `PATIENT_REGISTRATION_MODAL_MIGRATION.md` — Rapid registration modal and dual ANC/PANC prefix selection (v2.6.0.66)
- `MATERNITY_MODULE_PLAN.md` & `MATERNITY_ENHANCEMENT_PLAN.md` — Antenatal, partograph, and postnatal tracking
- `NURSING_WORKBENCH_GAP_ANALYSIS_FINAL.md`, `NURSING_WORKBENCH_GAP_ANALYSIS.md`, `NURSING_WORKBENCH_GAP_ANALYSIS_v2.md` — Nursing workbench evolution
- `NURSE_DRUG_SOURCE_REVAMP_PLAN.md` & `NURSING_BILLING_ENHANCEMENT_PLAN.md` — Ward drug source and service billing
- `LAB_IMAGING_PERFORM_INVESTIGATION_PLAN.md`, `LAB_IMAGING_RESULT_APPROVAL_PLAN.md`, `LAB_TEMPLATE_STRUCTURE.md`, `WYSIWYG_AND_RESULT_EDIT_IMPLEMENTATION.md` — LIS and imaging result workflows
- `PROCEDURE_MODULE_DESIGN_PLAN.md` — Surgical checklists, theatre queue, and procedure billing
- `INJECTION_IMMUNIZATION_UPDATE.md` — Immunization schedules and administration logging
- `APPOINTMENT_ENHANCEMENT_PLAN.md` — Outpatient booking, clinic calendars, and triage queues
- `MOBILE_ENCOUNTERS_PLAN.md` & `MOBILE_WEB_PARITY_PLAN.md` — Mobile-responsive encounter design

### 2. Billing, Cashier, HMO & Revenue Cycle
- `BILLING_PAYMENT_ENHANCEMENTS.md` — Split payments, free-form items, voiding, and cockpit account form stacking (v2.6.0.66)
- `BILLING_WORKBENCH_IMPLEMENTATION.md` & `BILLER_PLAN.md` — Unified billing workbench and payment queues
- `DEPARTMENT_REVENUE_ATTRIBUTION.md` — Dual-tier department revenue attribution and GL tagging (v2.6.0.50)
- `BATCH_COGS_AND_REVENUE_ATTRIBUTION_IMPLEMENTATION.md` — Cost of goods sold (COGS) and inventory revenue sync
- `MY_TRANSACTIONS_IMPLEMENTATION.md` & `MY_TRANSACTIONS_TEST_GUIDE.md` — Cashier transaction feed and receipt reprinting
- `hmo_plan.md` & `HMO_WORKBENCH_ENHANCEMENT_PLAN.md` — HMO claims, tariffs, and remittance workflows
- `PRODUCT_PRICE_CHANGE_WORKFLOW.md`, `PRODUCT_ENHANCEMENT_PLAN.md`, `PRODUCT_CREATION_TARIFF_STUDY.md`, `TARIFF_PREVIEW_PLAN.md` — Price list and tariff administration

### 3. Pharmacy & Inventory Governance
- `BATCH_STOCK_GAP_ANALYSIS.md` & `BATCH_STOCK_IMPLEMENTATION_STATUS.md` — FIFO batch tracking across pharmacy and sub-stores
- `STORE_GOVERNANCE_AND_CONTEXTUAL_WORKBENCH_PLAN.md` — Store roles, lane policies, and transfer approvals
- `INVENTORY_IMPLEMENTATION_PLAN.md` & `DELIVERY_GUARDS_SETUP.md` — Purchase orders, goods receipt, and delivery guards
- `PHARMACY_WORKBENCH_IMPLEMENTATION_PLAN.md` & `PHARMACY_WORKBENCH_IMPLEMENTATION_STATUS.md` — Prescription dispensing queues
- `PHARMACY_QUICK_ACTIONS_PLAN.md`, `PHARMACY_SUPERMARKET_CHECKOUT_PLAN.md`, `PHARMACY_MOBILE_LOOK_PLAN.md` — Returns, damages, and retail sales
- `VOID_VS_DISPOSAL_GUIDE.md` — Waste, expiry write-off, and disposal protocols

### 4. Accounting, Financial Control & Auditing
- `ACCOUNTING_COMPREHENSIVE_GAP_ANALYSIS_2026.md`, `ACCOUNTING_GAP_ANALYSIS.md`, `ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md`, `Accounting _plan.md` — General Ledger and double-entry engine
- `ACCOUNTING_UI_IMPLEMENTATION_PLAN.md` & `ACCOUNTING_IMPLEMENTATION_CHECKLIST.md` — Financial reporting UI (Trial Balance, P&L, Balance Sheet)
- `BANK_CASH_STATEMENT_IMPLEMENTATION.md` — Bank statement parsing (CSV/OFX) and automated reconciliation
- `CAPEX_COLUMN_MAPPING.md` & `CAPEX_SYNC_COMPLETED.md` — Fixed assets, depreciation schedules, and capital expenditures
- `OPS_AUDIT_DUTY_SHIFT_RECONCILIATION.md` — Duty shift executive handover certificates and cashier till balancing (v2.6.0.48)
- `OPS_AUDIT_STEPPED_STORYBOARD_WORKFLOW.md` — 4-step progressive audit ribbon and custom time windows (v2.6.0.54)
- `OPS_AUDIT_REQUISITION_AND_PARTIAL_FULFILLMENT_ACCOUNTABILITY.md` — Store requisition status parity and interim fulfiller attribution (v2.6.0.55)

### 5. Administration, HRMS & Dashboards
- `STAFF_DEACTIVATION_AND_STATUS_GOVERNANCE.md` — Employment status governance, role deactivation, and smart locum pass revocation (v2.6.0.61)
- `HRMS_IMPLEMENTATION_PLAN.md` & `ATTENDANCE_CLOCKING_MODULE_PLAN.md` — Employee master, leaves, payroll, and biometric clocking
- `DASHBOARD_DUAL_MODE_CONFIGURATION.md` & `OPERATIONAL_COCKPIT_DASHBOARD_REDESIGN.md` — Dual dashboard (cockpit vs classic) architecture (v2.6.0.59)
- `DASHBOARD_ENHANCEMENT_PLAN.md` — Historical executive widgets
- `SIDEBAR_REDESIGN.md` — Modular sidebar architecture based on Spatie roles
- `BRANDED_HOMEPAGE_CONFIGURATION.md` — Modern dynamic hospital landing page with demo isolation (v2.6.0.65)
- `ENV_TO_APPSETTINGS_MIGRATION.md` & `DATABASE_FIELD_REFERENCE.md` — Hospital settings repository and schema reference

### 6. Architecture, Ecosystem & Standards
- `STANDALONE_ARCHITECTURE_AND_INDEPENDENCE_STANDARDS.md` — Independent repository architecture and decoupling
- `UNIVERSAL_WORKBENCH_FRAMEWORK_ARCHITECTURE.md` & `WORKBENCH_COCKPIT_SCALING_ARCHITECTURE.md` — Zero-scroll viewport locking and cockpit scaling standards (v2.6.0.45)
- `COREHMS_SYNCHRONIZATION_AND_MONITORING_IMPLEMENTATION.md` — Real-time event sync and deduplication with CoreHMS SuperAdmin (v2.6.0.51)
- `WECARE247_CLINICAL_STORY_AND_EHR_SYNCHRONIZATION.md` — WeCare247 telemedicine bidirectional synchronization (v2.6.0.53)
- `NHMIS_OUTCOME_CLASSIFICATION_AND_AUTO_SENSING_IMPLEMENTATION.md` — NHMIS monthly reporting auto-sensing (v2.6.0.49)
- `api-versioning.md`, `input-validation.md`, `TESTING_CHECKLIST.md` — Early API and testing guidelines

