<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


use OwenIt\Auditing\Contracts\Auditable;
class LabWorkbenchAuditLog extends Model implements Auditable
{


    use \OwenIt\Auditing\Auditable;
protected $fillable = [
        'lab_service_request_id',
        'user_id',
        'action',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function labServiceRequest()
    {
        return $this->belongsTo(LabServiceRequest::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
