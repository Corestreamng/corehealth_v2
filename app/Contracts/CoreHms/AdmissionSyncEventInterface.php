<?php

namespace App\Contracts\CoreHms;

/**
 * Interface AdmissionSyncEventInterface
 *
 * Contract for inpatient bed admissions, ward occupancy, and discharges.
 */
interface AdmissionSyncEventInterface extends CoreHmsSyncEventInterface
{
    public function getPatientId(): int;

    public function getBedId(): ?int;

    public function getBedName(): ?string;

    public function getWardId(): ?int;

    public function getWardName(): ?string;

    public function getAdmissionStatus(): string; // 'admitted', 'discharged', 'transferred'

    public function getDischargeDate(): ?string;

    public function getCoverageMode(): string;

    public function getAmount(): float;

    public function getStatus(): string;
}
