<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class AncInvestigation extends Model implements Auditable
{
    use HasFactory, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'enrollment_id', 'anc_visit_id',
        'lab_service_request_id', 'imaging_service_request_id',
        'investigation_name', 'result_summary', 'is_routine',
    ];

    protected $casts = [
        'is_routine' => 'boolean',
    ];

    /* ── Relationships ─────────────────────── */

    public function enrollment()
    {
        return $this->belongsTo(MaternityEnrollment::class, 'enrollment_id');
    }

    public function ancVisit()
    {
        return $this->belongsTo(AncVisit::class, 'anc_visit_id');
    }

    public function labRequest()
    {
        return $this->belongsTo(LabServiceRequest::class, 'lab_service_request_id');
    }

    public function imagingRequest()
    {
        return $this->belongsTo(ImagingServiceRequest::class, 'imaging_service_request_id');
    }
}
