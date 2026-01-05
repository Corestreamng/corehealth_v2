<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Cmgmyr\Messenger\Traits\Messagable;
use Spatie\Permission\Traits\HasRoles;


use OwenIt\Auditing\Contracts\Auditable;
class User extends Authenticatable implements Auditable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use HasRoles;
    use Messagable;
    use \OwenIt\Auditing\Auditable;
/**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'is_admin',
        'filename',
        'old_records',
        'surname',
        'firstname',
        'othername',
        'assignRole',
        'assignPermission',
        'status',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the user's full name as a single property.
     *
     * @return string
     */
    public function getNameAttribute()
    {
        $othername = ($this->othername) ? $this->othername : '';
        return ucwords($this->surname . ' ' . $this->firstname . ' ' . $othername);
    }

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function category()
    {
        return $this->belongsTo(UserCategory::class, 'is_admin', 'id');
    }

    public function staff_profile()
    {
        return $this->hasOne(Staff::class, 'user_id', 'id');
    }

    public function patient_profile()
    {
        return $this->hasOne(patient::class, 'user_id', 'id');
    }

    public function conversations()
    {
        return $this->belongsToMany(ChatConversation::class, 'chat_participants', 'user_id', 'conversation_id');
    }
}
