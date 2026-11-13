<?php

namespace App\Interoperability\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SdkTokenAudit extends Model
{
    use HasFactory;

    protected $table = 'sdk_token_audits';

    protected $fillable = [
        'client_id',
        'patient_id',
        'encounter_id',
        'clinician_id',
        'token_jti',
        'scopes',
        'issued_at',
        'expires_at',
        'revoked_at',
        'ip_address',
    ];

    protected $casts = [
        'scopes' => 'array',
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function isRevoked(): bool
    {
        return !is_null($this->revoked_at);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
