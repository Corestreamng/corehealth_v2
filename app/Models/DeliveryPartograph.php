<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class DeliveryPartograph extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'delivery_partograph';

    protected $fillable = [
        'delivery_record_id', 'recorded_at',
        'cervical_dilation_cm', 'descent_of_head',
        'contractions_per_10_min', 'contraction_duration_sec',
        'foetal_heart_rate', 'amniotic_fluid',
        'moulding', 'maternal_bp', 'maternal_pulse', 'maternal_temp',
        'urine_output_ml', 'urine_protein',
        'oxytocin_dose', 'iv_fluids', 'medications',
        'recorded_by',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'cervical_dilation_cm' => 'decimal:1',
        'maternal_temp' => 'decimal:1',
    ];

    /* ── Relationships ─────────────────────── */

    public function deliveryRecord()
    {
        return $this->belongsTo(DeliveryRecord::class, 'delivery_record_id');
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
