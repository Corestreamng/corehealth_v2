# CoreHealth Staff App: Store, Requisitions and Accounting

<span class="badge-role">STORE · ACCOUNTS · ADMIN · every staff member for Requisitions and My Stock</span>

The same workspaces as the web, with the same actions, on a phone, a tablet and a desktop. Back to the [Staff App guide](/user-guides/native-client).

## Staff Supplies (every staff member)
**Staff Workspace → Requisitions** and **My Stock** (the HR items of that section stay on the web).
- **Requisitions:** list with My requests / Needs approval / Ready to send; **New requisition** (supplying store, your store, products with packaging such as Box of 100); open one to approve (per item quantity), reject, **send stock** (batches pre-filled first-in-first-out), edit, cancel, reverse an item decision or mark it returned.
- **My Stock:** the stock of the store you work from, with filters and batches; **Use stock** for the ward or for a patient, optionally billed with the patient's HMO price shown first; the **History** of what moved.

## Store Workbench
**Store / Inventory → Store Workbench.** Choose the store (the one for your shift or department is pre-selected). Sections: **Overview**, **Tally card**, **Stock**, **Pending**, **Purchase orders**, **Returns & damages**, **Reports**.

![Store overview on a phone](/images/workbenches/native-phone-store-overview.png)

### The tally card
The stock ledger of the whole store or one product: opening balance, every movement with the running balance, closing balance, and what is in the store now. Filter by dates and kind of movement; search by product, batch or reference. On a computer the selected movement opens in a panel beside the list (batch, ledger entry it posted, who and why, and actions); on a phone it opens in a sheet.

![Tally card on a phone](/images/workbenches/native-phone-store-tally.png)
![Tally card on a desktop](/images/workbenches/native-desktop-store-tally.png)

**Actions** (one button on a phone, grouped buttons on a computer): new requisition, add stock batch, new purchase order, adjust stock (add, remove, write off expired or damaged), record damage (waits for approval), return requisition items, return PO items; on a movement: edit, void or return a batch, void an adjustment. Each shows the **ledger impact** (Dr / Cr) before you confirm.

### Pending, orders, returns, reports
- **Pending:** six queues (requests to send, my requests, POs to receive, damages, requisition returns, PO returns) with counts; approve or reject in place or open the record.
- **Purchase orders:** create, **edit a saved draft** (open the order, choose Edit, change supplier, store, dates or items, then Save changes or submit), submit, approve, **receive goods** (batch, expiry, actual cost), **pay the supplier**, cancel. Receiving creates the amount owed to the supplier; paying clears it.
- **Reports:** expiry (expired, critical, soon), stock value by product, movement summary (given out or received, by category, product or destination, with revenue and profit).

## Store masters, governance and expenses
In the **Store** section, **Stores**, **Products** and **Categories** open one screen with a bar for Stores, Products, Categories and Suppliers. Each is a paged list with search; tap a row to see the record, edit it, deactivate it (products) or delete it (categories and suppliers, which the server refuses while stock or orders still use them). **New** opens the same form as the web; a product form has its packaging levels (a box of strips of tablets) and the half- and piece-sale fields only when the hospital allows them.

![Products on a phone](/images/workbenches/native-phone-store-products.png)
![Products on a desktop](/images/workbenches/native-desktop-store-products.png)

**Store Governance** (needs the governance permission): *Store roles* (what each store is, whether it dispenses to patients, who supplies it, its ward, department and manager; switching off direct dispensing with pending dispenses asks you first), *Transfer lanes* (which kind of store may send to which, and who approves) and *Working store rules* (how a person's store is picked, with a panel to try a person and see the steps).

![Transfer lanes on a phone](/images/workbenches/native-phone-store-governance.png)

In the **Pharmacy** section, Stores, Products and Categories open the same screens.

**Expenses** (Store section) is the same list, record and approve, reject or void flow as in Accounting, available to store keepers; what each person can do still follows the expense permissions.

![Store expenses on a phone](/images/workbenches/native-phone-store-expenses.png)

## Accounting
**Accounting** section: Overview, Journals, Reports, Accounts, Periods, Payables, Expenses, Credit notes, Deposits, Petty cash, Transfers. The overview shows the open period, income and expenses, what waits for approval, and the ageing of what is **owed to us** and what **we owe**.

![Journal entries on a phone](/images/workbenches/native-phone-accounting-journals.png)
![Journal entries on a desktop](/images/workbenches/native-desktop-accounting-journals.png)

- **Journals:** draft, submit, approve, post, reverse; a posted entry can only be corrected by a request a reviewer approves. The editor shows debit, credit and the difference live.
- **Reports:** trial balance, profit and loss, balance sheet, cash flow, general ledger, aged receivables, aged payables and the daily audit, each with its dates, as one layout on every screen.

![Trial balance on a phone](/images/workbenches/native-phone-accounting-trial-balance.png)

- **Payables, expenses, credit notes, patient deposits, petty cash, bank transfers:** list, record, approve or reject, as on the web, with the web's permissions.
- **Finance modules:** each sidebar item opens its group, with a bar to move inside it.
  - *Fixed assets, Capex:* register, edit, dispose or void an asset; request, approve, reject, ask for revision, start, record expenses and complete a project.
  - *Liabilities, Leases, Statutory remittances:* create, record payments (liabilities), modify or terminate (leases), submit, approve, pay or void (remittances); each record shows its schedule.
  - *Budgets, Cash-flow forecast, Cost centres, KPIs:* create or edit a budget with its lines (account, amount, note), see lines against actual, submit, approve, reject, unapprove and lock it; create and activate forecasts, add items to a period and keep the recurring patterns; manage cost centres with their budgets and cost allocations; add and edit KPIs, calculate them, read their history and acknowledge alerts.
  - *Banks, Reconciliation, Opening balances:* start a reconciliation from the bank statement figures, add statement lines, select a ledger line and a statement line and **match** them, unmatch, mark outstanding, then submit for review, approve and finalise; set each account's opening balance for a fiscal year.
![A liability on a phone](/images/workbenches/native-phone-finance-liability.png)
![Bank reconciliation on a phone](/images/workbenches/native-phone-finance-reconciliation.png)
![Bank reconciliation on a desktop](/images/workbenches/native-desktop-finance-reconciliation.png)
![A budget on a tablet](/images/workbenches/native-tablet-finance-budget.png)
![Budget editor on a phone](/images/workbenches/native-phone-finance-budget-editor.png)
![Financial KPIs on a phone](/images/workbenches/native-phone-finance-kpis.png)

- **Saved filters and downloads:** on Reports, the menu saves the report with its dates, loads or deletes saved filters, and downloads the PDF or Excel. Lists and records that have a PDF or Excel on the web (fixed assets, capex, liabilities, leases, cost centre reports, forecasts, budgets, reconciliations) have a **Download** button too.
- **Bank statement files:** on a reconciliation, **Upload statement file** stores the bank's file with it; tap the file to read it beside the lines, then add the lines you need to match.
- **Pharmacy returns:** approving a return reverses the sale; goods put back on the shelf return to inventory at their cost, damaged goods do not.

👉 Developer reference: [Native Clients](/developer/native-client)
