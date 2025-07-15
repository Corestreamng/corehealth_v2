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
        'schedule_id',
        'administered_at',
        'dose',
        'route',
        'comment',
        'administered_by',
        'edited_by',
        'edited_at',
        'edit_reason',
        'previous_data',
        'deleted_by',
        'delete_reason'
    ];
    public function patient() { return $this->belongsTo(Patient::class); }
    public function productOrServiceRequest() { return $this->belongsTo(ProductOrServiceRequest::class); }
    public function schedule() { return $this->belongsTo(MedicationSchedule::class); }
    public function administeredBy() { return $this->belongsTo(User::class, 'administered_by'); }
    public function editedBy() { return $this->belongsTo(User::class, 'edited_by'); }
    public function deletedBy() { return $this->belongsTo(User::class, 'deleted_by'); }

    // Keep the old nurse relationship for backward compatibility
    public function nurse() { return $this->belongsTo(User::class, 'administered_by'); }
}
