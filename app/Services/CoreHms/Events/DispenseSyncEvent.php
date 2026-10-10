<?php

namespace App\Services\CoreHms\Events;

use App\Contracts\CoreHms\DispenseSyncEventInterface;

class DispenseSyncEvent extends BaseCoreHmsEvent implements DispenseSyncEventInterface
{
    protected ?int $patientId;

    protected ?int $productId;

    protected string $drugName;

    protected ?int $storeId;

    protected ?string $storeName;

    protected ?string $batchNumber;

    protected float $quantity;

    protected float $totalAmount;

    protected float $payableAmount;

    protected string $coverageMode;

    protected ?int $hmoId;

    protected string $status;

    public function __construct(
        $sourceId,
        string $drugName,
        ?int $patientId = null,
        ?int $productId = null,
        ?int $storeId = null,
        ?string $storeName = null,
        ?string $batchNumber = null,
        float $quantity = 1.0,
        float $totalAmount = 0.00,
        float $payableAmount = 0.00,
        string $coverageMode = 'cash',
        ?int $hmoId = null,
        string $status = 'dispensed',
        ?int $facilityId = null,
        ?string $eventDate = null
    ) {
        parent::__construct('dispense', $sourceId, $facilityId, $eventDate);
        $this->drugName = $drugName;
        $this->patientId = $patientId;
        $this->productId = $productId;
        $this->storeId = $storeId;
        $this->storeName = $storeName;
        $this->batchNumber = $batchNumber;
        $this->quantity = $quantity;
        $this->totalAmount = $totalAmount;
        $this->payableAmount = $payableAmount;
        $this->coverageMode = $coverageMode;
        $this->hmoId = $hmoId;
        $this->status = $status;
    }

    public function getPatientId(): ?int
    {
        return $this->patientId;
    }

    public function getProductId(): ?int
    {
        return $this->productId;
    }

    public function getDrugName(): string
    {
        return $this->drugName;
    }

    public function getStoreId(): ?int
    {
        return $this->storeId;
    }

    public function getStoreName(): ?string
    {
        return $this->storeName;
    }

    public function getBatchNumber(): ?string
    {
        return $this->batchNumber;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function getTotalAmount(): float
    {
        return $this->totalAmount;
    }

    public function getPayableAmount(): float
    {
        return $this->payableAmount;
    }

    public function getCoverageMode(): string
    {
        return $this->coverageMode;
    }

    public function getHmoId(): ?int
    {
        return $this->hmoId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function toPayload(): array
    {
        return [
            'drug_name' => $this->drugName,
            'patient_id' => $this->patientId,
            'product_id' => $this->productId,
            'store_id' => $this->storeId,
            'store_name' => $this->storeName,
            'batch_no' => $this->batchNumber,
            'qty' => $this->quantity,
            'amount' => $this->totalAmount,
            'payable_amount' => $this->payableAmount,
            'coverage_mode' => $this->coverageMode,
            'hmo_id' => $this->hmoId,
            'status' => $this->status,
        ];
    }
}
