<?php

namespace App\Contracts\CoreHms;

/**
 * Interface HeartbeatSyncEventInterface
 *
 * Contract for periodic health check, uptime, and version telemetry.
 */
interface HeartbeatSyncEventInterface extends CoreHmsSyncEventInterface
{
    public function getCoreHealthVersion(): string;

    public function getPhpVersion(): string;

    public function getDatabaseSizeMb(): ?float;

    public function getPendingQueueJobs(): int;

    public function getUptimeSeconds(): ?int;
}
