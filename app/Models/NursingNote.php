<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


use OwenIt\Auditing\Contracts\Auditable;
class NursingNote extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;
protected $fillable = [
        'patient_id',
        'created_by',
        'updated_by',
        'nursing_note_type_id',
        'note',
        'completed',
        'status'
    ];
    public function patient(){
        return $this->belongsTo(patient::class,'patient_id','id');
    }

    public function createdBy(){
        return $this->belongsTo(User::class,'created_by','id');
    }

    public function type(){
        return $this->belongsTo(NursingNoteType::class,'nursing_note_type_id','id');
    }
}
