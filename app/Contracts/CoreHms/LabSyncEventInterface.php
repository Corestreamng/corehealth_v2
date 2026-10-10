<?php

namespace App\Contracts\CoreHms;

/**
 * Interface LabSyncEventInterface
 *
 * Contract for diagnostic laboratory tests and NHMIS auto-sensed outcomes.
 */
interface LabSyncEventInterface extends CoreHmsSyncEventInterface
{
    public function getPatientId(): int;

    public function getServiceId(): ?int;

    public function getServiceName(): string;

    public function getSampleType(): ?string;

    public function getNhmisOutcome(): ?string; // 'positive', 'negative', 'reactive', 'non_reactive', etc.

    public function getNhmisOutcomeRaw(): ?string;

    public function getCoverageMode(): string;

    public function getAmount(): float;

    public function getResultBy(): ?int;

    public function getApprovedBy(): ?int;

    public function getStatus(): string; // 'completed', 'approved'
}
