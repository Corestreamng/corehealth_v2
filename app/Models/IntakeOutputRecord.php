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
        'nurse_id'
    ];
    public function period()
    {
        return $this->belongsTo(IntakeOutputPeriod::class, 'period_id');
    }
    public function nurse()
    {
        return $this->belongsTo(User::class, 'nurse_id');
    }
}
