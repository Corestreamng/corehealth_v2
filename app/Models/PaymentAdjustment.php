<?php

namespace App\Models;

use App\Traits\IsAuditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class PaymentAdjustment extends Model implements Auditable
{
    use IsAuditable;
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'payment_id',
        'original_payment_method',
        'original_bank_id',
        'original_is_split',
        'original_splits',
        'new_payment_method',
        'new_bank_id',
        'new_is_split',
        'new_splits',
        'reversal_journal_entry_id',
        'new_journal_entry_id',
        'adjusted_by',
        'reason',
        'adjusted_at',
    ];

    protected $casts = [
        'original_splits' => 'array',
        'new_splits' => 'array',
        'original_is_split' => 'boolean',
        'new_is_split' => 'boolean',
        'adjusted_at' => 'datetime',
    ];

    /**
     * Get the parent payment.
     */
    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    /**
     * Get the user who performed the adjustment.
     */
    public function adjuster()
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }

    /**
     * Get the reversal journal entry.
     */
    public function reversalJournalEntry()
    {
        return $this->belongsTo(Accounting\JournalEntry::class, 'reversal_journal_entry_id');
    }

    /**
     * Get the new journal entry.
     */
    public function newJournalEntry()
    {
        return $this->belongsTo(Accounting\JournalEntry::class, 'new_journal_entry_id');
    }
}
