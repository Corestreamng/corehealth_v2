<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Account Sub-Account Model
 *
 * Reference: Accounting System Plan §4.1 - Eloquent Models
 *
 * Subsidiary ledger entries that link accounts to specific entities
 * (patients, suppliers, products, services, employees, etc.)
 * for detailed tracking within a parent account.
 */
class AccountSubAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'account_id',
        'entity_type',
        'entity_id',
        'code',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Common entity types
    const ENTITY_PATIENT = 'App\\Models\\Patient';
    const ENTITY_SUPPLIER = 'App\\Models\\Supplier';
    const ENTITY_PRODUCT = 'App\\Models\\Product';
    const ENTITY_SERVICE = 'App\\Models\\Service';
    const ENTITY_EMPLOYEE = 'App\\Models\\Employee';
    const ENTITY_HMO = 'App\\Models\\Hmo';

    /**
     * Get the parent account.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the linked entity (polymorphic).
     */
    public function entity(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get all journal entry lines for this sub-account.
     */
    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    /**
     * Calculate sub-account balance for a date range.
     * Delegates to parent account balance calculation with sub-account filter.
     *
     * @param string|null $fromDate
     * @param string|null $toDate
     * @return float
     */
    public function getBalance(?string $fromDate = null, ?string $toDate = null): float
    {
        return $this->account->getBalance($fromDate, $toDate, $this->id);
    }

    /**
     * Get sub-account activity for a date range.
     *
     * @param string|null $fromDate
     * @param string|null $toDate
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActivity(?string $fromDate = null, ?string $toDate = null)
    {
        $query = JournalEntryLine::query()
            ->with(['journalEntry', 'account'])
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entry_lines.account_sub_account_id', $this->id)
            ->where('journal_entries.status', JournalEntry::STATUS_POSTED)
            ->select('journal_entry_lines.*');

        if ($fromDate) {
            $query->where('journal_entries.entry_date', '>=', $fromDate);
        }

        if ($toDate) {
            $query->where('journal_entries.entry_date', '<=', $toDate);
        }

        return $query->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entries.entry_number')
            ->get();
    }

    /**
     * Check if sub-account can be deleted.
     */
    public function canDelete(): bool
    {
        return $this->journalLines()->count() === 0;
    }

    /**
     * Scope to get active sub-accounts only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by entity type.
     */
    public function scopeForEntityType($query, string $entityType)
    {
        return $query->where('entity_type', $entityType);
    }

    /**
     * Scope to filter by parent account.
     */
    public function scopeForAccount($query, int $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    /**
     * Get display name with entity info.
     */
    public function getDisplayNameAttribute(): string
    {
        $entityName = $this->entity ? ($this->entity->name ?? $this->entity->full_name ?? '') : '';
        return $this->code . ' - ' . ($entityName ?: $this->name);
    }

    /**
     * Get the entity type short name.
     */
    public function getEntityTypeShortAttribute(): string
    {
        $map = [
            self::ENTITY_PATIENT => 'Patient',
            self::ENTITY_SUPPLIER => 'Supplier',
            self::ENTITY_PRODUCT => 'Product',
            self::ENTITY_SERVICE => 'Service',
            self::ENTITY_EMPLOYEE => 'Employee',
            self::ENTITY_HMO => 'HMO',
        ];

        return $map[$this->entity_type] ?? class_basename($this->entity_type);
    }
}
