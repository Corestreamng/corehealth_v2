<?php

namespace App\Contracts\CoreHms;

/**
 * Interface PatientSyncEventInterface
 *
 * Contract for patient registration and identity update synchronization.
 */
interface PatientSyncEventInterface extends CoreHmsSyncEventInterface
{
    public function getPatientName(): string;

    public function getGender(): ?string;

    public function getDateOfBirth(): ?string;

    public function getPhoneNumber(): ?string;

    public function getFileNumber(): ?string;

    public function getHmoId(): ?int;

    public function getHmoName(): ?string;

    public function getStatus(): string;
}
