<?php

namespace App\Services\CoreHms\Events;

use App\Contracts\CoreHms\PatientSyncEventInterface;

class PatientSyncEvent extends BaseCoreHmsEvent implements PatientSyncEventInterface
{
    protected string $patientName;

    protected ?string $gender;

    protected ?string $dob;

    protected ?string $phoneNo;

    protected ?string $fileNo;

    protected ?int $hmoId;

    protected ?string $hmoName;

    protected string $status;

    public function __construct(
        $sourceId,
        string $patientName,
        ?string $gender = null,
        ?string $dob = null,
        ?string $phoneNo = null,
        ?string $fileNo = null,
        ?int $hmoId = null,
        ?string $hmoName = null,
        string $status = 'Active',
        ?int $facilityId = null,
        ?string $eventDate = null
    ) {
        parent::__construct('patient', $sourceId, $facilityId, $eventDate);
        $this->patientName = $patientName;
        $this->gender = $gender;
        $this->dob = $dob;
        $this->phoneNo = $phoneNo;
        $this->fileNo = $fileNo;
        $this->hmoId = $hmoId;
        $this->hmoName = $hmoName;
        $this->status = $status;
    }

    public function getPatientName(): string
    {
        return $this->patientName;
    }

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function getDateOfBirth(): ?string
    {
        return $this->dob;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNo;
    }

    public function getFileNumber(): ?string
    {
        return $this->fileNo;
    }

    public function getHmoId(): ?int
    {
        return $this->hmoId;
    }

    public function getHmoName(): ?string
    {
        return $this->hmoName;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function toPayload(): array
    {
        return [
            'name' => $this->patientName,
            'gender' => $this->gender,
            'dob' => $this->dob,
            'phone_no' => $this->phoneNo,
            'file_no' => $this->fileNo,
            'hmo_id' => $this->hmoId,
            'hmo_name' => $this->hmoName,
            'status' => $this->status,
        ];
    }
}
