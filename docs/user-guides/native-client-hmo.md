# CoreHealth Staff App: HMO Executive

<span class="badge-role">HMO EXECUTIVE · ADMIN · SUPERADMIN — the same people as the web sidebar</span>

The **HMO** section is where an HMO executive decides, before a service is delivered, what an insurer will pay for. Everything works one-handed on a phone; tablets and computers add columns. Back to the [Staff App guide](/user-guides/native-client).

## HMO Workbench
**HMO → HMO Workbench**.

![HMO workbench on a phone](/images/workbenches/native-phone-hmo-workbench.png)

- **Money at a glance:** what is waiting for approval, approved today, rejected today and approved this month.
- **Queues:** tap a tile: **Pending** (needs your decision), **Awaiting code** (approved, the insurer's authorisation code is still to come), **Approved**, **Rejected**, **Express** (covered automatically), **Claims** (billed) and **All**. Only requests where the HMO pays something are listed; an item the HMO pays nothing for has nothing to validate.
- **Presets and filters:** Overdue (waiting four hours or more), High value, Approved today, Rejected today; filter by HMO, coverage, product/service/procedure, reception check and dates; search by patient, file or HMO number, item or request number. The list is paged by the server.
- **Cards** show the patient and their HMO number, the item, who asked, how long it has waited (green, amber, red), what the HMO and the patient each pay, and the status.
- **Several at once:** tick requests and use the bar: **Approve** or **Reject** in Pending, **Enter codes** (one shared code, or one each) in Awaiting code.

### One request
Tap a card. You see the item and prices, the patient and their cover, and the validation trail.

![An HMO request on a phone](/images/workbenches/native-phone-hmo-request.png)

| Status | What you can do |
|---|---|
| Pending | **Approve** (authorisation code and notes optional), **Reject** (choose a reason), **Edit tariff** |
| Awaiting code | **Enter auth code**, **Reject**, **Edit tariff** |
| Approved | **Reverse approval** (with a reason) while the service has not been delivered |
| Rejected | **Re-approve** while the service has not been delivered |

The server refuses a reversal once a lab result is entered, an imaging report is entered or a drug is dispensed, and says why.

**Edit tariff** changes the coverage mode and what the HMO and the patient each pay for this request, and can apply it to every HMO in the same scheme.

### A patient
**Find a patient** (or **Patient** on a request) opens their HMO cockpit: cover, wallet, and every request in six tabs (Pending, Awaiting code, Approved, Express, Rejected, Past).

![A patient's HMO requests](/images/workbenches/native-phone-hmo-patient.png)

- **Validate pending** approves or rejects the patient's whole pending group at once: pick the requests, then approve with no code, one shared code or a code each, or reject with a reason.
- **Clinical context** (vitals, notes, medications, allergies), **HMO history** (approved claims and totals) and **Change HMO** (a new HMO and number; pending requests are priced again).
- A request with no HMO share shows as **No HMO share** in Past: it needs no validation.

## HMO Reports
**HMO → Reports**: **Claims** (filter by status, submission, payment, HMO and dates; select claims to **Mark submitted** with a batch reference or **Link to a remittance**; download Excel or PDF), **Outstanding** (what each HMO still owes, aged), **Monthly**, **Utilisation** (top services and products, categories), **Auth codes** and **Remittances**.

![HMO reports on a phone](/images/workbenches/native-phone-hmo-reports.png)

**Remittances** are payments received from an HMO: **New remittance** (HMO, bank, amount, date, reference, period), open one to see the claims it settled, edit it or delete it (its claims become unpaid again).

## HMO Patients
**HMO → Patients** lists patients covered by an HMO, with an HMO filter; tap one for their cockpit.

## For admins and HMO heads
These items show only to administrators and heads of the HMO unit, as on the web.

- **HMOs:** create, edit (name, description, discount, scheme), activate or deactivate.
- **HMO Tariffs:** what each HMO and the patient pay for each product and service. Filter by HMO, product or service and coverage; **New tariff**; open one to edit or delete it; **Normalise scheme** (one rule for every item of every HMO in a scheme); **Import** an Excel or CSV (you see how many tariffs would change before anything is saved); **Export**. **Overrides** replace the tariff price for a group (all products, a category, one item) for an HMO or a whole scheme, by percentage or fixed amount.

![HMO tariffs on a desktop](/images/workbenches/native-desktop-hmo-tariffs.png)

- **Billing Organisations:** corporate accounts billed instead of the patient: create, edit, delete, see their outstanding **Bills** and **settle** them (cash, POS, transfer, cheque or waiver).

👉 Developer reference: [Native Clients](/developer/native-client)
