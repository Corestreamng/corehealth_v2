<?php

namespace App\Models\HR;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class StaffMedicalExam extends Model implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'staff_id',
        'exam_date',
        'exam_type',
        'result',
        'next_exam_due',
        'conducted_by',
        'notes',
        'document_path',
        'recorded_by',
    ];

    protected $casts = [
        'exam_date' => 'date',
        'next_exam_due' => 'date',
    ];

    const TYPE_PRE_EMPLOYMENT = 'pre_employment';
    const TYPE_PERIODIC = 'periodic';
    const TYPE_EXIT = 'exit';

    const RESULT_FIT = 'fit';
    const RESULT_UNFIT = 'unfit';
    const RESULT_CONDITIONAL = 'conditional';

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function getResultBadgeAttribute(): string
    {
        return match ($this->result) {
            self::RESULT_FIT => 'badge-success',
            self::RESULT_UNFIT => 'badge-danger',
            self::RESULT_CONDITIONAL => 'badge-warning',
            default => 'badge-secondary',
        };
    }

    public function getExamTypeLabelAttribute(): string
    {
        return match ($this->exam_type) {
            self::TYPE_PRE_EMPLOYMENT => 'Pre-Employment',
            self::TYPE_PERIODIC => 'Periodic',
            self::TYPE_EXIT => 'Exit',
            default => ucfirst($this->exam_type),
        };
    }

    public static function getExamTypes(): array
    {
        return [
            self::TYPE_PRE_EMPLOYMENT => 'Pre-Employment',
            self::TYPE_PERIODIC => 'Periodic',
            self::TYPE_EXIT => 'Exit',
        ];
    }

    public static function getResults(): array
    {
        return [
            self::RESULT_FIT => 'Fit',
            self::RESULT_UNFIT => 'Unfit',
            self::RESULT_CONDITIONAL => 'Conditional',
        ];
    }
}
