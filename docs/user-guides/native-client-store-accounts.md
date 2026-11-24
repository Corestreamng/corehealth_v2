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
- **Purchase orders:** create, submit, approve, **receive goods** (batch, expiry, actual cost), **pay the supplier**, cancel. Receiving creates the amount owed to the supplier; paying clears it.
- **Reports:** expiry (expired, critical, soon), stock value by product, movement summary (given out or received, by category, product or destination, with revenue and profit).

## Accounting
**Accounting** section: Overview, Journals, Reports, Accounts, Periods, Payables, Expenses, Credit notes, Deposits, Petty cash, Transfers. The overview shows the open period, income and expenses, what waits for approval, and the ageing of what is **owed to us** and what **we owe**.

![Journal entries on a phone](/images/workbenches/native-phone-accounting-journals.png)
![Journal entries on a desktop](/images/workbenches/native-desktop-accounting-journals.png)

- **Journals:** draft, submit, approve, post, reverse; a posted entry can only be corrected by a request a reviewer approves. The editor shows debit, credit and the difference live.
- **Reports:** trial balance, profit and loss, balance sheet, cash flow, general ledger, aged receivables, aged payables and the daily audit, each with its dates, as one layout on every screen.

![Trial balance on a phone](/images/workbenches/native-phone-accounting-trial-balance.png)

- **Payables, expenses, credit notes, patient deposits, petty cash, bank transfers:** list, record, approve or reject, as on the web, with the web's permissions.
- **Still on the web:** bank reconciliation matching, fixed assets, liabilities, leases, cost centres, capex, budgets, cash-flow forecast, financial KPIs, statutory remittances, opening balances and exports.

👉 Developer reference: [Native Clients](/developer/native-client)
