# Billing & Revenue Cycle Developer API Reference

<span class="badge-role">AUTHORITATIVE ROLE GUARD: SUPERADMIN | ADMIN | ACCOUNTS | BILLER</span>

## 1. Controllers & Verified Route Contracts
- **Primary Controller**: `App\Http\Controllers\BillingWorkbenchController` and `App\Http\Controllers\PaymentController`.
- **Primary Routes (`routes/web.php`)**:
  - `GET /billing-workbench` (`billing.workbench`) — Cashier queue, receipts, and deposits cockpit.
  - `POST /billing-workbench/process-payment` (`billing.process-payment`) — Multi-tender and split payment checkout.
  - `POST /billing-workbench/make-deposit` (`billing.make-deposit`) — Patient wallet deposit.
  - `POST /billing-workbench/account-transaction` (`billing.account-transaction`) — Patient wallet withdrawal/adjustment.
  - `POST /billing-workbench/void-transaction` (`billing.void-transaction`) — Reversing transaction voiding.

## 2. Models, Observers & Split Payments Ledger
- `Payment`: `$pending_splits` transport property prevents column insert errors.
- `PaymentSplit`: Multi-leg tender ledger (`payment_splits` table).
- `PaymentObserver`: Enforces multi-leg balancing JEs (`DR Cash/POS/Transfer, CR Department Revenue`).
- `PaymentAdjustment`: Audit trail for payment method reclassifications.

## 3. Testing Verification
```bash
vendor/bin/phpunit tests/Feature/Billing/BillingWorkbenchTest.php
```

👉 **[Read User Story Guide for Billing](/user-guides/workbenches/billing)**
