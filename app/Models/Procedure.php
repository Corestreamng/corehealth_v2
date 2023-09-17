<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use PhpOffice\PhpSpreadsheet\Calculation\Web\Service;

class Procedure extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'requested_by',
        'requested_on',
        'patient_id',
        'billed_by',
        'billed_on',
        'pre_notes',
        'pre_notes_by',
        'post_notes',
        'post_notes_by',
        'status'
    ];

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id', 'id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by', 'id');
    }

    public function biller()
    {
        return $this->belongsTo(User::class, 'billed_by', 'id');
    }

    public function pre_documenter()
    {
        return $this->belongsTo(User::class, 'pre_notes_by', 'id');
    }

    public function post_documenter()
    {
        return $this->belongsTo(User::class, 'post_notes_by', 'id');
    }

    public function patient()
    {
        return $this->belongsTo(patient::class, 'patient_id', 'id');
    }
}
