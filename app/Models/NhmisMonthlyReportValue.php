<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class NhmisMonthlyReportValue extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'nhmis_monthly_report_values';

    protected $fillable = [
        'report_id',
        'cell_key',
        'auto_value',
        'override_value',
        'final_value',
        'override_reason',
        'overridden_by',
        'metadata',
    ];

    protected $casts = [
        'auto_value' => 'float',
        'override_value' => 'float',
        'final_value' => 'float',
        'metadata' => 'array',
    ];

    public function report()
    {
        return $this->belongsTo(NhmisMonthlyReport::class, 'report_id', 'id');
    }

    public function overriddenBy()
    {
        return $this->belongsTo(User::class, 'overridden_by', 'id');
    }

    /**
     * Check if this cell has been manually adjusted
     */
    public function getIsOverriddenAttribute(): bool
    {
        return $this->override_value !== null;
    }
}
