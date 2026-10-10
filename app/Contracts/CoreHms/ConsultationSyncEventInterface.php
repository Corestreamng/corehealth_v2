<?php

namespace App\Contracts\CoreHms;

/**
 * Interface ConsultationSyncEventInterface
 *
 * Contract for doctor consultations, clinical encounters, and diagnoses.
 */
interface ConsultationSyncEventInterface extends CoreHmsSyncEventInterface
{
    public function getPatientId(): int;

    public function getDoctorId(): ?int;

    public function getClinicCategory(): string; // Clinic name / OPD / GOPD

    public function getChiefComplaint(): ?string; // Reason for encounter

    public function getDiagnosis(): ?string; // ICD-10 or clinical evaluation

    public function getCoverageMode(): ?string; // 'cash', 'hmo', etc.

    public function getAmount(): float;

    public function getStatus(): string; // 'completed', 'in_progress'
}
