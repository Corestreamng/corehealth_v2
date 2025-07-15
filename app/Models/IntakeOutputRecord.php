<?php
// IntakeOutputRecord model
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntakeOutputRecord extends Model
{
    use HasFactory;
    protected $fillable = [
        'period_id',
        'type',
        'amount',
        'description',
        'recorded_at',
        'nurse_id',
        'edited_at',
        'edited_by',
        'edit_reason',
        'deleted_at',
        'deleted_by',
        'delete_reason'
    ];
    
    protected $dates = [
        'recorded_at',
        'edited_at',
        'deleted_at'
    ];
    public function period()
    {
        return $this->belongsTo(IntakeOutputPeriod::class, 'period_id');
    }
    public function nurse()
    {
        return $this->belongsTo(User::class, 'nurse_id');
    }
    public function editedBy()
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
    
    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
    
    public function history()
    {
        return $this->hasMany(IntakeOutputHistory::class, 'record_id');
    }
}
