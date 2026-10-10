<?php

namespace App\Services\CoreHms\Events;

use App\Contracts\CoreHms\StaffSyncEventInterface;

class StaffSyncEvent extends BaseCoreHmsEvent implements StaffSyncEventInterface
{
    protected string $staffName;

    protected string $staffType;

    protected ?string $specialization;

    protected ?string $gender;

    protected ?string $email;

    protected ?string $phone;

    protected string $status;

    public function __construct(
        $sourceId,
        string $staffName,
        string $staffType = 'Staffs',
        ?string $specialization = null,
        ?string $gender = null,
        ?string $email = null,
        ?string $phone = null,
        string $status = 'Active',
        ?int $facilityId = null,
        ?string $eventDate = null
    ) {
        parent::__construct('staff', $sourceId, $facilityId, $eventDate);
        $this->staffName = $staffName;
        $this->staffType = $staffType;
        $this->specialization = $specialization;
        $this->gender = $gender;
        $this->email = $email;
        $this->phone = $phone;
        $this->status = $status;
    }

    public function getStaffName(): string
    {
        return $this->staffName;
    }

    public function getStaffType(): string
    {
        return $this->staffType;
    }

    public function getSpecialization(): ?string
    {
        return $this->specialization;
    }

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phone;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function toPayload(): array
    {
        return [
            'name' => $this->staffName,
            'type' => $this->staffType,
            'specialization' => $this->specialization,
            'gender' => $this->gender,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
        ];
    }
}
