<?php

namespace App\Models;

use App\Traits\IsAuditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class PaymentSplit extends Model implements Auditable
{
    use IsAuditable;
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'payment_id',
        'payment_method',
        'bank_id',
        'amount',
        'reference_no',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * Get the parent payment.
     */
    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    /**
     * Get the bank associated with this split leg.
     */
    public function bank()
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }
}
