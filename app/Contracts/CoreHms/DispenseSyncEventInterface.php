<?php

namespace App\Contracts\CoreHms;

/**
 * Interface DispenseSyncEventInterface
 *
 * Contract for pharmacy drug dispensation and POSR fulfillment.
 */
interface DispenseSyncEventInterface extends CoreHmsSyncEventInterface
{
    public function getPatientId(): ?int;

    public function getProductId(): ?int;

    public function getDrugName(): string;

    public function getStoreId(): ?int;

    public function getStoreName(): ?string;

    public function getBatchNumber(): ?string;

    public function getQuantity(): float;

    public function getTotalAmount(): float;

    public function getPayableAmount(): float;

    public function getCoverageMode(): string; // 'cash', 'hmo'

    public function getHmoId(): ?int;

    public function getStatus(): string; // 'dispensed'
}
