<?php

namespace App\Interoperability\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class InteropExternalIdentifier extends Model
{
    use HasFactory;

    protected $table = 'interop_external_identifiers';

    protected $fillable = [
        'standard',
        'resource_type',
        'local_id',
        'external_id',
        'system',
    ];

    /**
     * Retrieve or dynamically generate a persistent UUID for a local resource.
     */
    public static function getOrCreateUuid(string $standard, string $resourceType, int $localId, ?string $system = null): string
    {
        $record = static::firstOrCreate(
            [
                'standard' => $standard,
                'resource_type' => $resourceType,
                'local_id' => $localId,
            ],
            [
                'external_id' => (string) Str::uuid(),
                'system' => $system ?? config('app.url') . "/api/{$standard}/{$resourceType}",
            ]
        );

        return $record->external_id;
    }

    /**
     * Resolve an external identifier (UUID or external string) back to a local database ID.
     */
    public static function resolveLocalId(string $standard, string $resourceType, string $externalId): ?int
    {
        // First check if externalId is already numeric (direct local ID fallback)
        if (is_numeric($externalId) && (int) $externalId > 0) {
            return (int) $externalId;
        }

        $record = static::where('standard', $standard)
            ->where('resource_type', $resourceType)
            ->where('external_id', $externalId)
            ->first();

        return $record ? $record->local_id : null;
    }

    /**
     * Explicitly map an incoming external ID to an existing local resource ID.
     */
    public static function linkExternalId(string $standard, string $resourceType, int $localId, string $externalId, ?string $system = null): self
    {
        return static::updateOrCreate(
            [
                'standard' => $standard,
                'resource_type' => $resourceType,
                'local_id' => $localId,
            ],
            [
                'external_id' => $externalId,
                'system' => $system ?? config('app.url') . "/api/{$standard}/{$resourceType}",
            ]
        );
    }
}
