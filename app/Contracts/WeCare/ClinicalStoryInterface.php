<?php

namespace App\Contracts\WeCare;

/**
 * Interface ClinicalStoryInterface
 *
 * Enforces contract parity for the complete longitudinal EHR story synchronized
 * between CoreHealth v2 and WeCare247.
 */
interface ClinicalStoryInterface
{
    /**
     * Get patient demographic and sync metadata.
     */
    public function getPatient(): array;

    /**
     * Get aggregated counts of each clinical domain.
     */
    public function getSummary(): array;

    /**
     * Get doctor encounters, consultations, diagnoses, and notes.
     */
    public function getEncounters(): array;

    /**
     * Get recorded vital signs and observation parameters.
     */
    public function getVitals(): array;

    /**
     * Get nursing notes and shift progress charts.
     */
    public function getNursingNotes(): array;

    /**
     * Get laboratory investigations, status, and verified results.
     */
    public function getLabs(): array;

    /**
     * Get imaging and radiology requests and findings.
     */
    public function getImaging(): array;

    /**
     * Get prescriptions and pharmacy dispensing records.
     */
    public function getPrescriptions(): array;

    /**
     * Get clinical procedures and surgical operation notes.
     */
    public function getProcedures(): array;

    /**
     * Get ward admissions and inpatient bed records.
     */
    public function getAdmissions(): array;

    /**
     * Get specialist and referral consultations.
     */
    public function getReferrals(): array;

    /**
     * Get medication administration records.
     */
    public function getMedAdmin(): array;

    /**
     * Get the unified chronological timeline stream.
     */
    public function getTimeline(): array;

    /**
     * Convert to the canonical JSON response payload.
     */
    public function toPayload(): array;
}
