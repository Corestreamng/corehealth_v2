<?php

namespace App\Enums\WeCare;

/**
 * Event types for items in the WeCare247 ↔ CoreHealth unified clinical timeline.
 */
enum TimelineType: string
{
    case ENCOUNTER = 'encounter';
    case VITAL = 'vital';
    case NURSING_NOTE = 'nursing_note';
    case LAB = 'lab';
    case IMAGING = 'imaging';
    case PRESCRIPTION = 'prescription';
    case PROCEDURE = 'procedure';
    case ADMISSION = 'admission';
    case REFERRAL = 'referral';
    case MED_ADMIN = 'med_admin';

    public function label(): string
    {
        return match ($this) {
            self::ENCOUNTER => 'Doctor Consultation',
            self::VITAL => 'Vital Signs',
            self::NURSING_NOTE => 'Nursing Note',
            self::LAB => 'Laboratory Test',
            self::IMAGING => 'Imaging / Radiology',
            self::PRESCRIPTION => 'Prescription',
            self::PROCEDURE => 'Procedure / Surgery',
            self::ADMISSION => 'Ward Admission',
            self::REFERRAL => 'Specialist Referral',
            self::MED_ADMIN => 'Medication Administration',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::ENCOUNTER => 'blue',
            self::VITAL => 'rose',
            self::NURSING_NOTE => 'emerald',
            self::LAB => 'cyan',
            self::IMAGING => 'purple',
            self::PRESCRIPTION => 'amber',
            self::PROCEDURE => 'indigo',
            self::ADMISSION => 'orange',
            self::REFERRAL => 'teal',
            self::MED_ADMIN => 'sky',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ENCOUNTER => 'stethoscope',
            self::VITAL => 'heart-pulse',
            self::NURSING_NOTE => 'clipboard-check',
            self::LAB => 'flask',
            self::IMAGING => 'scan',
            self::PRESCRIPTION => 'pill',
            self::PROCEDURE => 'scissors',
            self::ADMISSION => 'bed',
            self::REFERRAL => 'user-plus',
            self::MED_ADMIN => 'needle',
        };
    }
}
