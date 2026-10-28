<?php

namespace App\Models;

use App\Traits\IsAuditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Locum Profile Model
 *
 * Stores regulatory licensing and professional metadata for locum/temporary
 * medical doctors, visiting radiologists, locum lab scientists, and relief nurses.
 */
class LocumProfile extends Model implements Auditable
{
    use HasFactory;
    use IsAuditable;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'locum_profiles';

    protected $fillable = [
        'user_id',
        'regulatory_body',
        'license_number',
        'primary_specialty',
        'cadre_template',
        'created_by',
    ];

    /**
     * The User identity representing this locum practitioner.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * The administrative staff or Medical Director who provisioned this profile.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    /**
     * All shift-bounded access passes issued to this locum clinician.
     */
    public function passes(): HasMany
    {
        return $this->hasMany(LocumPass::class, 'user_id', 'user_id');
    }

    /**
     * The most recent shift pass.
     */
    public function latestPass(): HasOne
    {
        return $this->hasOne(LocumPass::class, 'user_id', 'user_id')->latestOfMany();
    }

    /**
     * The currently active shift pass, if any.
     */
    public function activePass(): HasOne
    {
        return $this->hasOne(LocumPass::class, 'user_id', 'user_id')
            ->whereNull('revoked_at')
            ->where('starts_at', '<=', now())
            ->where('expires_at', '>=', now())
            ->latestOfMany();
    }

    /**
     * Check if this locum currently holds an active, unexpired shift pass.
     */
    public function hasActivePass(): bool
    {
        return $this->activePass()->exists();
    }
}
