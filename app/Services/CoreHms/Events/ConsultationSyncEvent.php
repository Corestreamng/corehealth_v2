<?php

namespace App\Services\CoreHms\Events;

use App\Contracts\CoreHms\ConsultationSyncEventInterface;

class ConsultationSyncEvent extends BaseCoreHmsEvent implements ConsultationSyncEventInterface
{
    protected int $patientId;

    protected ?int $doctorId;

    protected string $clinicCategory;

    protected ?string $chiefComplaint;

    protected ?string $diagnosis;

    protected ?string $coverageMode;

    protected float $amount;

    protected string $status;

    public function __construct(
        $sourceId,
        int $patientId,
        string $clinicCategory = 'General Practice',
        ?string $chiefComplaint = null,
        ?string $diagnosis = null,
        ?int $doctorId = null,
        ?string $coverageMode = null,
        float $amount = 0.00,
        string $status = 'completed',
        ?int $facilityId = null,
        ?string $eventDate = null
    ) {
        parent::__construct('consultation', $sourceId, $facilityId, $eventDate);
        $this->patientId = $patientId;
        $this->clinicCategory = $clinicCategory;
        $this->chiefComplaint = $chiefComplaint;
        $this->diagnosis = $diagnosis;
        $this->doctorId = $doctorId;
        $this->coverageMode = $coverageMode;
        $this->amount = $amount;
        $this->status = $status;
    }

    public function getPatientId(): int
    {
        return $this->patientId;
    }

    public function getDoctorId(): ?int
    {
        return $this->doctorId;
    }

    public function getClinicCategory(): string
    {
        return $this->clinicCategory;
    }

    public function getChiefComplaint(): ?string
    {
        return $this->chiefComplaint;
    }

    public function getDiagnosis(): ?string
    {
        return $this->diagnosis;
    }

    public function getCoverageMode(): ?string
    {
        return $this->coverageMode;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function toPayload(): array
    {
        return [
            'category' => $this->clinicCategory,
            'health_case' => $this->chiefComplaint ?: $this->diagnosis,
            'diagnosis' => $this->diagnosis,
            'patient_id' => $this->patientId,
            'doctor_id' => $this->doctorId,
            'coverage_mode' => $this->coverageMode,
            'amount' => $this->amount,
            'status' => $this->status,
        ];
    }
}
