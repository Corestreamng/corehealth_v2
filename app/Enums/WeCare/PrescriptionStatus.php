<?php

namespace App\Enums\WeCare;

/**
 * Product / Prescription status mapping for WeCare sync.
 */
enum PrescriptionStatus: int
{
    case DISMISSED = 0;
    case UNBILLED = 1;
    case BILLED = 2;
    case DISPENSED = 3;

    public function label(): string
    {
        return match ($this) {
            self::DISMISSED => 'Dismissed',
            self::UNBILLED => 'Unbilled',
            self::BILLED => 'Billed / Pending Dispensing',
            self::DISPENSED => 'Dispensed',
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
