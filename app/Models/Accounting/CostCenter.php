<?php

namespace App\Models\Accounting;

use App\Models\User;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Cost Center Model
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 6.11
 * Reference: ACCOUNTING_IMPLEMENTATION_CHECKLIST.md - Phase 7.1
 */
class CostCenter extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'cost_centers';

    // Center Types
    public const TYPE_REVENUE = 'revenue';
    public const TYPE_COST = 'cost';
    public const TYPE_SERVICE = 'service';
    public const TYPE_PROJECT = 'project';

    protected $fillable = [
        'code',
        'name',
        'department_id',
        'manager_user_id',
        'parent_cost_center_id',
        'center_type',
        'hierarchy_level',
        'is_active',
        'description',
    ];

    protected $casts = [
        'hierarchy_level' => 'integer',
        'is_active' => 'boolean',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function parent()
    {
        return $this->belongsTo(CostCenter::class, 'parent_cost_center_id');
    }

    public function children()
    {
        return $this->hasMany(CostCenter::class, 'parent_cost_center_id');
    }

    public function budgets()
    {
        return $this->hasMany(CostCenterBudget::class);
    }

    public function journalEntryLines()
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function incomingAllocations()
    {
        return $this->hasMany(CostCenterAllocation::class, 'target_cost_center_id');
    }

    public function outgoingAllocations()
    {
        return $this->hasMany(CostCenterAllocation::class, 'source_cost_center_id');
    }

    // ==========================================
    // BALANCE CALCULATIONS (JE-Centric)
    // ==========================================

    /**
     * Get balance from journal entries for this cost center.
     */
    public function getBalance(?string $fromDate = null, ?string $toDate = null, ?int $accountId = null): float
    {
        $query = JournalEntryLine::where('cost_center_id', $this->id)
            ->whereHas('journalEntry', function ($q) {
                $q->where('status', JournalEntry::STATUS_POSTED);
            });

        if ($fromDate) {
            $query->whereHas('journalEntry', fn($q) => $q->where('entry_date', '>=', $fromDate));
        }

        if ($toDate) {
            $query->whereHas('journalEntry', fn($q) => $q->where('entry_date', '<=', $toDate));
        }

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        return $query->sum('debit') - $query->sum('credit');
    }

    /**
     * Get expense total for period.
     */
    public function getExpensesForPeriod(string $fromDate, string $toDate): float
    {
        return JournalEntryLine::where('cost_center_id', $this->id)
            ->whereHas('journalEntry', function ($q) use ($fromDate, $toDate) {
                $q->where('status', JournalEntry::STATUS_POSTED)
                  ->whereBetween('entry_date', [$fromDate, $toDate]);
            })
            ->whereHas('account.accountGroup.accountClass', function ($q) {
                $q->where('name', 'EXPENSE');
            })
            ->sum('debit');
    }

    /**
     * Get revenue total for period (for revenue centers).
     */
    public function getRevenueForPeriod(string $fromDate, string $toDate): float
    {
        return JournalEntryLine::where('cost_center_id', $this->id)
            ->whereHas('journalEntry', function ($q) use ($fromDate, $toDate) {
                $q->where('status', JournalEntry::STATUS_POSTED)
                  ->whereBetween('entry_date', [$fromDate, $toDate]);
            })
            ->whereHas('account.accountGroup.accountClass', function ($q) {
                $q->where('name', 'INCOME');
            })
            ->sum('credit');
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('center_type', $type);
    }

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_cost_center_id');
    }

    // ==========================================
    // HELPERS
    // ==========================================

    public function getTypeLabelAttribute(): string
    {
        return match ($this->center_type) {
            self::TYPE_REVENUE => 'Revenue Center',
            self::TYPE_COST => 'Cost Center',
            self::TYPE_SERVICE => 'Service Center',
            self::TYPE_PROJECT => 'Project Center',
            default => ucfirst($this->center_type ?? 'Unknown'),
        };
    }

    public function getFullPathAttribute(): string
    {
        $path = [$this->name];
        $current = $this;

        while ($current->parent) {
            $current = $current->parent;
            array_unshift($path, $current->name);
        }

        return implode(' > ', $path);
    }
}
