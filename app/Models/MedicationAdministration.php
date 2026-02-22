<?php
// MedicationAdministration model
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use OwenIt\Auditing\Contracts\Auditable;
class MedicationAdministration extends Model implements Auditable
{
    use HasFactory, SoftDeletes;
    use \OwenIt\Auditing\Auditable;
protected $fillable = [
        'patient_id',
        'product_id',
        'product_or_service_request_id',
        'schedule_id',
        'administered_at',
        'dose',
        'qty',
        'route',
        'comment',
        'administered_by',
        'drug_source',
        'product_request_id',
        'external_drug_name',
        'external_qty',
        'external_batch_number',
        'external_expiry_date',
        'external_source_note',
        'store_id',
        'dispensed_from_batch_id',
        'edited_by',
        'edited_at',
        'edit_reason',
        'previous_data',
        'deleted_by',
        'delete_reason'
    ];
    public function patient() { return $this->belongsTo(patient::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function productOrServiceRequest() { return $this->belongsTo(ProductOrServiceRequest::class); }
    public function productRequest() { return $this->belongsTo(ProductRequest::class); }
    public function schedule() { return $this->belongsTo(MedicationSchedule::class); }
    public function administeredBy() { return $this->belongsTo(User::class, 'administered_by'); }
    public function editedBy() { return $this->belongsTo(User::class, 'edited_by'); }
    public function deletedBy() { return $this->belongsTo(User::class, 'deleted_by'); }
    public function store() { return $this->belongsTo(Store::class, 'store_id'); }
    public function dispensedFromBatch() { return $this->belongsTo(StockBatch::class, 'dispensed_from_batch_id'); }

    // Keep the old nurse relationship for backward compatibility
    public function nurse() { return $this->belongsTo(User::class, 'administered_by'); }
}
