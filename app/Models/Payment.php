<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


use OwenIt\Auditing\Contracts\Auditable;
class Payment extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;
    protected $fillable = [
        'reference_no', 'total', 'payment_type', 'payment_method', 'bank_id',
        'invoice_id', 'patient_id', 'user_id', 'hmo_id', 'total_discount',
        'journal_entry_id', // For linking to accounting journal entries
        'shift_id', // For billing shift tracking
    ];



    /**
     * Get the invoice associated with the payment
     *
     *
     */
    public function invoice()
    {
        return $this->hasOne(invoice::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id', 'id');
    }

    public function staff_user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class, 'bank_id', 'id');
    }

    public function product_or_service_request()
    {
        return $this->hasMany(ProductOrServiceRequest::class, 'payment_id', 'id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(Accounting\JournalEntry::class, 'journal_entry_id');
    }

    /**
     * Get the billing shift this payment was made during.
     */
    public function shift()
    {
        return $this->belongsTo(NursingShift::class, 'shift_id');
    }

    /**
     * Get staff bills created from this payment (checkout side).
     */
    public function staffBills()
    {
        return $this->hasMany(StaffBill::class, 'payment_id');
    }

    /**
     * Get organization bills created from this payment (checkout side).
     */
    public function organizationBills()
    {
        return $this->hasMany(OrganizationBill::class, 'payment_id');
    }
}
