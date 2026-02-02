<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class HmoRemittance extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'hmo_remittances';

    protected $fillable = [
        'hmo_id',
        'bank_id',
        'amount',
        'reference_number',
        'payment_method',
        'bank_name',
        'payment_date',
        'period_from',
        'period_to',
        'notes',
        'receipt_file',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'period_from' => 'date',
        'period_to' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the HMO that this remittance belongs to.
     */
    public function hmo()
    {
        return $this->belongsTo(Hmo::class, 'hmo_id');
    }

    /**
     * Get the bank where payment was received.
     */
    public function bank()
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

    /**
     * Get the user who created this remittance.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the claims associated with this remittance.
     */
    public function claims()
    {
        return $this->hasMany(ProductOrServiceRequest::class, 'hmo_remittance_id');
    }

    /**
     * Scope to filter by HMO
     */
    public function scopeByHmo($query, $hmoId)
    {
        return $query->where('hmo_id', $hmoId);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeDateRange($query, $from, $to)
    {
        return $query->whereBetween('payment_date', [$from, $to]);
    }
}
