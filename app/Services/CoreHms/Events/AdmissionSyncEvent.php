<?php

namespace App\Services\CoreHms\Events;

use App\Contracts\CoreHms\AdmissionSyncEventInterface;

class AdmissionSyncEvent extends BaseCoreHmsEvent implements AdmissionSyncEventInterface
{
    protected int $patientId;

    protected ?int $bedId;

    protected ?string $bedName;

    protected ?int $wardId;

    protected ?string $wardName;

    protected string $admissionStatus;

    protected ?string $dischargeDate;

    protected string $coverageMode;

    protected float $amount;

    protected string $status;

    public function __construct(
        $sourceId,
        int $patientId,
        string $admissionStatus = 'admitted',
        ?int $bedId = null,
        ?string $bedName = null,
        ?int $wardId = null,
        ?string $wardName = null,
        ?string $dischargeDate = null,
        string $coverageMode = 'cash',
        float $amount = 0.00,
        string $status = 'active',
        ?int $facilityId = null,
        ?string $eventDate = null
    ) {
        parent::__construct('admission', $sourceId, $facilityId, $eventDate);
        $this->patientId = $patientId;
        $this->admissionStatus = $admissionStatus;
        $this->bedId = $bedId;
        $this->bedName = $bedName;
        $this->wardId = $wardId;
        $this->wardName = $wardName;
        $this->dischargeDate = $dischargeDate;
        $this->coverageMode = $coverageMode;
        $this->amount = $amount;
        $this->status = $status;
    }

    public function getPatientId(): int
    {
        return $this->patientId;
    }

    public function getBedId(): ?int
    {
        return $this->bedId;
    }

    public function getBedName(): ?string
    {
        return $this->bedName;
    }

    public function getWardId(): ?int
    {
        return $this->wardId;
    }

    public function getWardName(): ?string
    {
        return $this->wardName;
    }

    public function getAdmissionStatus(): string
    {
        return $this->admissionStatus;
    }

    public function getDischargeDate(): ?string
    {
        return $this->dischargeDate;
    }

    public function getCoverageMode(): string
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
            'patient_id' => $this->patientId,
            'bed_id' => $this->bedId,
            'bed_name' => $this->bedName,
            'ward_id' => $this->wardId,
            'ward_name' => $this->wardName,
            'admission_status' => $this->admissionStatus,
            'discharge_date' => $this->dischargeDate,
            'coverage_mode' => $this->coverageMode,
            'amount' => $this->amount,
            'status' => $this->status,
        ];
    }
}
