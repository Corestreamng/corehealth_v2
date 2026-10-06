<?php

namespace App\Services\Nhmis;

use App\Services\Nhmis\Schemas\Nhmis2019Schema;

class NhmisFormRegistry
{
    /**
     * Registered form schemas keyed by version.
     */
    protected static array $schemas = [];

    /**
     * Initialize standard schemas
     */
    protected static function boot(): void
    {
        if (empty(self::$schemas)) {
            self::register(new Nhmis2019Schema());
        }
    }

    /**
     * Register a new or revised form schema
     */
    public static function register($schema): void
    {
        self::$schemas[$schema->getVersion()] = $schema;
    }

    /**
     * Get a schema instance by version (defaults to v2019)
     */
    public static function getSchema(string $version = 'v2019')
    {
        self::boot();

        return self::$schemas[$version] ?? self::$schemas['v2019'] ?? new Nhmis2019Schema();
    }

    /**
     * List all available form versions for UI selection
     */
    public static function getAvailableVersions(): array
    {
        self::boot();

        $list = [];
        foreach (self::$schemas as $version => $schema) {
            $list[$version] = [
                'version' => $schema->getVersion(),
                'title' => $schema->getTitle(),
                'code' => $schema->getCode(),
                'description' => $schema->getDescription(),
            ];
        }

        return $list;
    }
}
