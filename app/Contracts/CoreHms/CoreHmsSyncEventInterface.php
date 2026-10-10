<?php

namespace App\Contracts\CoreHms;

/**
 * Interface CoreHmsSyncEventInterface
 *
 * Base contract for all synchronization events transmitted between
 * CoreHealth hospital instances and the central CoreHMS SuperAdmin.
 */
interface CoreHmsSyncEventInterface
{
    /**
     * Get the event domain type (e.g. 'patient', 'staff', 'consultation', 'dispense', 'lab', 'procedure', 'admission', 'heartbeat').
     */
    public function getEventType(): string;

    /**
     * Get the source entity primary key / ID in CoreHealth.
     */
    public function getSourceId(): string;

    /**
     * Get the facility ID registered in CoreHMS.
     */
    public function getFacilityId(): int;

    /**
     * Get the deterministic idempotency key for deduplication.
     */
    public function getEventKey(): string;

    /**
     * Get the timestamp of the event (ISO / Y-m-d H:i:s).
     */
    public function getEventDate(): string;

    /**
     * Convert the event to the canonical payload array sent to CoreHMS.
     */
    public function toPayload(): array;
}
