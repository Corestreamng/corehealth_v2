<?php

namespace App\Enums\WeCare;

/**
 * Laboratory service request status mapping for WeCare sync.
 */
enum LabStatus: int
{
    case DISMISSED = 0;
    case UNBILLED = 1;
    case BILLED_PENDING = 2;
    case SAMPLE_TAKEN = 3;
    case COMPLETED = 4;
    case PENDING_APPROVAL = 5;
    case REJECTED = 6;

    public function label(): string
    {
        return match ($this) {
            self::DISMISSED => 'Dismissed',
            self::UNBILLED => 'Unbilled',
            self::BILLED_PENDING => 'Billed / Pending Sample',
            self::SAMPLE_TAKEN => 'Sample Taken',
            self::COMPLETED => 'Completed / Verified',
            self::PENDING_APPROVAL => 'Pending Approval',
            self::REJECTED => 'Rejected',
        };
    }

    public static function fromCode(?int $code): string
    {
        if ($code === null) {
            return 'Unknown';
        }
        $case = self::tryFrom($code);

        return $case ? $case->label() : 'Unknown';
    }
}
