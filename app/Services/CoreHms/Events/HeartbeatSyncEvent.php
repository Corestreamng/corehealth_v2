<?php

namespace App\Services\CoreHms\Events;

use App\Contracts\CoreHms\HeartbeatSyncEventInterface;

class HeartbeatSyncEvent extends BaseCoreHmsEvent implements HeartbeatSyncEventInterface
{
    protected string $appVersion;

    protected string $phpVersion;

    protected ?float $dbSizeMb;

    protected int $pendingJobs;

    protected ?int $uptimeSeconds;

    public function __construct(
        $sourceId = 'hb_ping',
        string $appVersion = '2.5.0',
        ?string $phpVersion = null,
        ?float $dbSizeMb = null,
        int $pendingJobs = 0,
        ?int $uptimeSeconds = null,
        ?int $facilityId = null,
        ?string $eventDate = null
    ) {
        parent::__construct('heartbeat', $sourceId, $facilityId, $eventDate);
        $this->appVersion = $appVersion;
        $this->phpVersion = $phpVersion ?? PHP_VERSION;
        $this->dbSizeMb = $dbSizeMb;
        $this->pendingJobs = $pendingJobs;
        $this->uptimeSeconds = $uptimeSeconds;
    }

    public function getCoreHealthVersion(): string
    {
        return $this->appVersion;
    }

    public function getPhpVersion(): string
    {
        return $this->phpVersion;
    }

    public function getDatabaseSizeMb(): ?float
    {
        return $this->dbSizeMb;
    }

    public function getPendingQueueJobs(): int
    {
        return $this->pendingJobs;
    }

    public function getUptimeSeconds(): ?int
    {
        return $this->uptimeSeconds;
    }

    public function toPayload(): array
    {
        return [
            'app_version' => $this->appVersion,
            'php_version' => $this->phpVersion,
            'db_size_mb' => $this->dbSizeMb,
            'pending_jobs' => $this->pendingJobs,
            'uptime_seconds' => $this->uptimeSeconds,
            'status' => 'online',
        ];
    }
}
