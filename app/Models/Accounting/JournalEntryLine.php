<?php

namespace App\Models\Accounting;

use App\Models\Department;
use App\Models\Hmo;
use App\Models\patient;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Journal Entry Line Model
 *
 * Reference: BANK_CASH_STATEMENT_IMPLEMENTATION.md - Part 5A
 *
 * Individual debit/credit line within a journal entry.
 * Each line affects exactly one account (and optionally a sub-account).
 *
 * METADATA FIELDS (for granular filtering and drill-down):
 * - product_id: Links to product (pharmacy, inventory)
 * - service_id: Links to service (consultation, lab, imaging)
 * - product_category_id: Product category for grouping
 * - service_category_id: Service category for grouping
 * - hmo_id: HMO company for AR tracking
 * - supplier_id: Supplier for AP tracking
 * - patient_id: Patient for patient-level tracking
 * - department_id: Department for departmental reporting
 * - category: Quick category string (lab, pharmacy, payroll, etc.)
 */
class JournalEntryLine extends Model implements Auditable
{
    use HasFactory, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'journal_entry_id',
        'line_number',
        'account_id',
        'sub_account_id',
        'narration',
        'debit',
        'credit',
        'cash_flow_category',
        // Metadata fields for granular filtering
        'product_id',
        'service_id',
        'product_category_id',
        'service_category_id',
        'hmo_id',
        'supplier_id',
        'patient_id',
        'department_id',
        'category',
    ];

    protected $casts = [
        'debit' => 'decimal:4',
        'credit' => 'decimal:4',
        'line_number' => 'integer',
    ];

    // =========================================
    // ATTRIBUTE ALIASES (for backward compatibility)
    // =========================================

    /**
     * Alias: debit_amount -> debit
     */
    public function getDebitAmountAttribute(): float
    {
        return (float) $this->debit;
    }

    /**
     * Alias: credit_amount -> credit
     */
    public function getCreditAmountAttribute(): float
    {
        return (float) $this->credit;
    }

    /**
     * Alias: description -> narration
     */
    public function getDescriptionAttribute(): ?string
    {
        return $this->narration;
    }

    /**
     * Alias: line_order -> line_number
     */
    public function getLineOrderAttribute(): ?int
    {
        return $this->line_number;
    }

    /**
     * Alias: account_sub_account_id -> sub_account_id
     */
    public function getAccountSubAccountIdAttribute(): ?int
    {
        return $this->sub_account_id;
    }

    // =========================================
    // RELATIONSHIPS
    // =========================================

    /**
     * Get the journal entry this line belongs to.
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * Get the account for this line.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the sub-account for this line (optional).
     */
    public function subAccount(): BelongsTo
    {
        return $this->belongsTo(AccountSubAccount::class, 'sub_account_id');
    }

    // =========================================
    // METADATA RELATIONSHIPS (for granular filtering)
    // =========================================

    /**
     * Get the product associated with this line.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the service associated with this line.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Get the product category associated with this line.
     */
    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    /**
     * Get the service category associated with this line.
     */
    public function serviceCategory(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class);
    }

    /**
     * Get the HMO associated with this line.
     */
    public function hmo(): BelongsTo
    {
        return $this->belongsTo(Hmo::class);
    }

    /**
     * Get the supplier associated with this line.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the patient associated with this line.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(patient::class);
    }

    /**
     * Get the department associated with this line.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    // =========================================
    // METADATA SCOPES (for granular queries)
    // =========================================

    /**
     * Scope to filter by product.
     */
    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope to filter by service.
     */
    public function scopeForService($query, int $serviceId)
    {
        return $query->where('service_id', $serviceId);
    }

    /**
     * Scope to filter by product category.
     */
    public function scopeForProductCategory($query, int $categoryId)
    {
        return $query->where('product_category_id', $categoryId);
    }

    /**
     * Scope to filter by service category.
     */
    public function scopeForServiceCategory($query, int $categoryId)
    {
        return $query->where('service_category_id', $categoryId);
    }

    /**
     * Scope to filter by HMO.
     */
    public function scopeForHmo($query, int $hmoId)
    {
        return $query->where('hmo_id', $hmoId);
    }

    /**
     * Scope to filter by supplier.
     */
    public function scopeForSupplier($query, int $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    /**
     * Scope to filter by patient.
     */
    public function scopeForPatient($query, int $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    /**
     * Scope to filter by department.
     */
    public function scopeForDepartment($query, int $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    /**
     * Scope to filter by category string.
     */
    public function scopeForCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Check if this is a debit line.
     */
    public function isDebit(): bool
    {
        return $this->debit > 0;
    }

    /**
     * Check if this is a credit line.
     */
    public function isCredit(): bool
    {
        return $this->credit > 0;
    }

    /**
     * Get the line amount (debit or credit, whichever is non-zero).
     */
    public function getAmountAttribute(): float
    {
        return $this->isDebit() ? (float) $this->debit : (float) $this->credit;
    }

    /**
     * Get the line type (debit or credit).
     */
    public function getTypeAttribute(): string
    {
        return $this->isDebit() ? 'debit' : 'credit';
    }

    /**
     * Validate that line has either debit or credit, not both.
     */
    public function isValid(): bool
    {
        $hasDebit = $this->debit > 0;
        $hasCredit = $this->credit > 0;

        // Must have exactly one of debit or credit
        return ($hasDebit xor $hasCredit);
    }

    /**
     * Scope to get debit lines only.
     */
    public function scopeDebits($query)
    {
        return $query->where('debit', '>', 0);
    }

    /**
     * Scope to get credit lines only.
     */
    public function scopeCredits($query)
    {
        return $query->where('credit', '>', 0);
    }

    /**
     * Scope to filter by account.
     */
    public function scopeForAccount($query, int $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    /**
     * Scope to filter by sub-account.
     */
    public function scopeForSubAccount($query, int $subAccountId)
    {
        return $query->where('sub_account_id', $subAccountId);
    }

    /**
     * Scope to order by line number.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('line_number');
    }

    /**
     * Get formatted debit amount for display.
     */
    public function getFormattedDebitAttribute(): string
    {
        return $this->debit > 0 ? number_format($this->debit, 2) : '';
    }

    /**
     * Get formatted credit amount for display.
     */
    public function getFormattedCreditAttribute(): string
    {
        return $this->credit > 0 ? number_format($this->credit, 2) : '';
    }

    /**
     * Get the account display name with code.
     */
    public function getAccountDisplayAttribute(): string
    {
        if (!$this->account) {
            return '';
        }

        $display = $this->account->display_name;

        if ($this->subAccount) {
            $display .= ' → ' . $this->subAccount->display_name;
        }

        return $display;
    }
}
