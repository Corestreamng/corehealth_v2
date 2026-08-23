<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillingQueue extends Model
{
    use HasFactory;

    protected $table = 'billing_queues';

    protected $fillable = [
        'user_id',
        'patient_id',
        'unpaid_items_count',
        'hmo_items_count',
        'is_emergency',
        'latest_item_at',
    ];

    protected $casts = [
        'is_emergency' => 'boolean',
        'latest_item_at' => 'datetime',
    ];

    /**
     * The user this queue belongs to (the billing target)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The associated patient record (for clinical context)
     */
    public function patient()
    {
        // Patient's user_id maps to BillingQueue's user_id
        return $this->belongsTo(Patient::class, 'user_id', 'user_id');
    }
}
