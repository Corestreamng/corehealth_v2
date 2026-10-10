<?php

namespace App\Services\CoreHms\Events;

use App\Contracts\CoreHms\CoreHmsSyncEventInterface;

abstract class BaseCoreHmsEvent implements CoreHmsSyncEventInterface
{
    protected string $eventType;

    protected string $sourceId;

    protected int $facilityId;

    protected string $eventDate;

    public function __construct(string $eventType, $sourceId, ?int $facilityId = null, ?string $eventDate = null)
    {
        $this->eventType = $eventType;
        $this->sourceId = (string)$sourceId;
        $this->facilityId = $facilityId ?? (int)appsettings('client_id', 4);
        $this->eventDate = $eventDate ?? now()->toDateTimeString();
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function getSourceId(): string
    {
        return $this->sourceId;
    }

    public function getFacilityId(): int
    {
        return $this->facilityId;
    }

    public function getEventKey(): string
    {
        return sha1($this->facilityId . ':' . $this->eventType . ':' . $this->sourceId);
    }

    public function getEventDate(): string
    {
        return $this->eventDate;
    }

    abstract public function toPayload(): array;
}
