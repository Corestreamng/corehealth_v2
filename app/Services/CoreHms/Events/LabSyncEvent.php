<?php

namespace App\Services\CoreHms\Events;

use App\Contracts\CoreHms\LabSyncEventInterface;

class LabSyncEvent extends BaseCoreHmsEvent implements LabSyncEventInterface
{
    protected int $patientId;

    protected ?int $serviceId;

    protected string $serviceName;

    protected ?string $sampleType;

    protected ?string $nhmisOutcome;

    protected ?string $nhmisOutcomeRaw;

    protected string $coverageMode;

    protected float $amount;

    protected ?int $resultBy;

    protected ?int $approvedBy;

    protected string $status;

    public function __construct(
        $sourceId,
        int $patientId,
        string $serviceName,
        ?int $serviceId = null,
        ?string $sampleType = null,
        ?string $nhmisOutcome = null,
        ?string $nhmisOutcomeRaw = null,
        string $coverageMode = 'cash',
        float $amount = 0.00,
        ?int $resultBy = null,
        ?int $approvedBy = null,
        string $status = 'completed',
        ?int $facilityId = null,
        ?string $eventDate = null
    ) {
        parent::__construct('lab', $sourceId, $facilityId, $eventDate);
        $this->patientId = $patientId;
        $this->serviceId = $serviceId;
        $this->serviceName = $serviceName;
        $this->sampleType = $sampleType;
        $this->nhmisOutcome = $nhmisOutcome;
        $this->nhmisOutcomeRaw = $nhmisOutcomeRaw;
        $this->coverageMode = $coverageMode;
        $this->amount = $amount;
        $this->resultBy = $resultBy;
        $this->approvedBy = $approvedBy;
        $this->status = $status;
    }

    public function getPatientId(): int
    {
        return $this->patientId;
    }

    public function getServiceId(): ?int
    {
        return $this->serviceId;
    }

    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    public function getSampleType(): ?string
    {
        return $this->sampleType;
    }

    public function getNhmisOutcome(): ?string
    {
        return $this->nhmisOutcome;
    }

    public function getNhmisOutcomeRaw(): ?string
    {
        return $this->nhmisOutcomeRaw;
    }

    public function getCoverageMode(): string
    {
        return $this->coverageMode;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getResultBy(): ?int
    {
        return $this->resultBy;
    }

    public function getApprovedBy(): ?int
    {
        return $this->approvedBy;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function toPayload(): array
    {
        return [
            'patient_id' => $this->patientId,
            'service_id' => $this->serviceId,
            'service_name' => $this->serviceName,
            'sample_type' => $this->sampleType,
            'nhmis_outcome' => $this->nhmisOutcome,
            'nhmis_outcome_raw' => $this->nhmisOutcomeRaw,
            'coverage_mode' => $this->coverageMode,
            'amount' => $this->amount,
            'result_by' => $this->resultBy,
            'approved_by' => $this->approvedBy,
            'status' => $this->status,
        ];
    }
}
