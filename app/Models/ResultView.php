<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Polymorphic result view tracker.
 *
 * Logs each time a user views (modal) or prints a lab/imaging result.
 * Used for audit trails and unviewed-result badge counters on clinical tabs.
 */
class ResultView extends Model
{
    use HasFactory;

    protected $fillable = [
        'viewable_type',
        'viewable_id',
        'user_id',
        'view_type',
        'ip_address',
    ];

    /**
     * The result that was viewed (LabServiceRequest or ImagingServiceRequest).
     */
    public function viewable()
    {
        return $this->morphTo();
    }

    /**
     * The user who viewed the result.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
