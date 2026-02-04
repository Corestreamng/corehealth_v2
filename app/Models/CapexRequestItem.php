<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CapexRequestItem extends Model
{
    use HasFactory;

    protected $table = 'capex_request_items';

    protected $fillable = [
        'capex_request_id',
        'description',
        'quantity',
        'unit_cost',
        'amount',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    // Relationships
    public function capexRequest()
    {
        return $this->belongsTo(CapexProject::class, 'capex_request_id');
    }
}
