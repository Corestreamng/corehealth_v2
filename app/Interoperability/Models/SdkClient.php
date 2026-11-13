<?php

namespace App\Interoperability\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class SdkClient extends Model
{
    use HasFactory;

    protected $table = 'sdk_clients';

    protected $fillable = [
        'client_id',
        'client_secret',
        'name',
        'organization',
        'allowed_scopes',
        'rate_limit_rpm',
        'is_active',
    ];

    protected $casts = [
        'allowed_scopes' => 'array',
        'rate_limit_rpm' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Set the client secret attribute with automatic bcrypt hashing if not already hashed.
     */
    public function setClientSecretAttribute($value): void
    {
        $this->attributes['client_secret'] = (str_starts_with($value, '$2y$') || str_starts_with($value, '$2a$'))
            ? $value
            : Hash::make($value);
    }

    /**
     * Verify a plaintext secret against stored hash.
     */
    public function verifySecret(string $plainSecret): bool
    {
        return Hash::check($plainSecret, $this->client_secret);
    }

    /**
     * Check if client has permission for a specific scope.
     */
    public function hasScope(string $scope): bool
    {
        $allowed = $this->allowed_scopes ?? [];

        return in_array('*', $allowed, true) || in_array($scope, $allowed, true);
    }
}
