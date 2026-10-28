<?php

namespace App\Models;

use App\Traits\IsAuditable;
use Cmgmyr\Messenger\Traits\Messagable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use IsAuditable;

    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use HasRoles;
    use Messagable;

    protected static function booted()
    {
        static::saved(function ($user) {
            // is_admin 19 is Patient. Any other category is considered staff.
            if ($user->is_admin && $user->is_admin != 19 && !$user->staff_profile()->exists()) {
                try {
                    $staffData = ['user_id' => $user->id, 'status' => 1];
                    // employment_status exists in newer schema versions
                    $columns = \Illuminate\Support\Facades\Schema::getColumnListing('staff');
                    if (in_array('employment_status', $columns)) {
                        $staffData['employment_status'] = 'active';
                    }
                    $user->staff_profile()->create($staffData);
                } catch (\Exception $e) {
                    // Silently ignore staff profile creation failures during testing
                    // when schema versions differ
                    \Illuminate\Support\Facades\Log::debug('Staff profile auto-create skipped: ' . $e->getMessage());
                }
            }
        });
    }

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
        'is_locum',
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
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'name',
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
        'is_locum' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(UserCategory::class, 'is_admin', 'id');
    }

    public function staff_profile()
    {
        return $this->hasOne(Staff::class, 'user_id', 'id')->withDefault([
            'status' => 1,
            'employment_status' => 'active',
            'employment_type' => 'locum',
            'is_unit_head' => false,
            'is_dept_head' => false,
            'can_see_clinic_queues' => [],
        ]);
    }

    /**
     * Alias for staff_profile() - used by HR/ESS modules
     */
    public function staff()
    {
        return $this->staff_profile();
    }

    /**
     * Check if user is a leadership staff (unit head or dept head)
     */
    public function isUnitOrDeptHead(): bool
    {
        return (bool) ($this->staff_profile && ($this->staff_profile->is_unit_head || $this->staff_profile->is_dept_head));
    }

    public function patient_profile()
    {
        return $this->hasOne(Patient::class, 'user_id', 'id');
    }

    public function conversations()
    {
        return $this->belongsToMany(ChatConversation::class, 'chat_participants', 'user_id', 'conversation_id');
    }

    /**
     * Get all staff bills associated with this user.
     */
    public function staffBills()
    {
        return $this->hasMany(StaffBill::class, 'staff_user_id');
    }

    /**
     * Locum professional profile (licensing, regulatory council, cadre).
     */
    public function locum_profile()
    {
        return $this->hasOne(LocumProfile::class, 'user_id', 'id');
    }

    /**
     * All shift-bounded passes issued to this practitioner.
     */
    public function locum_passes()
    {
        return $this->hasMany(LocumPass::class, 'user_id', 'id');
    }

    /**
     * The most recent shift pass.
     */
    public function latest_locum_pass()
    {
        return $this->hasOne(LocumPass::class, 'user_id', 'id')->latestOfMany();
    }

    /**
     * The currently active shift pass.
     */
    public function active_locum_pass()
    {
        return $this->hasOne(LocumPass::class, 'user_id', 'id')
            ->whereNull('revoked_at')
            ->where('starts_at', '<=', now())
            ->where('expires_at', '>=', now())
            ->latestOfMany();
    }

    /**
     * Check if user is registered as a locum practitioner.
     */
    public function isLocum(): bool
    {
        return (bool) ($this->is_locum || $this->locum_profile()->exists());
    }

    /**
     * Check if this locum currently holds an active, unexpired shift pass.
     */
    public function hasActiveLocumPass(): bool
    {
        return $this->active_locum_pass()->exists();
    }
}
