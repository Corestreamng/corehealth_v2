<?php

namespace App\Enums\WeCare;

/**
 * Priority levels across clinical orders, procedures, and referrals.
 */
enum ClinicalPriority: string
{
    case ROUTINE = 'routine';
    case URGENT = 'urgent';
    case EMERGENCY = 'emergency';

    public function label(): string
    {
        return match ($this) {
            self::ROUTINE => 'Routine',
            self::URGENT => 'Urgent',
            self::EMERGENCY => 'Emergency',
        };
    }

    public static function fromString(?string $val): self
    {
        if (!$val) {
            return self::ROUTINE;
        }

        return self::tryFrom(strtolower(trim($val))) ?? self::ROUTINE;
    }
}
