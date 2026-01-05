<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


use OwenIt\Auditing\Contracts\Auditable;
class NursingNoteType extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;
public function notes(){
        return $this->hasMany(NursingNote::class,'nursing_note_type_id','id');
    }
}
