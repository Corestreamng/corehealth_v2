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
        'nurse_id'
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
}
