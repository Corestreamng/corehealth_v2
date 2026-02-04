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
        'amount',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
    ];

    // Relationships
    public function project()
    {
        return $this->belongsTo(CapexProject::class, 'project_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }
}
