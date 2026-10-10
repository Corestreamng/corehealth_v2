<?php

namespace App\Contracts\CoreHms;

/**
 * Interface StaffSyncEventInterface
 *
 * Contract for staff / clinical personnel synchronization.
 */
interface StaffSyncEventInterface extends CoreHmsSyncEventInterface
{
    public function getStaffName(): string;

    public function getStaffType(): string; // 'Doctors', 'Nurse', 'Pharmacist', 'Lab Scientist', 'Staffs'

    public function getSpecialization(): ?string;

    public function getGender(): ?string;

    public function getEmail(): ?string;

    public function getPhoneNumber(): ?string;

    public function getStatus(): string;
}
