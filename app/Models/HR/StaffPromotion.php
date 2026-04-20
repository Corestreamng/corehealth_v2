<?php

namespace App\Models\HR;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class StaffPromotion extends Model implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'staff_id',
        'from_grade_level_id',
        'to_grade_level_id',
        'from_job_title',
        'to_job_title',
        'promotion_date',
        'effective_date',
        'next_promotion_due_date',
        'authority',
        'remarks',
        'processed_by',
    ];

    protected $casts = [
        'promotion_date' => 'date',
        'effective_date' => 'date',
        'next_promotion_due_date' => 'date',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function fromGradeLevel()
    {
        return $this->belongsTo(GradeLevel::class, 'from_grade_level_id');
    }

    public function toGradeLevel()
    {
        return $this->belongsTo(GradeLevel::class, 'to_grade_level_id');
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function getPromotionSummaryAttribute(): string
    {
        $from = $this->from_job_title ?? $this->fromGradeLevel?->name ?? 'N/A';
        $to = $this->to_job_title ?? $this->toGradeLevel?->name ?? 'N/A';
        return "{$from} → {$to}";
    }
}
