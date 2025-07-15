<?php
// IntakeOutputPeriod model
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntakeOutputPeriod extends Model
{
    use HasFactory;
    protected $fillable = [
        'patient_id',
        'type',
        'started_at',
        'ended_at',
        'ended_by',
        'nurse_id'
    ];
    
    protected $dates = [
        'started_at',
        'ended_at'
    ];
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
    public function nurse()
    {
        return $this->belongsTo(User::class, 'nurse_id');
    }
    public function records()
    {
        return $this->hasMany(IntakeOutputRecord::class, 'period_id');
    }
    public function endedByNurse()
    {
        return $this->belongsTo(User::class, 'ended_by');
    }
    
    public function history()
    {
        return $this->hasMany(IntakeOutputHistory::class, 'period_id');
    }
}
