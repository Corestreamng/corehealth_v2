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
                { text: 'Multi-Department Order Sets', link: '/user-guides/workbenches/doctor/clinical-orders' },
                { text: 'Referrals, Admission & Conclude', link: '/user-guides/workbenches/doctor/referrals-and-discharge' },
              ]
            },
            { text: 'Nursing & Inpatient Care (MAR)', link: '/user-guides/workbenches/nursing' },
            { text: 'Pharmacy & Dispensary', link: '/user-guides/workbenches/pharmacy' },
            { text: 'Medical Laboratory (LIS)', link: '/user-guides/workbenches/laboratory' },
            { text: 'Imaging & Radiology', link: '/user-guides/workbenches/imaging' },
            { text: 'Theatre & Surgery', link: '/user-guides/workbenches/theatre' },
            { text: 'Maternity (ANC & Postnatal)', link: '/user-guides/workbenches/maternity' },
            { text: 'Inpatient Wards & Admissions', link: '/user-guides/workbenches/inpatient' },
            { text: 'Mortuary & Last Office', link: '/user-guides/workbenches/mortuary' },
          ]
        },
        {
          text: 'Revenue & Operations Workbenches',
          collapsed: false,
          items: [
            { text: 'Billing & Cashier Workbench', link: '/user-guides/workbenches/billing' },
            { text: 'HMO & Managed Health Insurance', link: '/user-guides/workbenches/hmo' },
            { text: 'Store & Inventory Governance', link: '/user-guides/workbenches/inventory' },
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
                { text: 'Encounter API & Save Contracts', link: '/developer/workbenches/doctor/api-contracts' },
                { text: 'Database Models, Queues & Observers', link: '/developer/workbenches/doctor/database-and-models' },
              ]
            },
            { text: 'Nursing & MAR API', link: '/developer/workbenches/nursing' },
            { text: 'Pharmacy & Dispensing API', link: '/developer/workbenches/pharmacy' },
            { text: 'Laboratory (LIS) API', link: '/developer/workbenches/laboratory' },
            { text: 'Imaging & Radiology API', link: '/developer/workbenches/imaging' },
            { text: 'Theatre & Surgery API', link: '/developer/workbenches/theatre' },
            { text: 'Maternity (ANC/PANC) API', link: '/developer/workbenches/maternity' },
            { text: 'Inpatient & Wards API', link: '/developer/workbenches/inpatient' },
            { text: 'Billing & Payment Ledger API', link: '/developer/workbenches/billing' },
            { text: 'HMO Tariffs & Claims API', link: '/developer/workbenches/hmo' },
            { text: 'Store & Stock Transfer API', link: '/developer/workbenches/inventory' },
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
        }
      ],

      '/releases/': [
        {
          text: 'Release Milestones',
          items: [
            { text: 'Release History Index', link: '/releases/index' },
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

