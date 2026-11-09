# Maternity, Obstetrics & Child Wellness Developer Architecture

<span class="badge-role">AUTHORITATIVE ROLE GUARD: SUPERADMIN | ADMIN | super-admin | MATERNITY</span>

The **Maternity, Obstetrics & Child Wellness Subsystem** manages the continuous journey of maternal, fetal, and neonatal healthcare. It governs antenatal care (ANC) booking, trimester monitoring, labor ward triage, the real-time **Digital Partograph** engine, delivery summaries, newborn biometrics and APGAR evaluations, postnatal recovery visits, and the National Programme on Immunization (EPI).

---

## 1. Architectural Overview & Component Structure

```mermaid
flowchart TD
    subgraph Client Layer
        A["maternity/workbench.blade.php<br/>15 High-Density Clinical Tabs"]
        B["public/js/maternity-core.js<br/>Enrollment & Context Switching"]
        C["public/js/maternity-anc.js<br/>ANC Visits & Partograph Engine"]
        D["public/js/maternity-delivery.js<br/>Delivery, Baby & Immunization"]
    end

    subgraph Controller Layer
        E["MaternityWorkbenchController<br/>(routes/maternity_workbench.php)"]
        F["ClinicalOrdersTrait<br/>(Nurse/Midwife Order Set Auto-Save)"]
    end

    subgraph Domain Engine Layer
        G["Naegele Gestational Calculator<br/>(EDD & Gestational Weeks Engine)"]
        H["Digital Partograph Engine<br/>(WHO Alert/Action Line Curve Plotter)"]
        I["APGAR Vital Scoring Engine<br/>(1, 5, 10 min Neonatal Scoring)"]
        J["EPI Immunization Engine<br/>(Mother TT & Baby NPI Schedules)"]
    end

    subgraph Relational Persistence
        K[("maternity_enrollments")]
        L[("anc_visits & investigations")]
        M[("maternity_partographs")]
        N[("delivery_records")]
        O[("maternity_babies")]
        P[("postnatal_visits")]
        Q[("patient_immunization_schedules")]
    end

    A --> B
    A --> C
    A --> D

    B --> E
    C --> E
    D --> E
    E --> F

    E --> G
    E --> H
    E --> I
    E --> J

    G --> K
    E --> L
    H --> M
    E --> N
    I --> O
    E --> P
    J --> Q
```

---

## 2. The Dual-Prefix Patient Isolation Architecture

To ensure strict longitudinal separation between pregnancy care and postpartum infant health, CoreHealth v2 implements a dual-prefix file numbering convention:

```mermaid
flowchart LR
    A["Patient Registration<br/>(Reception Workbench)"] --> B{"Encounter Type"}
    
    B -->|"Pregnancy Care"| C["ANC- Series File Number<br/>(e.g., ANC-2026-0042)"]
    B -->|"Postpartum Infant"| D["PANC- Series File Number<br/>(e.g., PANC-2026-0042)"]
    
    C --> E["MaternityEnrollment<br/>has_delivery = 0"]
    E -->|"Delivery Recorded"| F["MaternityBaby Registered"]
    F -->|"Creates Baby Patient"| D
```

- **`ANC-` Prefix**: Assigned to expectant mothers upon ANC booking. Governs obstetric history, gestational weeks, trimester visits, laboratory screens, and intrapartum labor monitoring.
- **`PANC-` Prefix**: Assigned to the newborn upon registration in `#registerBabyModal`. Governs neonatal biometrics, APGAR scores, growth charts, and routine EPI immunization doses.
- **Bidirectional Context Switcher**: The client navigation bar (`#context-switch-container`) enables providers to switch between maternal records and infant charts with a single click without losing the active session state.

---

## 3. Real-Time Digital Partograph Engine

The digital partograph implements the World Health Organization (WHO) active phase labor monitoring standards:
- **Alert Line**: A straight line plotted from $4\text{ cm}$ of cervical dilation upwards at a constant rate of $1\text{ cm per hour}$ over 6 hours to full dilation ($10\text{ cm}$).
- **Action Line**: Parallel to the Alert Line and drawn $4\text{ hours}$ to the right.
- **Algorithmic Evaluation**: If the plotted dilation curve crosses to the right of the Alert Line, the system flags **Prolonged Active Phase**. If the curve crosses the Action Line, a high-priority banner recommends immediate obstetric review for operative delivery or labor augmentation.

---

## 4. Subsystem References

👉 **[Read Maternity API Contracts & JSON Schemas](/developer/workbenches/maternity/api-contracts)**  
👉 **[Read Maternity Database Schema & Models Reference](/developer/workbenches/maternity/database-and-models)**  
👉 **[Read Maternity User Story Guide](/user-guides/workbenches/maternity)**
