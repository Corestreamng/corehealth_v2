<?php

namespace App\Services\CoreHms\Events;

use App\Contracts\CoreHms\ProcedureSyncEventInterface;

class ProcedureSyncEvent extends BaseCoreHmsEvent implements ProcedureSyncEventInterface
{
    protected int $patientId;

    protected string $procedureName;

    protected ?string $theater;

    protected string $outcome;

    protected ?string $outcomeNotes;

    protected ?string $nhmisOutcome;

    protected ?string $nhmisOutcomeRaw;

    protected string $coverageMode;

    protected float $amount;

    protected ?int $surgeonId;

    protected string $status;

    public function __construct(
        $sourceId,
        int $patientId,
        string $procedureName,
        string $outcome = 'successful',
        ?string $theater = 'Minor Theater',
        ?string $outcomeNotes = null,
        ?string $nhmisOutcome = null,
        ?string $nhmisOutcomeRaw = null,
        string $coverageMode = 'cash',
        float $amount = 0.00,
        ?int $surgeonId = null,
        string $status = 'completed',
        ?int $facilityId = null,
        ?string $eventDate = null
    ) {
        parent::__construct('procedure', $sourceId, $facilityId, $eventDate);
        $this->patientId = $patientId;
        $this->procedureName = $procedureName;
        $this->outcome = $outcome;
        $this->theater = $theater;
        $this->outcomeNotes = $outcomeNotes;
        $this->nhmisOutcome = $nhmisOutcome;
        $this->nhmisOutcomeRaw = $nhmisOutcomeRaw;
        $this->coverageMode = $coverageMode;
        $this->amount = $amount;
        $this->surgeonId = $surgeonId;
        $this->status = $status;
    }

    public function getPatientId(): int
    {
        return $this->patientId;
    }

    public function getProcedureName(): string
    {
        return $this->procedureName;
    }

    public function getTheater(): ?string
    {
        return $this->theater;
    }

    public function getOutcome(): string
    {
        return $this->outcome;
    }

    public function getOutcomeNotes(): ?string
    {
        return $this->outcomeNotes;
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

    public function getSurgeonId(): ?int
    {
        return $this->surgeonId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function toPayload(): array
    {
        return [
            'patient_id' => $this->patientId,
            'procedure_name' => $this->procedureName,
            'theater' => $this->theater,
            'outcome' => $this->outcome,
            'outcome_notes' => $this->outcomeNotes,
            'nhmis_outcome' => $this->nhmisOutcome,
            'nhmis_outcome_raw' => $this->nhmisOutcomeRaw,
            'coverage_mode' => $this->coverageMode,
            'amount' => $this->amount,
            'surgeon_id' => $this->surgeonId,
            'status' => $this->status,
        ];
    }
}
