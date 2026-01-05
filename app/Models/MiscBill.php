<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


use OwenIt\Auditing\Contracts\Auditable;
class MiscBill extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;
protected $fillable = [
        'service_request_id',
        'created_by',
        'creation_date',
        'billed_by',
        'billed_date',
        'service_id',
        'patient_id',
        'status'
    ];


    public function productOrServiceRequest(){
        return $this->belongsTo(ProductOrServiceRequest::class,'service_request_id','id');
    }

    public function service(){
        return $this->belongsTo(service::class, 'service_id','id');
    }

    public function patient(){
        return $this->belongsTo(patient::class, 'patient_id','id');
    }

    public function creator(){
        return $this->belongsTo(User::class, 'created_by','id');
    }

    public function biller(){
        return $this->belongsTo(User::class, 'billed_by','id');
    }

}
