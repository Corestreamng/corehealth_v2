<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class MaternityMedicalHistory extends Model implements Auditable
{
    use HasFactory, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'maternity_medical_history';

    protected $fillable = [
        'enrollment_id', 'category', 'description', 'year', 'notes',
    ];

    public function enrollment()
    {
        return $this->belongsTo(MaternityEnrollment::class, 'enrollment_id');
    }
}
