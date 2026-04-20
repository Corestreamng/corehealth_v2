<?php

namespace App\Models\HR;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class StaffQualification extends Model implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'staff_id',
        'type',
        'qualification_name',
        'field_of_study',
        'institution',
        'year_of_graduation',
        'date_obtained',
        'result_seen',
        'result_seen_by',
        'result_seen_at',
        'document_path',
        'notes',
    ];

    protected $casts = [
        'result_seen' => 'boolean',
        'result_seen_at' => 'datetime',
        'year_of_graduation' => 'integer',
        'date_obtained' => 'date',
    ];

    const TYPE_ENTRY = 'entry';
    const TYPE_ADDITIONAL = 'additional';

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'result_seen_by');
    }

    public function markResultSeen(int $userId): void
    {
        $this->update([
            'result_seen' => true,
            'result_seen_by' => $userId,
            'result_seen_at' => now(),
        ]);
    }

    public function scopeEntry($query)
    {
        return $query->where('type', self::TYPE_ENTRY);
    }

    public function scopeAdditional($query)
    {
        return $query->where('type', self::TYPE_ADDITIONAL);
    }
}
