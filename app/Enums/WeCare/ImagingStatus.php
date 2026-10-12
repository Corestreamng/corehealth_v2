<?php

namespace App\Enums\WeCare;

/**
 * Imaging / Radiology request status mapping for WeCare sync.
 */
enum ImagingStatus: int
{
    case DISMISSED = 0;
    case UNBILLED = 1;
    case BILLED_PENDING = 2;
    case IN_PROGRESS = 3;
    case COMPLETED = 4;
    case PENDING_APPROVAL = 5;
    case REJECTED = 6;

    public function label(): string
    {
        return match ($this) {
            self::DISMISSED => 'Dismissed',
            self::UNBILLED => 'Unbilled',
            self::BILLED_PENDING => 'Billed / Pending Scan',
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed / Reported',
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
