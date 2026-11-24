# CoreHealth Staff App: Laboratory and Imaging

<span class="badge-role">LAB SCIENTIST · RADIOLOGIST · ADMIN — the same people as the web sidebar</span>

The Laboratory and Imaging workspaces are one flow in the staff app: request, bill, (sample), result, approve. Imaging is the laboratory without the sample step and lab numbers. Everything on a phone works one-handed; tablets and computers add columns. Back to the [Staff App guide](/user-guides/native-client).

## Laboratory
**Medical Laboratory → Lab Workbench**.

![Lab desk on a phone](/images/workbenches/native-phone-lab-desk.png)

- **Desk:** search a patient by name, file number, phone or **lab number**; tap a tile (Emergency, Billing, Sample, Results, Approval, Free-form, Completed, All pending) to list that queue, paged by the server. Filter by **bench** (department) and dates. Tapping a request opens its patient at the right stage. **Approvals**, New Patient, Walk-in, Emergency Intake, **My Transactions** (what you billed, sampled, resulted and approved), **Reports**, **Trash & Dismissed**, Contacts and Price List are under the main action and **More**.
- **A patient** has **Pending**, **New request**, **History** and their details. Every request card shows what it is, its stage, who requested it and when, the payment or HMO status and who billed, sampled or resulted it.

![A patient's requests](/images/workbenches/native-phone-lab-requests.png)

**Pending stages**
| Stage | What you do |
|---|---|
| **Billing** | Tick tests and **Bill**: the patient pays at the cashier, then the tests are ready for samples. |
| **Sample** | Tick tests and **Collect sample**: the lab number is suggested (a patient's lab number from today is reused) and checked against other patients before it is saved. |
| **Results** | **Enter result**: typed fields with reference ranges and Normal/High/Low flags (or a text template), attachments, who entered it and their cadre. When the hospital does not use approval you also enter who approved it. A test that is not paid or HMO-validated shows why and cannot be resulted yet. |
| **Free-form** | **Record result** (typed) or **Close without result**. |
| **Approval** | Approvers (unit heads, department heads, administrators) tap **Review**; everyone else sees that it is waiting. A rejected result shows the reason and **Re-enter result**. |

![Billing several tests](/images/workbenches/native-phone-lab-billing.png)

![Collecting a sample](/images/workbenches/native-phone-lab-sample.png)

Each request's ⋮ menu has **Dismiss…**, **Delete…** (reason of at least 10 characters; the requesting doctor or an administrator, and not once it is billed), the **Audit log**, and **I am performing this** for a bundled test.

**Approvals** lists every result waiting (pending and rejected); open one to read it with who entered it, then **Approve** (your name and cadre are printed on the result) or **Reject** with a reason.

![Reviewing a result](/images/workbenches/native-phone-lab-approval.png)

**Reports** has the web's filters (period, status) with an **Overview** (totals, by status, monthly trend, top tests, top requesting doctors) and the **Detailed report** (paged by the server). **Trash & Dismissed** restores what was dismissed or deleted.


![Entering a structured result](/images/workbenches/native-phone-lab-result-entry.png)

## Imaging
**Imaging / Radiology → Imaging Workbench**. It behaves like the lab desk with these differences:

![Imaging desk on a phone](/images/workbenches/native-phone-imaging-desk.png)

- **No sample step:** the tiles are Emergency, Billing, Results, Approval, Free-form and All pending; a billed scan goes straight to **Results**. There are no lab numbers. The bench chips are the **modalities** (radiology, scanning room…).
- **Requesting a scan** (**New scan**): search the imaging services, add a note to each, choose **urgency** (routine, urgent, STAT), **priority** and **special instructions**, then request.

![Requesting a scan](/images/workbenches/native-phone-imaging-new-scan.png)

- **Reporting a scan:** the result is typed or entered from a template, with attachments (images or documents up to 10 MB each; there is no image viewer, files are attachments). When the hospital does not use imaging approval, the report also records who approved it, and the server refuses a save without it.
- **Approvals, history, trash and My Transactions** work as in the laboratory. Dismissing or deleting an imaging request does not need a reason (the laboratory asks for one).
- **Reports:** imaging's own totals, status, trend, top scans and requesting doctors. (The web's Imaging Reports page used to show laboratory figures; it now uses these too.)

![Imaging reports](/images/workbenches/native-phone-imaging-reports.png)

![A patient's scans](/images/workbenches/native-phone-imaging-requests.png)

👉 Developer reference: [Native Clients](/developer/native-client)

## Results: several at once, files, text or structured
- **Enter results** (Results stage, select several): opens each result in turn ("2 of 5"); requests not yet paid or HMO-validated are skipped and counted.
- **Edit a result:** existing files are listed; the bin marks one to be removed when you save (undo before saving). A structured result can be switched to **Text**, and **Insert a template** adds a saved text template.

## Trash & dismissed (Lab, Imaging, Pharmacy)
**More → Trash & Dismissed** (Pharmacy: **More → Dismissed**) lists dismissed and deleted items a page at a time with search; each shows the reason, who and when. **Restore** puts it back at the stage it was at. Dismissing asks for a reason (required in the laboratory, optional in Imaging and Pharmacy).

![Imaging trash on a phone](/images/workbenches/native-phone-imaging-trash.png)
![Pharmacy dismissed on a phone](/images/workbenches/native-phone-pharmacy-dismissed.png)

