<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffBill extends Model
{
    use HasFactory;

    protected $table = 'staff_bills';

    protected $fillable = [
        'patient_id',
        'staff_user_id',
        'payment_id',
        'total_amount',
        'discount_amount',
        'outstanding_amount',
        'status',
        'settlement_payment_id',
        'settled_at',
    ];

    protected $casts = [
        'settled_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'outstanding_amount' => 'decimal:2',
    ];

    /**
     * Get the patient receiving care.
     */
    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    /**
     * Get the staff user responsible for the bill.
     */
    public function staffUser()
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    /**
     * Get the checkout payment record.
     */
    public function checkoutPayment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    /**
     * Get the settlement payment record.
     */
    public function settlementPayment()
    {
        return $this->belongsTo(Payment::class, 'settlement_payment_id');
    }

    /**
     * Get all settlement payments associated with the staff bill.
     */
    public function payments()
    {
        return $this->belongsToMany(Payment::class, 'staff_bill_payment_allocations', 'staff_bill_id', 'payment_id')
            ->withPivot('amount_allocated', 'discount_allocated')
            ->withTimestamps();
    }
}
