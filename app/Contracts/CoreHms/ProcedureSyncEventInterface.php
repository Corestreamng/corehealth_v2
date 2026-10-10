<?php

namespace App\Contracts\CoreHms;

/**
 * Interface ProcedureSyncEventInterface
 *
 * Contract for surgical, theater, and minor clinical procedure outcomes.
 */
interface ProcedureSyncEventInterface extends CoreHmsSyncEventInterface
{
    public function getPatientId(): int;

    public function getProcedureName(): string;

    public function getTheater(): ?string;

    public function getOutcome(): string; // 'successful', 'complications', 'aborted', 'converted'

    public function getOutcomeNotes(): ?string;

    public function getNhmisOutcome(): ?string;

    public function getNhmisOutcomeRaw(): ?string;

    public function getCoverageMode(): string;

    public function getAmount(): float;

    public function getSurgeonId(): ?int;

    public function getStatus(): string; // 'completed'
}
