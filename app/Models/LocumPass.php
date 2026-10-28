<?php

namespace App\Models;

use App\Traits\IsAuditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Locum Shift Pass Model
 *
 * Implements shift-bounded, time-limited credentials for locum practitioners.
 * Guarantees zero shared credentials, non-repudiation, and automatic TTL session revocation.
 */
class LocumPass extends Model implements Auditable
{
    use HasFactory;
    use IsAuditable;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'locum_passes';

    protected $fillable = [
        'user_id',
        'pin_display',
        'passcode_hash',
        'shift_type',
        'starts_at',
        'expires_at',
        'allowed_clinic_ids',
        'allowed_department_ids',
        'revoked_at',
        'revoked_by',
        'revocation_reason',
        'created_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'allowed_clinic_ids' => 'array',
        'allowed_department_ids' => 'array',
    ];

    /**
     * Associated locum user identity.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Associated locum professional profile.
     */
    public function locum_profile(): BelongsTo
    {
        return $this->belongsTo(LocumProfile::class, 'user_id', 'user_id');
    }

    /**
     * Admin/HOD who issued this pass.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    /**
     * Admin/HOD who revoked this pass, if revoked.
     */
    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by', 'id');
    }

    /**
     * Scope for passes that are currently valid, started, and unexpired.
     */
    public function scopeActive(Builder $query): Builder
    {
        $now = now();

        return $query->whereNull('revoked_at')
            ->where('starts_at', '<=', $now)
            ->where('expires_at', '>=', $now);
    }

    /**
     * Scope for expired passes.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')
            ->where('expires_at', '<', now());
    }

    /**
     * Scope for explicitly revoked passes.
     */
    public function scopeRevoked(Builder $query): Builder
    {
        return $query->whereNotNull('revoked_at');
    }

    /**
     * Check if pass is currently active.
     */
    public function isActive(): bool
    {
        $now = now();

        return is_null($this->revoked_at)
            && $this->starts_at <= $now
            && $this->expires_at >= $now;
    }

    /**
     * Check if pass has expired.
     */
    public function isExpired(): bool
    {
        return is_null($this->revoked_at) && $this->expires_at < now();
    }

    /**
     * Check if pass was manually revoked.
     */
    public function isRevoked(): bool
    {
        return !is_null($this->revoked_at);
    }

    /**
     * Get a human-readable status badge.
     */
    public function getStatusBadgeAttribute(): string
    {
        if ($this->isRevoked()) {
            return '<span class="badge bg-danger text-white"><i class="mdi mdi-cancel me-1"></i> Revoked</span>';
        }

        if ($this->isExpired()) {
            return '<span class="badge bg-secondary text-white"><i class="mdi mdi-clock-alert me-1"></i> Expired</span>';
        }

        if ($this->starts_at > now()) {
            return '<span class="badge bg-info text-dark"><i class="mdi mdi-calendar-clock me-1"></i> Scheduled</span>';
        }

        return '<span class="badge bg-success text-white"><i class="mdi mdi-check-circle me-1"></i> Active</span>';
    }

    /**
     * Human readable remaining time string.
     */
    public function getTimeRemainingAttribute(): string
    {
        if ($this->isRevoked()) {
            return 'Revoked ' . ($this->revoked_at ? $this->revoked_at->diffForHumans() : '');
        }

        if ($this->isExpired()) {
            return 'Expired ' . $this->expires_at->diffForHumans();
        }

        $now = now();
        if ($this->starts_at > $now) {
            return 'Starts ' . $this->starts_at->diffForHumans();
        }

        $diff = $now->diff($this->expires_at);
        $hours = $diff->h + ($diff->days * 24);
        $mins = $diff->i;

        return "{$hours}h {$mins}m remaining";
    }

    /**
     * Revoke this pass immediately.
     */
    public function revoke(?int $revokedById = null, ?string $reason = null): bool
    {
        $this->revoked_at = now();
        $this->revoked_by = $revokedById ?: auth()->id();
        $this->revocation_reason = $reason;

        return $this->save();
    }

    /**
     * Extend this pass by specified number of minutes.
     */
    public function extend(int $additionalMinutes): bool
    {
        $base = $this->isExpired() ? now() : $this->expires_at;
        $this->expires_at = $base->copy()->addMinutes($additionalMinutes);
        $this->revoked_at = null;
        $this->revoked_by = null;
        $this->revocation_reason = null;

        return $this->save();
    }
}
