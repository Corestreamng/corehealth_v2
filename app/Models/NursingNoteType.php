<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NursingNoteType extends Model
{
    use HasFactory;
    
    public function notes(){
        return $this->hasMany(NursingNote::class,'nursing_note_type_id','id');
    }
}
