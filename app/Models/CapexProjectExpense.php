<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CapexProjectExpense extends Model
{
    use HasFactory;

    protected $table = 'capex_project_expenses';

    protected $fillable = [
        'project_id',
        'journal_entry_id',
        'purchase_order_id',
        'expense_id',
        'expense_date',
        'description',
        'vendor',
        'invoice_number',
        'payment_method',
        'bank_id',
        'cheque_number',
        'amount',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_PAID = 'paid';
    const STATUS_VOID = 'void';

    // Relationships
    public function project()
    {
        return $this->belongsTo(CapexProject::class, 'project_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(\App\Models\Accounting\JournalEntry::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }

    public function bank()
    {
        return $this->belongsTo(\App\Models\Accounting\Bank::class);
    }
}
