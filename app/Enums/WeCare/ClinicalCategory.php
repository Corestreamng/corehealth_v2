<?php

namespace App\Enums\WeCare;

/**
 * Categories supported by the WeCare247 ↔ CoreHealth clinical story sync.
 */
enum ClinicalCategory: string
{
    case ALL = 'all';
    case ENCOUNTERS = 'encounters';
    case VITALS = 'vitals';
    case NURSING_NOTES = 'nursing_notes';
    case LABS = 'labs';
    case IMAGING = 'imaging';
    case PRESCRIPTIONS = 'prescriptions';
    case PROCEDURES = 'procedures';
    case ADMISSIONS = 'admissions';
    case REFERRALS = 'referrals';
    case MED_ADMIN = 'med_admin';

    public function label(): string
    {
        return match ($this) {
            self::ALL => 'All Clinical Records',
            self::ENCOUNTERS => 'Consultations & Diagnoses',
            self::VITALS => 'Vital Signs & Observations',
            self::NURSING_NOTES => 'Nursing Notes',
            self::LABS => 'Laboratory Investigations',
            self::IMAGING => 'Imaging & Radiology',
            self::PRESCRIPTIONS => 'Prescriptions & Pharmacy',
            self::PROCEDURES => 'Procedures & Surgeries',
            self::ADMISSIONS => 'Ward Admissions',
            self::REFERRALS => 'Specialist Referrals',
            self::MED_ADMIN => 'Medication Administration',
        };
    }
}
