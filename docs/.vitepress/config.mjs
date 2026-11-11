import { defineConfig } from 'vitepress';

export default defineConfig({
  base: '/docs/',
  outDir: '../public/docs',
  title: 'CoreHealth v2 Documentation',
  description: 'Enterprise Hospital Management Information System (HMIS) — User Story Guides & Developer API Reference',
  lang: 'en-US',
  lastUpdated: true,
  cleanUrls: true,

  head: [
    ['link', { rel: 'icon', href: '/images/favicon.ico' }],
    ['meta', { name: 'theme-color', content: '#011b33' }],
    ['script', { src: '/docs/js/mermaid.min.js' }],
  ],

  themeConfig: {
    siteTitle: 'CoreHealth v2',

    nav: [
      { text: 'User Stories & Workbenches', link: '/user-guides/workbenches/reception' },
      { text: 'Clinical Pathway', link: '/user-guides/clinical-pathway' },
      { text: 'Developer API Reference', link: '/developer/architecture' },
      { text: 'Releases & Changelog', link: '/releases/index' },
    ],

    sidebar: {
      '/user-guides/': [
        {
          text: 'Patient Journey & Overview',
          collapsed: false,
          items: [
            { text: 'Longitudinal Clinical Pathway', link: '/user-guides/clinical-pathway' },
            { text: 'Zero-Scroll Cockpit Standards', link: '/user-guides/cockpit-experience' },
          ]
        },
        {
          text: 'Clinical Workbenches',
          collapsed: false,
          items: [
            {
              text: 'Reception & Health Records',
              collapsed: false,
              items: [
                { text: 'Workbench Manual & Overview', link: '/user-guides/workbenches/reception' },
                { text: 'Patient Registration & Identity', link: '/user-guides/workbenches/reception/patient-registration' },
                { text: 'Queue Dispatching & Booking', link: '/user-guides/workbenches/reception/queue-and-booking' },
                { text: 'Walk-in Point-of-Sale & Cart', link: '/user-guides/workbenches/reception/walkin-and-billing' },
                { text: 'HMO Validation & Referrals', link: '/user-guides/workbenches/reception/hmo-and-referrals' },
              ]
            },
            {
              text: 'Doctor Consultation & Encounters',
              collapsed: false,
              items: [
                { text: 'Core Story & Clinical Cockpit', link: '/user-guides/workbenches/doctor' },
                { text: 'My Queues & Triage Filtering', link: '/user-guides/workbenches/doctor/consultation-queue' },
                { text: 'Active Clinical Intake & AI Notes', link: '/user-guides/workbenches/doctor/clinical-encounter' },
                { text: 'Clinical AI Scribe Studio', link: '/user-guides/workbenches/doctor/clinical-ai-scribe' },
                { text: 'Multi-Department Order Sets', link: '/user-guides/workbenches/doctor/clinical-orders' },
                { text: 'Referrals, Admission & Conclude', link: '/user-guides/workbenches/doctor/referrals-and-discharge' },
              ]
            },
            {
              text: 'Nursing & Inpatient Care (MAR)',
              collapsed: false,
              items: [
                { text: 'Core Story & Shift Cockpit', link: '/user-guides/workbenches/nursing' },
                { text: 'Shift Management & Triage Queues', link: '/user-guides/workbenches/nursing/shifts-and-triage' },
                { text: 'Bedside Vitals & MEWS Scoring', link: '/user-guides/workbenches/nursing/vitals-and-triage' },
                { text: 'eMAR & Injection Administration', link: '/user-guides/workbenches/nursing/medication-and-injections' },
                { text: 'Fluid Balance (I/O) & Care Plans', link: '/user-guides/workbenches/nursing/fluid-balance-and-care-plans' },
                { text: 'Ward Occupancy & Discharge Clearance', link: '/user-guides/workbenches/nursing/occupancy-and-discharge' },
              ]
            },
            { text: 'Pharmacy & Dispensary', link: '/user-guides/workbenches/pharmacy' },
            { text: 'Medical Laboratory (LIS)', link: '/user-guides/workbenches/laboratory' },
            { text: 'Imaging & Radiology', link: '/user-guides/workbenches/imaging' },
            { text: 'Theatre & Surgery', link: '/user-guides/workbenches/theatre' },
            {
              text: 'Maternity (ANC, Labor & Child Health)',
              collapsed: false,
              items: [
                { text: 'Core Story & Maternal Continuum', link: '/user-guides/workbenches/maternity' },
                { text: 'ANC Enrollment & Obstetric History', link: '/user-guides/workbenches/maternity/enrollment-and-history' },
                { text: 'Trimester Visits & Clinical Orders', link: '/user-guides/workbenches/maternity/anc-visits-and-orders' },
                { text: 'Labor Ward & Digital Partograph', link: '/user-guides/workbenches/maternity/partograph-and-labor' },
                { text: 'Delivery Record & Newborn APGAR', link: '/user-guides/workbenches/maternity/delivery-and-newborn' },
                { text: 'Postnatal Care & Child Immunization', link: '/user-guides/workbenches/maternity/postnatal-and-immunization' },
              ]
            },
            { text: 'Inpatient Wards & Admissions', link: '/user-guides/workbenches/inpatient' },
            { text: 'Mortuary & Last Office', link: '/user-guides/workbenches/mortuary' },
          ]
        },
        {
          text: 'Revenue & Operations Workbenches',
          collapsed: false,
          items: [
            {
              text: 'Billing & Cashier Workbench',
              collapsed: false,
              items: [
                { text: 'Billing Manual & Cashier Cockpit', link: '/user-guides/workbenches/billing' },
                { text: 'Cashier Shifts & Queue Triage', link: '/user-guides/workbenches/billing/shifts-and-queues' },
                { text: 'Selective Checkout & Split Payments', link: '/user-guides/workbenches/billing/checkout-and-split-payments' },
                { text: 'Patient Accounts & Deposit Wallets', link: '/user-guides/workbenches/billing/patient-accounts-and-deposits' },
                { text: 'Transaction History & Reconciliation', link: '/user-guides/workbenches/billing/transactions-and-reconciliation' },
                { text: 'Walk-in POS & Inpatient Bed Stays', link: '/user-guides/workbenches/billing/walkin-and-admissions' },
              ]
            },
            {
              text: 'Financial Accounting & Fund Governance',
              collapsed: false,
              items: [
                { text: 'Accounting Command Center & Funds Flow', link: '/user-guides/workbenches/accounting' },
                { text: 'Chart of Accounts, JEs & Periods', link: '/user-guides/workbenches/accounting/chart-of-accounts-and-gl' },
                { text: 'Banking, Sweeps & Petty Cash Float', link: '/user-guides/workbenches/accounting/banking-and-cashbook' },
                { text: 'Payables, Store POs, Expenses & Deposits', link: '/user-guides/workbenches/accounting/payables-expenses-and-deposits' },
                { text: 'Fixed Assets, Leases, Cost Centers & Budgets', link: '/user-guides/workbenches/accounting/assets-liabilities-and-budgets' },
                { text: 'Financial Reports, Statements & Audit', link: '/user-guides/workbenches/accounting/financial-reports-and-audit' },
              ]
            },
            { text: 'HMO & Managed Health Insurance', link: '/user-guides/workbenches/hmo' },
            {
              text: 'Store, Inventory & Supply Chain',
              collapsed: false,
              items: [
                { text: 'Store Workbench & Materials Cockpit', link: '/user-guides/workbenches/inventory' },
                { text: 'Procurement, POs & GRN Receiving', link: '/user-guides/workbenches/inventory/procurement-and-reception' },
                { text: 'Requisitions, Approvals & Transfers', link: '/user-guides/workbenches/inventory/requisitions-and-transfers' },
                { text: 'Stock Consumption & Dispensing', link: '/user-guides/workbenches/inventory/utilization-and-dispensing' },
                { text: 'Tally Cards, Expiry & Valuation', link: '/user-guides/workbenches/inventory/tally-card-and-valuation' },
                { text: 'Damages, Returns, Expenses & Governance', link: '/user-guides/workbenches/inventory/damages-returns-and-expenses' },
              ]
            },
            { text: 'Internal Audit & OpsAudit', link: '/user-guides/workbenches/ops-audit' },
            { text: 'Human Resources & ESS', link: '/user-guides/workbenches/hr' },
            { text: 'Operational Cockpit & Dashboards', link: '/user-guides/workbenches/cockpit' },
          ]
        },
        {
          text: 'Platform Integrations',
          collapsed: true,
          items: [
            { text: 'WeCare247 Telehealth Sync', link: '/user-guides/workbenches/wecare' },
            { text: 'CoreHMS SuperAdmin Central Node', link: '/user-guides/workbenches/corehms' },
          ]
        }
      ],

      '/developer/': [
        {
          text: 'Architecture & Standards',
          collapsed: false,
          items: [
            { text: 'Modular Workbench Pattern', link: '/developer/architecture' },
            { text: 'Coding Standards & PSR-12', link: '/developer/coding-standards' },
            { text: 'Database & Observer Ledger', link: '/developer/database-and-observers' },
            { text: 'Automated Testing Suites', link: '/developer/testing-guide' },
            { text: 'Screenshot Guide for Agents', link: '/developer/screenshot-guide' },
          ]
        },
        {
          text: 'Workbench Submodule APIs',
          collapsed: false,
          items: [
            {
              text: 'Reception & Records API',
              collapsed: false,
              items: [
                { text: 'Architecture & Controller Reference', link: '/developer/workbenches/reception' },
                { text: 'Paystack-Style API Contracts', link: '/developer/workbenches/reception/api-contracts' },
                { text: 'Database Schemas & Observer Models', link: '/developer/workbenches/reception/database-and-models' },
              ]
            },
            {
              text: 'Doctor Consultation API',
              collapsed: false,
              items: [
                { text: 'Architecture & Route Blueprint', link: '/developer/workbenches/doctor' },
                { text: 'Clinical AI Scribe & Catalog API', link: '/developer/workbenches/clinical-ai-scribe' },
                { text: 'Encounter API & Save Contracts', link: '/developer/workbenches/doctor/api-contracts' },
                { text: 'Database Models, Queues & Observers', link: '/developer/workbenches/doctor/database-and-models' },
              ]
            },
            {
              text: 'Nursing & MAR API',
              collapsed: false,
              items: [
                { text: 'Architecture & Shift Machine', link: '/developer/workbenches/nursing' },
                { text: 'Nursing API Contracts', link: '/developer/workbenches/nursing/api-contracts' },
                { text: 'Database Models & Observers', link: '/developer/workbenches/nursing/database-and-models' },
              ]
            },
            { text: 'Pharmacy & Dispensing API', link: '/developer/workbenches/pharmacy' },
            { text: 'Laboratory (LIS) API', link: '/developer/workbenches/laboratory' },
            { text: 'Imaging & Radiology API', link: '/developer/workbenches/imaging' },
            { text: 'Theatre & Surgery API', link: '/developer/workbenches/theatre' },
            {
              text: 'Maternity (ANC/PANC) API',
              collapsed: false,
              items: [
                { text: 'Architecture & Dual Prefix System', link: '/developer/workbenches/maternity' },
                { text: 'Maternity API Contracts', link: '/developer/workbenches/maternity/api-contracts' },
                { text: 'Database Models & Partograph Schema', link: '/developer/workbenches/maternity/database-and-models' },
              ]
            },
            { text: 'Inpatient & Wards API', link: '/developer/workbenches/inpatient' },
            {
              text: 'Billing & Payment Ledger API',
              collapsed: false,
              items: [
                { text: 'Architecture & PaymentObserver', link: '/developer/workbenches/billing' },
                { text: 'API Contracts & Route Specs', link: '/developer/workbenches/billing/api-contracts' },
                { text: 'Database Schemas & Models', link: '/developer/workbenches/billing/database-and-models' },
              ]
            },
            {
              text: 'Financial Accounting & GL API',
              collapsed: false,
              items: [
                { text: 'Double-Entry Core & Architecture', link: '/developer/workbenches/accounting' },
                { text: 'Accounting API Contracts', link: '/developer/workbenches/accounting/api-contracts' },
                { text: 'Database Schemas & Observers', link: '/developer/workbenches/accounting/database-and-models' },
              ]
            },
            { text: 'HMO Tariffs & Claims API', link: '/developer/workbenches/hmo' },
            {
              text: 'Store & Inventory Management API',
              collapsed: false,
              items: [
                { text: 'Architecture & Multi-Store Tenancy', link: '/developer/workbenches/inventory' },
                { text: 'Store API Contracts & Schemas', link: '/developer/workbenches/inventory/api-contracts' },
                { text: 'Database Schemas & Observer Hooks', link: '/developer/workbenches/inventory/database-and-models' },
              ]
            },
            { text: 'OpsAudit Verification API', link: '/developer/workbenches/ops-audit' },
            { text: 'Mortuary Records API', link: '/developer/workbenches/mortuary' },
            { text: 'HRMS & Locum Passes API', link: '/developer/workbenches/hr' },
            { text: 'Cockpit & Telemetry API', link: '/developer/workbenches/cockpit' },
          ]
        },
        {
          text: 'External Sync Contracts',
          collapsed: true,
          items: [
            { text: 'WeCare247 API Contracts', link: '/developer/workbenches/wecare' },
            { text: 'CoreHMS SuperAdmin Contracts', link: '/developer/workbenches/corehms' },
          ]
        },
        {
          text: 'Interoperability & Data Standards',
          collapsed: false,
          items: [
            { text: 'Multi-Standard 5-Level Architecture', link: '/developer/interoperability/multi-standard-5level-architecture' },
            { text: 'REST API Endpoints Master Registry', link: '/developer/interoperability/rest-api-crud-endpoints' },
            { text: 'HL7 FHIR R4 Endpoints Reference', link: '/developer/interoperability/fhir-r4-endpoints-reference' },
            { text: 'openEHR REST Endpoints Reference', link: '/developer/interoperability/openehr-rest-endpoints-reference' },
            { text: 'OpenEMR & C-CDA XML Reference', link: '/developer/interoperability/openemr-ccda-endpoints-reference' },
            { text: 'NDPA Consent Endpoints Reference', link: '/developer/interoperability/ndpa-consent-endpoints-reference' },
            { text: 'HL7 FHIR R4 Architecture Specs', link: '/developer/interoperability/fhir-r4-specifications' },
            { text: 'openEHR Archetype Specifications', link: '/developer/interoperability/openehr-archetype-specifications' },
            { text: 'OpenEMR Compatibility Specs', link: '/developer/interoperability/openemr-compatibility-specifications' },
            { text: 'NDPA 2023 & Health Privacy Standards', link: '/developer/interoperability/ndpa-compliance-specifications' },
          ]
        }
      ],

      '/releases/': [
        {
          text: 'Release Milestones',
          items: [
            { text: 'Release History Index', link: '/releases/index' },
            { text: 'v2.6.0.69 (LIS Auto-Increment & Search)', link: '/releases/v2.6.0.69' },
            { text: 'v2.6.0.68 (Clinical AI Scribe Studio)', link: '/releases/v2.6.0.68' },
            { text: 'v2.6.0.67 (Clinical Search Engine)', link: '/releases/v2.6.0.67' },
            { text: 'v2.6.0.66 (Billing & Dual ANC/PANC)', link: '/releases/v2.6.0.66' },
            { text: 'v2.6.0.65 (Branded Homepage & Memory)', link: '/releases/v2.6.0.65' },
          ]
        }
      ]
    },

    search: {
      provider: 'local',
      options: {
        detailedView: true
      }
    },

    socialLinks: [
      { icon: 'github', link: 'https://github.com/mrapollos/corehealth_v2' }
    ],

    footer: {
      message: 'Enterprise Healthcare Management Information System (HMIS)',
      copyright: 'Copyright © 2026 CoreHealth Ecosystem. All rights reserved.'
    }
  }
});

