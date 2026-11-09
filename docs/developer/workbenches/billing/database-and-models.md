# Billing Database Schema, Models & Observers

<span class="badge-role">AUTHORITATIVE ROLE GUARD: SUPERADMIN | ADMIN | ACCOUNTS | BILLER</span>

The Billing Workbench relies on relational schemas designed for transactional speed, auditability, and double-entry General Ledger balancing.

---

## 1. Entity-Relationship Architecture

```mermaid
erDiagram
    BILLING_SHIFTS ||--o{ PAYMENTS : "contains"
    PATIENTS ||--o| PATIENT_ACCOUNTS : "owns"
    PATIENT_ACCOUNTS ||--o{ PATIENT_ACCOUNT_TRANSACTIONS : "logs"
    PAYMENTS ||--o{ PAYMENT_SPLITS : "divides into"
    PAYMENTS ||--o{ PAYMENT_ADJUSTMENTS : "tracked by"
    PAYMENTS ||--|| JOURNAL_ENTRIES : "creates"

    PAYMENTS {
        bigint id PK
        bigint patient_id FK
        bigint billing_shift_id FK
        string receipt_number UK
        decimal amount
        decimal discount_amount
        string payment_method
        bigint bank_id FK
        boolean is_split
        string status
        timestamp created_at
    }

    PAYMENT_SPLITS {
        bigint id PK
        bigint payment_id FK
        string payment_method
        decimal amount
        bigint bank_id FK
        string reference
    }

    PATIENT_ACCOUNTS {
        bigint id PK
        bigint patient_id FK UK
        decimal balance
        string status
        timestamp updated_at
    }

    BILLING_SHIFTS {
        bigint id PK
        bigint user_id FK
        string shift_type
        timestamp started_at
        timestamp ended_at
        string status
    }
```

---

## 2. Core Eloquent Models

### `App\Models\Payment`
- **Key Properties**: `id`, `patient_id`, `amount`, `payment_method`, `is_split`, `status`.
- **Relationships**:
  - `patient()`: `BelongsTo` &rarr; `App\Models\Patient`
  - `splits()`: `HasMany` &rarr; `App\Models\PaymentSplit`
  - `adjustments()`: `HasMany` &rarr; `App\Models\PaymentAdjustment`
  - `shift()`: `BelongsTo` &rarr; `App\Models\BillingShift`
  - `journalEntry()`: `HasOne` &rarr; `App\Models\Accounting\JournalEntry`

### `App\Models\PaymentSplit`
- Stores individual tender legs for split transactions (`CASH`, `POS`, `TRANSFER`, `ACCOUNT`).
- Preserves the ledger balance: $\sum \text{PaymentSplit.amount} \equiv \text{Payment.amount}$.

### `App\Models\PatientAccount`
- Represents the patient's pre-funded wallet.
- `balance` column is modified strictly via atomic transactions (`PatientAccountTransaction`) to prevent race conditions during simultaneous ward and pharmacy debits.

---

## 3. The `PaymentObserver` Invariant

The `PaymentObserver` monitors all Eloquent lifecycle events (`created`, `updated`, `deleted`) on `Payment`:

```php
public function created(Payment $payment): void
{
    // 1. Instantiate Double-Entry Journal Entry
    $journalEntry = JournalEntry::create([
        'reference_number' => 'JE-PAY-' . $payment->receipt_number,
        'entry_date' => now(),
        'description' => "Payment clearance for {$payment->patient->fullname}",
        'source_type' => Payment::class,
        'source_id' => $payment->id,
        'status' => JournalEntry::STATUS_POSTED,
    ]);

    // 2. Post Debit Legs (Assets / Tender Accounts)
    if ($payment->is_split) {
        foreach ($payment->splits as $split) {
            $debitAccount = $this->resolveTenderAccount($split->payment_method, $split->bank_id);
            $journalEntry->items()->create([
                'account_id' => $debitAccount->id,
                'debit' => $split->amount,
                'credit' => 0,
            ]);
        }
    } else {
        $debitAccount = $this->resolveTenderAccount($payment->payment_method, $payment->bank_id);
        $journalEntry->items()->create([
            'account_id' => $debitAccount->id,
            'debit' => $payment->amount,
            'credit' => 0,
        ]);
    }

    // 3. Post Credit Legs (Department Revenue Accounts)
    foreach ($payment->items as $item) {
        $revenueAccount = $this->resolveRevenueAccount($item->category);
        $journalEntry->items()->create([
            'account_id' => $revenueAccount->id,
            'debit' => 0,
            'credit' => $item->amount,
        ]);
    }
}
```

---

## Developer Reference
👉 **[Read Billing API Contracts](/developer/workbenches/billing/api-contracts)**  
👉 **[Read Billing User Guide](/user-guides/workbenches/billing)**

