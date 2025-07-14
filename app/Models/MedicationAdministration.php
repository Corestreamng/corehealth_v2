<?php
// MedicationAdministration model
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicationAdministration extends Model
{
    use HasFactory;
    protected $fillable = [
        'patient_id',
        'product_or_service_request_id',
        'scheduled_time',
        'administered_time',
        'route',
        'note',
        'nurse_id',
    ];
    public function patient() { return $this->belongsTo(Patient::class); }
    public function productOrServiceRequest() { return $this->belongsTo(ProductOrServiceRequest::class); }
    public function nurse() { return $this->belongsTo(User::class, 'nurse_id'); }
}
