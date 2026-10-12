<?php

namespace App\Support\WeCare;

use App\Contracts\WeCare\ClinicalStoryInterface;

/**
 * Concrete DTO implementing the WeCare ClinicalStoryInterface contract.
 */
class ClinicalStoryDto implements ClinicalStoryInterface
{
    public function __construct(
        protected array $patient,
        protected array $summary,
        protected array $encounters = [],
        protected array $vitals = [],
        protected array $nursingNotes = [],
        protected array $labs = [],
        protected array $imaging = [],
        protected array $prescriptions = [],
        protected array $procedures = [],
        protected array $admissions = [],
        protected array $referrals = [],
        protected array $medAdmin = [],
        protected array $timeline = []
    ) {
    }

    public function getPatient(): array
    {
        return $this->patient;
    }

    public function getSummary(): array
    {
        return $this->summary;
    }

    public function getEncounters(): array
    {
        return $this->encounters;
    }

    public function getVitals(): array
    {
        return $this->vitals;
    }

    public function getNursingNotes(): array
    {
        return $this->nursingNotes;
    }

    public function getLabs(): array
    {
        return $this->labs;
    }

    public function getImaging(): array
    {
        return $this->imaging;
    }

    public function getPrescriptions(): array
    {
        return $this->prescriptions;
    }

    public function getProcedures(): array
    {
        return $this->procedures;
    }

    public function getAdmissions(): array
    {
        return $this->admissions;
    }

    public function getReferrals(): array
    {
        return $this->referrals;
    }

    public function getMedAdmin(): array
    {
        return $this->medAdmin;
    }

    public function getTimeline(): array
    {
        return $this->timeline;
    }

    public function toPayload(): array
    {
        return [
            'status' => true,
            'patient' => $this->patient,
            'summary' => $this->summary,
            'encounters' => $this->encounters,
            'vitals' => $this->vitals,
            'nursing_notes' => $this->nursingNotes,
            'labs' => $this->labs,
            'imaging' => $this->imaging,
            'prescriptions' => $this->prescriptions,
            'procedures' => $this->procedures,
            'admissions' => $this->admissions,
            'referrals' => $this->referrals,
            'med_admin' => $this->medAdmin,
            'timeline' => $this->timeline,
        ];
    }
}
