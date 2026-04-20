<?php

namespace App\Models\HR;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class StaffNextOfKin extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'staff_next_of_kin';

    protected $fillable = [
        'staff_id',
        'full_name',
        'relationship',
        'phone',
        'email',
        'address',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }
}
