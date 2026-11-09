---
layout: home

hero:
  name: "CoreHealth v2"
  text: "Enterprise Hospital Management Information System"
  tagline: "High-density clinical workbenches, zero-scroll operational cockpits, and double-entry financial governance."
  image:
    src: /images/workbenches/cockpit-overview.jpg
    alt: CoreHealth v2 Operational Cockpit
  actions:
    - theme: brand
      text: Explore Reception Story
      link: /user-guides/workbenches/reception
    - theme: alt
      text: Doctor Consultation Story
      link: /user-guides/workbenches/doctor
    - theme: alt
      text: Developer API & Architecture
      link: /developer/architecture

features:
  - icon: 🏥
    title: 18 Specialized Workbenches
    details: Modular, zero-scroll operational cockpits tailored for Doctors, Nurses, Pharmacists, Lab Scientists, Surgeons, Cashiers, and Administrators.
    link: /user-guides/workbenches/reception

  - icon: 🔄
    title: Longitudinal Patient Pathway
    details: Seamless clinical continuity from Reception intake, triage, consultation, diagnostic investigation, to dispensary, ward admission, and billing.
    link: /user-guides/clinical-pathway

  - icon: ⚖️
    title: Double-Entry Financial Governance
    details: 46+ automated Eloquent observers enforcing balancing debit/credit journal entries on every clinical payment, inventory dispense, and tariff claim.
    link: /developer/database-and-observers

  - icon: ⚡
    title: High-Density Cockpit Scaling
    details: Sub-second keyboard-driven interfaces, 30px compact controls, and responsive micro-scrollbars optimized for 1366×768 hospital workstations.
    link: /user-guides/cockpit-experience

  - icon: 🌐
    title: Ecosystem Synchronization
    details: Real-time non-blocking event streaming to CoreHMS SuperAdmin and bidirectional EHR integration with the WeCare247 telemedicine portal.
    link: /user-guides/workbenches/corehms

  - icon: 🛡️
    title: Strict Role-Based Access
    details: Character-for-character Spatie authorization guards mirrored directly from the hospital navigation framework.
    link: /developer/coding-standards
---

## Documentation Tracks

<div class="custom-card-grid">
  <div class="custom-card">
    <span class="badge-role">FOR CLINICIANS & STAFF</span>
    <h3>User Story Guides</h3>
    <p>Real-world hospital workflows, step-by-step patient intake narratives, visual tours, and cross-department handoffs written for human users.</p>
    <a href="/docs/user-guides/workbenches/reception">Read Story Guides &rarr;</a>
  </div>

  <div class="custom-card">
    <span class="badge-role">FOR ENGINEERS & ARCHITECTS</span>
    <h3>Developer & API Reference</h3>
    <p>Comprehensive controller contracts, database schemas, observer lifecycles, REST APIs, and PHPUnit testing standards.</p>
    <a href="/docs/developer/architecture">Explore API Reference &rarr;</a>
  </div>

  <div class="custom-card">
    <span class="badge-role">AUDIT & CHANGELOG</span>
    <h3>Semantic Release Notes</h3>
    <p>Detailed verification records, architectural changes, and milestone updates bound to semantic git release tags.</p>
    <a href="/docs/releases/index">Browse Releases &rarr;</a>
  </div>
</div>
