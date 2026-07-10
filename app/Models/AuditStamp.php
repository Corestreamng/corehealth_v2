<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditStamp extends Model
{
    use HasFactory;

    protected $table = 'audit_stamps';

    protected $fillable = [
        'user_id',
        'responsibility_key',
        'from_date',
        'to_date',
        'status',
        'notes',
        'stamped_at',
    ];

    protected $casts = [
        'from_date' => 'datetime',
        'to_date' => 'datetime',
        'stamped_at' => 'datetime',
    ];

    /**
     * Get the auditor user.
     */
    public function auditor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
