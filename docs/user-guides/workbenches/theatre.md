# Theatre & Surgery Workbench

<span class="badge-role">ROLE GUARD: SUPERADMIN | ADMIN | SURGERY | DOCTOR | NURSE</span>

The **Theatre & Surgery Workbench** governs the operative pathway: pre-operative assessment, surgical bookings, WHO Surgical Safety Checklists (Sign In, Time Out, Sign Out), intra-operative logs, anaesthesia notes, and surgical consumable billing.

---

## 1. Visual Tour: Surgical Theatre Cockpit

![Theatre & Surgery Workbench View](/images/workbenches/workbench-layout.jpg)

> [!TIP]
> The Surgery Workbench provides an integrated procedural checklist and team attribution console without leaving the operative record.

---

## 2. Real Routes & Role Authorization

- **Primary Route**: `surgery-workbench.index` (`/surgery-workbench`)
- **Authoritative Role Guard**: Defined in `sidebar/_nav-nursing.blade.php` as `@hasanyrole('SUPERADMIN|ADMIN|SURGERY|DOCTOR|NURSE')`.

---

## 3. Operative Pathway

```mermaid
flowchart LR
    A["Surgical Booking (surgery-workbench.queue)"] --> B["Pre-Op Assessment"]
    B --> C["WHO Surgical Safety Checklist"]
    C --> D["Intra-Operative Procedure & Anaesthesia"]
    D --> E["PACU Recovery & Consumables Billing (surgery-workbench.patient-procedures)"]
```

---

## 4. Cross-Module Data & Patient Flows

- **Doctor (`encounters.create`)**: Surgeries booked during clinical consultation appear on the surgical waiting queue.
- **Store (`inventory.store-workbench.index`)**: Surgical packs and anaesthetic agents are requested from Central Stores.
- **Billing (`billing.workbench`)**: Perioperative consumable bills and surgeon fees post automatically to patient folios.

---

## Developer Reference
👉 **[Read the Theatre Developer API Reference](/developer/workbenches/theatre)**
