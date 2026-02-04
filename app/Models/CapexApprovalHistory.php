<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CapexApprovalHistory extends Model
{
    use HasFactory;

    protected $table = 'capex_approval_history';

    protected $fillable = [
        'capex_request_id',
        'user_id',
        'action',
        'notes',
    ];

    // Relationships
    public function capexRequest()
    {
        return $this->belongsTo(CapexProject::class, 'capex_request_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
