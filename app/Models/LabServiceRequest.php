<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


use OwenIt\Auditing\Contracts\Auditable;
class LabServiceRequest extends Model implements Auditable
{
    use HasFactory, SoftDeletes;
    use \OwenIt\Auditing\Auditable;
protected $fillable = [
        'service_request_id',
        'billed_by',
        'billed_date',
        'service_id',
        'encounter_id',
        'patient_id',
        'result',
        'result_data',
        'attachments',
        'result_date',
        'result_by',
        'sample_taken',
        'sample_date',
        'sample_taken_by',
        'doctor_id',
        'note',
        'status',
        'priority',
        'deleted_by',
        'deletion_reason',
        'dismissed_at',
        'dismissed_by',
        'dismiss_reason',
        'pending_result',
        'pending_result_data',
        'pending_attachments',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
    ];

    protected $casts = [
        'attachments' => 'array',
        'result_data' => 'array',
        'pending_result_data' => 'array',
        'pending_attachments' => 'array',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function productOrServiceRequest()
    {
        return $this->belongsTo(ProductOrServiceRequest::class, 'service_request_id', 'id');
    }

    public function service()
    {
        return $this->belongsTo(service::class, 'service_id', 'id');
    }

    public function encounter()
    {
        return $this->belongsTo(Encounter::class, 'encounter_id', 'id');
    }

    public function patient()
    {
        return $this->belongsTo(patient::class, 'patient_id', 'id');
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id', 'id');
    }

    public function biller()
    {
        return $this->belongsTo(User::class, 'billed_by', 'id');
    }

    public function results_person()
    {
        return $this->belongsTo(User::class, 'result_by', 'id');
    }

    public function resultBy()
    {
        return $this->belongsTo(User::class, 'result_by', 'id');
    }

    /**
     * Get the user who approved this result.
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by', 'id');
    }

    /**
     * Get the user who rejected this result.
     */
    public function rejector()
    {
        return $this->belongsTo(User::class, 'rejected_by', 'id');
    }

    /**
     * Check if result is pending approval.
     */
    public function isPendingApproval(): bool
    {
        return $this->status == 5;
    }

    /**
     * Check if result was rejected.
     */
    public function isRejected(): bool
    {
        return $this->status == 6;
    }

    /**
     * Check if result was approved (completed via approval workflow).
     */
    public function hasApprovedResult(): bool
    {
        return $this->status == 4 && $this->approved_by !== null;
    }

    /**
     * Get the procedure item if this lab is part of a procedure.
     * Spec Reference: PROCEDURE_MODULE_DESIGN_PLAN.md Part 3.7
     */
    public function procedureItem()
    {
        return $this->hasOne(ProcedureItem::class, 'lab_service_request_id', 'id');
    }
}
