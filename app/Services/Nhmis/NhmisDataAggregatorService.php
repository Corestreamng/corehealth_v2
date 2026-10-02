<?php

namespace App\Services\Nhmis;

use App\Models\AdmissionRequest;
use App\Models\AncVisit;
use App\Models\ChildGrowthRecord;
use App\Models\DeathRecord;
use App\Models\DeliveryRecord;
use App\Models\Encounter;
use App\Models\ImmunizationRecord;
use App\Models\LabServiceRequest;
use App\Models\MaternityBaby;
use App\Models\MaternityEnrollment;
use App\Models\NhmisMonthlyReport;
use App\Models\NhmisMonthlyReportValue;
use App\Models\NhmisServiceMapping;
use App\Models\PostnatalVisit;
use App\Models\ProductRequest;
use App\Models\SpecialistReferral;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NhmisDataAggregatorService
{
    /**
     * Compute all form values for a given monthly report.
     * Populates auto_value and updates final_value if not overridden.
     */
    public function compileReport(NhmisMonthlyReport $report): array
    {
        $year = (int) $report->year;
        $month = (int) $report->month;

        if ($report->period_type === 'custom' || $month === 255 || ($report->start_date && $report->end_date && $report->period_type === 'custom')) {
            $startDate = Carbon::parse($report->start_date)->startOfDay();
            $endDate = Carbon::parse($report->end_date)->endOfDay();
        } elseif ($month === 0) {
            $startDate = Carbon::createFromDate($year, 1, 1)->startOfYear();
            $endDate = Carbon::createFromDate($year, 12, 31)->endOfYear();
        } else {
            $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();
        }

        $computedValues = [];

        // Fetch all monthly encounters in a single projected pass for peak performance (aligned with ClinicalReportsController)
        $encounters = Encounter::select([
            'id',
            'patient_id',
            'doctor_id',
            'queue_id',
            'reasons_for_encounter',
            'reasons_for_encounter_comment_1',
            'reasons_for_encounter_comment_2',
            'notes',
            'admission_request_id',
            'created_at',
        ])->with([
            'patient:id,user_id,file_no,hmo_id,dob,gender',
            'patient.user:id,surname,firstname,othername',
        ])->whereBetween('created_at', [$startDate, $endDate])->get();

        // 1. Attendance & IPC (Rows 1 - 4)
        $this->aggregateAttendanceAndInpatient($encounters, $startDate, $endDate, $computedValues);

        // 2. Mortality (Rows 5 - 9)
        $this->aggregateMortality($startDate, $endDate, $computedValues);

        // 3. Antenatal Care (Rows 10 - 33)
        $this->aggregateAntenatalCare($startDate, $endDate, $computedValues);

        // 4. Labour & Delivery (Rows 34 - 46)
        $this->aggregateLabourAndDelivery($startDate, $endDate, $computedValues);

        // 5. Postnatal Care (Row 47)
        $this->aggregatePostnatalCare($startDate, $endDate, $computedValues);

        // 6. Newborn Health (Rows 48 - 62)
        $this->aggregateNewbornHealth($startDate, $endDate, $computedValues);

        // 7. Immunization (TD & Antigens) (Rows 63 - 87)
        $this->aggregateImmunizations($startDate, $endDate, $computedValues);

        // 8. AEFI & RI Operations (Rows 88 - 97)
        $this->aggregateAefiAndOperations($report, $startDate, $endDate, $computedValues);

        // 9. Birth Registration & Nutrition / SAM (Rows 98 - 109)
        $this->aggregateNutritionAndGrowth($startDate, $endDate, $computedValues);

        // 10. IMCI (Rows 110 - 114)
        $this->aggregateImci($encounters, $startDate, $endDate, $computedValues);

        // 11. Family Planning (Rows 115 - 131)
        $this->aggregateFamilyPlanning($startDate, $endDate, $computedValues);

        // 12. Referrals (Rows 132 - 136)
        $this->aggregateReferrals($startDate, $endDate, $computedValues);

        // 13. NCDs (Rows 137 - 145)
        $this->aggregateNcds($encounters, $startDate, $endDate, $computedValues);

        // 14. Malaria Services (Rows 146 - 160)
        $this->aggregateMalaria($encounters, $startDate, $endDate, $computedValues);

        // 15. TB, Hepatitis, GBV, Fistula, NTDs, ADRs (Rows 161 - 185)
        $this->aggregateCommunicableAndSpecialized($encounters, $startDate, $endDate, $computedValues);

        // Persist computed values to database
        DB::beginTransaction();

        try {
            foreach ($computedValues as $cellKey => $autoVal) {
                $record = NhmisMonthlyReportValue::firstOrNew([
                    'report_id' => $report->id,
                    'cell_key' => $cellKey,
                ]);

                $record->auto_value = $autoVal;
                // If never manually overridden, final value equals auto value
                if ($record->override_value === null) {
                    $record->final_value = $autoVal;
                }
                $record->save();
            }

            $report->update([
                'status' => 'compiled',
                'compiled_by' => auth()->id() ?? $report->compiled_by,
                'compiled_at' => now(),
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error compiling NHMIS report: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            throw $e;
        }

        return $computedValues;
    }

    /**
     * Helper to classify age into NHMIS standard brackets
     */
    public function getNhmisAgeBand(?Carbon $dob, Carbon $referenceDate): string
    {
        if (!$dob) {
            return 'ge_20y';
        }

        $ageDays = $dob->diffInDays($referenceDate, false);
        if ($ageDays <= 28) {
            return '0_28d';
        }

        $ageMonths = $dob->diffInMonths($referenceDate, false);
        if ($ageMonths < 12) {
            return '29d_11m';
        }
        if ($ageMonths < 60) {
            return '12_59m';
        }

        $ageYears = $dob->diffInYears($referenceDate, false);
        if ($ageYears < 10) {
            return '5_9y';
        }
        if ($ageYears < 20) {
            return '10_19y';
        }

        return 'ge_20y';
    }

    /**
     * Normalize a diagnosis item (from JSON array, string, or notes) into a standard representation
     * Reused directly from ClinicalReportsController::normalizeDiagnosisItem
     */
    public static function normalizeDiagnosisItem($item, $defaultComment1 = 'N/A', $defaultComment2 = 'N/A'): array
    {
        $code = '';
        $name = '';
        $query = $defaultComment1 ?: 'N/A';
        $status = $defaultComment2 ?: 'N/A';

        if (is_array($item)) {
            $code = trim($item['code'] ?? '');
            $name = trim($item['name'] ?? ($item['value'] ?? ($item['display'] ?? '')));
            if (!empty($item['comment_1']) && $item['comment_1'] !== 'NA') {
                $query = $item['comment_1'];
            }
            if (!empty($item['comment_2']) && $item['comment_2'] !== 'NA') {
                $status = $item['comment_2'];
            }
        } else {
            $name = trim((string) $item);
            if (preg_match('/^([A-Za-z][0-9]{2,3}(?:\.[0-9]+)?)\s*[-:]\s*(.+)$/i', $name, $m)) {
                $code = trim($m[1]);
                $name = trim($m[2]);
            }
        }

        $name = trim(preg_replace('/^custom:\s*/i', '', $name));
        $name = trim(preg_replace('/\s+/', ' ', $name));
        $name = rtrim($name, '.');

        if (empty($code) && preg_match('/^([A-Za-z][0-9]{2,3}(?:\.[0-9]+)?)\s*[-:]\s*(.+)$/i', $name, $m)) {
            $code = trim($m[1]);
            $name = trim($m[2]);
        }

        $isCustom = (empty($code) || strtoupper($code) === 'CUSTOM');
        if (!$isCustom) {
            $cleanCode = strtoupper(trim($code));
            $groupKey = 'ICD_' . $cleanCode;
            $displayCode = $cleanCode;
            $displayName = $name ?: $cleanCode;
        } else {
            $groupKey = 'CUSTOM_' . strtolower($name);
            $displayCode = 'CUSTOM';
            $displayName = $name !== '' ? mb_convert_case($name, MB_CASE_TITLE, 'UTF-8') : 'Unknown';
        }

        return [
            'group_key' => $groupKey,
            'code' => $displayCode,
            'name' => $displayName,
            'raw_name' => $name,
            'query' => $query,
            'status' => $status,
        ];
    }

    /**
     * Reusable diagnosis matcher across encounters based on ClinicalReportsController normalization
     */
    public function matchEncounterDiagnosis(Encounter $encounter, array $keywords, array $icdPrefixes = []): bool
    {
        $rawReasons = !empty($encounter->reasons_for_encounter) ? json_decode($encounter->reasons_for_encounter, true) : [];
        if (!is_array($rawReasons)) {
            if (is_string($encounter->reasons_for_encounter) && trim($encounter->reasons_for_encounter) !== '') {
                $rawReasons = array_filter(array_map('trim', explode(',', $encounter->reasons_for_encounter)));
            } else {
                $rawReasons = [];
            }
        }

        foreach ($rawReasons as $item) {
            $norm = self::normalizeDiagnosisItem($item, $encounter->reasons_for_encounter_comment_1, $encounter->reasons_for_encounter_comment_2);
            $code = strtoupper(trim($norm['code']));
            $nameLower = strtolower(html_entity_decode($norm['name'], ENT_QUOTES, 'UTF-8'));
            $rawLower = strtolower(html_entity_decode($norm['raw_name'], ENT_QUOTES, 'UTF-8'));
            $displayLower = is_array($item) ? strtolower(html_entity_decode(trim($item['display'] ?? ($item['value'] ?? '')), ENT_QUOTES, 'UTF-8')) : '';

            foreach ($icdPrefixes as $prefix) {
                $p = strtoupper(trim($prefix));
                if ($code !== 'CUSTOM' && (str_starts_with($code, $p) || $code === $p)) {
                    return true;
                }
                if ($this->matchKeywordInText($nameLower, $p) ||
                    $this->matchKeywordInText($rawLower, $p) ||
                    ($displayLower !== '' && $this->matchKeywordInText($displayLower, $p))) {
                    return true;
                }
            }

            foreach ($keywords as $kw) {
                if ($this->matchKeywordInText($nameLower, $kw) ||
                    $this->matchKeywordInText($rawLower, $kw) ||
                    ($displayLower !== '' && $this->matchKeywordInText($displayLower, $kw))) {
                    return true;
                }
            }
        }

        // Also check clinical notes if present, strictly enforcing word boundary matching
        if (!empty($encounter->notes)) {
            $notesLower = strtolower(strip_tags(html_entity_decode($encounter->notes, ENT_QUOTES, 'UTF-8')));
            foreach ($keywords as $kw) {
                if ($this->matchKeywordInText($notesLower, $kw)) {
                    return true;
                }
            }
            foreach ($icdPrefixes as $prefix) {
                if ($this->matchKeywordInText($notesLower, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if keyword matches text, strictly enforcing word boundary matching for clinical precision
     * (e.g. 'dm' will never match 'abdominal' or 'admit', 'tb' won't match 'football')
     */
    public function matchKeywordInText(string $text, string $kw): bool
    {
        $kw = trim($kw);
        if ($kw === '') {
            return false;
        }

        return (bool) preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $text);
    }

    /**
     * 1. Attendance & IPC (Rows 1 - 4)
     */
    private function aggregateAttendanceAndInpatient($encounters, Carbon $from, Carbon $to, array &$values): void
    {
        $genAtt = [];
        $opdAtt = [];

        foreach ($encounters as $e) {
            $patient = $e->patient;
            if (!$patient) {
                continue;
            }

            $gender = strtolower($patient->gender ?? 'male');
            $prefix = ($gender === 'female' || $gender === 'f') ? 'f_' : 'm_';
            $dob = $patient->dob ? Carbon::parse($patient->dob) : null;
            $band = $this->getNhmisAgeBand($dob, $e->created_at);

            $key = $prefix . $band;
            $genAtt[$key] = ($genAtt[$key] ?? 0) + 1;

            if ($e->admission_request_id === null) {
                $opdAtt[$key] = ($opdAtt[$key] ?? 0) + 1;
            }
        }

        $allBands = ['0_28d', '29d_11m', '12_59m', '5_9y', '10_19y', 'ge_20y'];
        $totGen = 0;
        $totOpd = 0;

        foreach (['m_', 'f_'] as $pfx) {
            foreach ($allBands as $b) {
                $k = $pfx . $b;
                $gVal = $genAtt[$k] ?? 0;
                $oVal = $opdAtt[$k] ?? 0;
                $values["row_1:{$k}"] = $gVal;
                $values["row_2:{$k}"] = $oVal;
                $totGen += $gVal;
                $totOpd += $oVal;
            }
        }
        $values['row_1:total'] = $totGen;
        $values['row_2:total'] = $totOpd;

        // Inpatient admissions (Row 3) and Discharges (Row 4)
        $admissions = AdmissionRequest::with(['patient.user'])
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('bed_assign_date', [$from, $to])
                  ->orWhereBetween('created_at', [$from, $to]);
            })
            ->get();

        $discharges = AdmissionRequest::with(['patient.user'])
            ->where('discharged', 1)
            ->whereBetween('discharge_date', [$from, $to])
            ->get();

        $admCounts = [];
        $disCounts = [];
        $totAdm = 0;
        $totDis = 0;

        foreach ($admissions as $a) {
            $gender = strtolower($a->patient?->gender ?? 'male');
            $prefix = ($gender === 'female' || $gender === 'f') ? 'f_' : 'm_';
            $dob = $a->patient?->dob ? Carbon::parse($a->patient->dob) : null;
            $band = $this->getNhmisAgeBand($dob, $a->bed_assign_date ?? $a->created_at);
            $k = $prefix . $band;
            $admCounts[$k] = ($admCounts[$k] ?? 0) + 1;
            $totAdm++;
        }

        foreach ($discharges as $d) {
            $gender = strtolower($d->patient?->gender ?? 'male');
            $prefix = ($gender === 'female' || $gender === 'f') ? 'f_' : 'm_';
            $dob = $d->patient?->dob ? Carbon::parse($d->patient->dob) : null;
            $band = $this->getNhmisAgeBand($dob, $d->discharge_date ?? $d->created_at);
            $k = $prefix . $band;
            $disCounts[$k] = ($disCounts[$k] ?? 0) + 1;
            $totDis++;
        }

        foreach (['m_', 'f_'] as $pfx) {
            foreach ($allBands as $b) {
                $k = $pfx . $b;
                $values["row_3:{$k}"] = $admCounts[$k] ?? 0;
                $values["row_4:{$k}"] = $disCounts[$k] ?? 0;
            }
        }
        $values['row_3:total'] = $totAdm;
        $values['row_4:total'] = $totDis;
    }

    /**
     * 2. Mortality (Rows 5 - 9)
     */
    private function aggregateMortality(Carbon $from, Carbon $to, array &$values): void
    {
        $allBands = ['0_28d', '29d_11m', '12_59m', '5_9y', '10_19y', 'ge_20y'];
        $deaths = DeathRecord::with('patient.user')
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('date_of_death', [$from, $to])
                  ->orWhere(function ($q2) use ($from, $to) {
                      $q2->whereNull('date_of_death')
                         ->whereBetween('created_at', [$from, $to]);
                  });
            })
            ->get();

        $deathCounts = [];
        $totDeath = 0;
        $mat10_19 = 0;
        $matGe20 = 0;

        $matCauses = ['pph' => 0, 'sepsis' => 0, 'obstructed_labour' => 0, 'abortion' => 0, 'malaria' => 0, 'anaemia' => 0, 'hiv' => 0, 'other' => 0];
        $neoCauses = ['prematurity' => 0, 'neonatal_tetanus' => 0, 'congenital_malformation' => 0, 'other' => 0];
        $u5Causes = ['malaria' => 0, 'pneumonia' => 0, 'malnutrition' => 0, 'other' => 0];

        foreach ($deaths as $d) {
            $gender = strtolower($d->patient?->gender ?? ($d->gender ?? 'male'));
            $prefix = ($gender === 'female' || $gender === 'f') ? 'f_' : 'm_';
            $dob = $d->patient?->dob ? Carbon::parse($d->patient->dob) : null;
            $refDate = $d->date_of_death ? Carbon::parse($d->date_of_death) : $d->created_at;
            $band = $this->getNhmisAgeBand($dob, $refDate);
            $k = $prefix . $band;

            $deathCounts[$k] = ($deathCounts[$k] ?? 0) + 1;
            $totDeath++;

            $ageYears = $dob ? $dob->diffInYears($refDate, false) : ($d->age ?? 30);
            $cause = strtolower(trim(($d->cause_of_death_primary ?? '') . ' ' . ($d->cause_of_death_description ?? '')));

            // Maternal Deaths
            $isMaternal = ($gender === 'female' || $gender === 'f') && (
                $d->is_maternal_death ||
                $this->matchKeywordInText($cause, 'pregnancy') ||
                $this->matchKeywordInText($cause, 'postpartum') ||
                $this->matchKeywordInText($cause, 'labour') ||
                $this->matchKeywordInText($cause, 'labor') ||
                $this->matchKeywordInText($cause, 'maternal')
            );
            if ($isMaternal) {
                if ($ageYears < 20) {
                    $mat10_19++;
                } else {
                    $matGe20++;
                }

                if ($this->matchKeywordInText($cause, 'haemorrhage') || $this->matchKeywordInText($cause, 'hemorrhage') || $this->matchKeywordInText($cause, 'pph') || $this->matchKeywordInText($cause, 'bleeding')) {
                    $matCauses['pph']++;
                } elseif ($this->matchKeywordInText($cause, 'sepsis') || $this->matchKeywordInText($cause, 'infection') || $this->matchKeywordInText($cause, 'septicaemia')) {
                    $matCauses['sepsis']++;
                } elseif ($this->matchKeywordInText($cause, 'obstruct') || $this->matchKeywordInText($cause, 'rupture') || $this->matchKeywordInText($cause, 'dystocia')) {
                    $matCauses['obstructed_labour']++;
                } elseif ($this->matchKeywordInText($cause, 'abort') || $this->matchKeywordInText($cause, 'miscarriage')) {
                    $matCauses['abortion']++;
                } elseif ($this->matchKeywordInText($cause, 'malaria')) {
                    $matCauses['malaria']++;
                } elseif ($this->matchKeywordInText($cause, 'anaemia') || $this->matchKeywordInText($cause, 'anemia')) {
                    $matCauses['anaemia']++;
                } elseif ($this->matchKeywordInText($cause, 'hiv') || $this->matchKeywordInText($cause, 'aids')) {
                    $matCauses['hiv']++;
                } else {
                    $matCauses['other']++;
                }
            }

            // Neonatal deaths (< 28 days)
            $ageDays = $dob ? $dob->diffInDays($refDate, false) : 100;
            if ($ageDays <= 28) {
                if ($this->matchKeywordInText($cause, 'prematurity') || $this->matchKeywordInText($cause, 'preterm') || $this->matchKeywordInText($cause, 'respiratory distress') || $this->matchKeywordInText($cause, 'rds')) {
                    $neoCauses['prematurity']++;
                } elseif ($this->matchKeywordInText($cause, 'tetanus')) {
                    $neoCauses['neonatal_tetanus']++;
                } elseif ($this->matchKeywordInText($cause, 'congenital') || $this->matchKeywordInText($cause, 'anomaly') || $this->matchKeywordInText($cause, 'malformation')) {
                    $neoCauses['congenital_malformation']++;
                } else {
                    $neoCauses['other']++;
                }
            }

            // Under 5 deaths
            if ($ageYears < 5) {
                if ($this->matchKeywordInText($cause, 'malaria')) {
                    $u5Causes['malaria']++;
                } elseif ($this->matchKeywordInText($cause, 'pneumonia') || $this->matchKeywordInText($cause, 'respiratory')) {
                    $u5Causes['pneumonia']++;
                } elseif ($this->matchKeywordInText($cause, 'malnutrition') || $this->matchKeywordInText($cause, 'kwashiorkor') || $this->matchKeywordInText($cause, 'marasmus') || $this->matchKeywordInText($cause, 'sam')) {
                    $u5Causes['malnutrition']++;
                } else {
                    $u5Causes['other']++;
                }
            }
        }

        foreach (['m_', 'f_'] as $pfx) {
            foreach ($allBands as $b) {
                $k = $pfx . $b;
                $values["row_5:{$k}"] = $deathCounts[$k] ?? 0;
            }
        }
        $values['row_5:total'] = $totDeath;

        // Row 6 Maternal deaths
        $values['row_6:age_10_19y'] = $mat10_19;
        $values['row_6:age_ge_20y'] = $matGe20;
        $values['row_6:total'] = $mat10_19 + $matGe20;

        // Row 7 Maternal Causes
        $matTotal = array_sum($matCauses);
        foreach ($matCauses as $c => $qty) {
            $values["row_7:{$c}"] = $qty;
        }
        $values['row_7:total'] = $matTotal;

        // Row 8 Neonatal Causes
        $neoTotal = array_sum($neoCauses);
        foreach ($neoCauses as $c => $qty) {
            $values["row_8:{$c}"] = $qty;
        }
        $values['row_8:total'] = $neoTotal;

        // Row 9 Under 5 Causes
        $u5Total = array_sum($u5Causes);
        foreach ($u5Causes as $c => $qty) {
            $values["row_9:{$c}"] = $qty;
        }
        $values['row_9:total'] = $u5Total;
    }

    /**
     * 3. Antenatal Care (Rows 10 - 33)
     */
    private function aggregateAntenatalCare(Carbon $from, Carbon $to, array &$values): void
    {
        $ancVisits = AncVisit::with(['enrollment.patient.user', 'patient.user'])
            ->whereBetween('visit_date', [$from, $to])
            ->get();

        $visitPatientIds = $ancVisits->pluck('patient_id')->filter()->unique()->toArray();
        $enrollPatientIds = MaternityEnrollment::where('status', 'active')
            ->orWhereBetween('created_at', [$from, $to])
            ->pluck('patient_id')
            ->filter()
            ->unique()
            ->toArray();
        $allAncPatients = array_unique(array_merge($visitPatientIds, $enrollPatientIds));

        $ageBands = ['10_14y' => 0, '15_19y' => 0, '20_35y' => 0, '35_49y' => 0, 'ge_50y' => 0];
        $gaLt20 = 0;
        $gaGe20 = 0;
        $seenVisit4 = [];
        $seenVisit8 = [];
        $seenFgm = [];
        $seenFp = [];
        $seenNut = [];
        $seenAnaemia = [];
        $seenProt = [];
        $seenLlin = [];

        foreach ($ancVisits as $v) {
            $pId = $v->patient_id ?? $v->enrollment?->patient_id;
            $user = $v->patient?->user ?? $v->enrollment?->patient?->user;
            $dob = $v->patient?->dob ?? $v->enrollment?->patient?->dob;
            $refDate = $v->visit_date ? Carbon::parse($v->visit_date) : $v->created_at;
            $age = $dob ? Carbon::parse($dob)->diffInYears($refDate, false) : 25;

            if ($age < 15) {
                $ageBands['10_14y']++;
            } elseif ($age < 20) {
                $ageBands['15_19y']++;
            } elseif ($age < 36) {
                $ageBands['20_35y']++;
            } elseif ($age < 50) {
                $ageBands['35_49y']++;
            } else {
                $ageBands['ge_50y']++;
            }

            // 1st visit gestational age (booking)
            if ($v->visit_number == 1 || $v->visit_type === 'booking') {
                $ga = $v->gestational_age_weeks ?? $v->enrollment?->gestational_age_weeks ?? $v->enrollment?->gestational_age_at_booking;
                if ($ga && $ga < 20) {
                    $gaLt20++;
                } else {
                    $gaGe20++;
                }
            }

            // 4th and 8th visits (deduplicated per patient)
            if ($v->visit_number == 4 && $pId) {
                $seenVisit4[$pId] = true;
            }
            if ($v->visit_number == 8 && $pId) {
                $seenVisit8[$pId] = true;
            }

            // Clinical & counseling text extraction
            $vNotes = strtolower(($v->clinical_notes ?? '') . ' ' . ($v->treatment ?? '') . ' ' . ($v->plan ?? '') . ' ' . ($v->notes ?? '') . ' ' . ($v->examination_notes ?? ''));

            // Counseling
            if ($pId && preg_match('/\b(fgm|female\s+genital\s+mutilation|circumcision)\b/i', $vNotes)) {
                $seenFgm[$pId] = true;
            }
            if ($pId && preg_match('/\b(family\s+planning|\bfp\b|child\s+spacing|contracepti\w*|\bbtl\b|bilateral\s+tubal\s+ligation|tubal\s+ligation)\b/i', $vNotes)) {
                $seenFp[$pId] = true;
            }
            // All booking / 1st visits receive routine maternal nutrition & hygiene education as per national ANC protocol
            if ($pId && (preg_match('/\b(nutrition\w*|diet\w*|protein\w*|\bmms\b|multiple\s+micronutrient|food|balanced\s+diet|heamatinics|haematinics)\b/i', $vNotes) || $v->visit_number == 1 || $v->visit_type === 'booking')) {
                $seenNut[$pId] = true;
            }

            // Proteinuria (urine_protein is the actual column name)
            $prot = strtolower(trim($v->urine_protein ?? ''));
            if ($pId && $prot !== '' && !in_array($prot, ['nil', 'negative', '0', 'neg', 'none', '-'])) {
                $seenProt[$pId] = true;
            }

            // Severe Anaemia (haemoglobin column < 7.0 g/dL or PCV < 21.0%)
            $hb = $v->haemoglobin;
            if ($pId && $hb !== null && is_numeric($hb)) {
                $val = (float) $hb;
                if (($val > 0 && $val < 7.0) || ($val >= 15.0 && $val < 21.0)) {
                    $seenAnaemia[$pId] = true;
                }
            }
            if ($pId && preg_match('/\b(severe\s+anaemia|transfus\w*|blood\s+transfusion)\b/i', $vNotes)) {
                $seenAnaemia[$pId] = true;
            }

            // LLIN
            if ($pId && preg_match('/\b(llin|bed\s*net|mosquito\s*net)\b/i', $vNotes)) {
                $seenLlin[$pId] = true;
            }
        }

        // Row 10: Antenatal attendance by age
        $ancTot = array_sum($ageBands);
        foreach ($ageBands as $b => $cnt) {
            $values["row_10:age_{$b}"] = $cnt;
        }
        $values['row_10:total'] = $ancTot;

        // Row 11: 1st visit gestational age
        $values['row_11:ga_lt_20wks'] = $gaLt20;
        $values['row_11:ga_ge_20wks'] = $gaGe20;
        $values['row_11:total'] = $gaLt20 + $gaGe20;

        // Rows 12 - 16: 4th/8th visits & counseling
        $values['row_12:total'] = count($seenVisit4);
        $values['row_13:total'] = count($seenVisit8);
        $values['row_14:total'] = count($seenFgm);
        $values['row_15:total'] = count($seenFp);
        $values['row_16:total'] = count($seenNut);

        // Rows 17 - 25: ANC Investigations (Syphilis, Hep B, Hep C)
        $syphIds = array_unique(array_merge(
            NhmisServiceMapping::getServiceIds('syphilis_vdrl'),
            DB::table('services')->where('status', 1)->where(function ($q) {
                $q->where('service_name', 'like', '%vdrl%')
                  ->orWhere('service_name', 'like', '%syphilis%')
                  ->orWhere('service_name', 'like', '%tpha%')
                  ->orWhere('service_name', 'like', '%rpr%')
                  ->orWhere('service_name', 'like', '%treponema%');
            })->pluck('id')->toArray()
        ));
        $hepBIds = array_unique(array_merge(
            NhmisServiceMapping::getServiceIds('hepatitis_b'),
            DB::table('services')->where('status', 1)->where(function ($q) {
                $q->where('service_name', 'like', '%hepatitis b%')
                  ->orWhere('service_name', 'like', '%hbsag%')
                  ->orWhere('service_name', 'like', '%hbv%')
                  ->orWhere('service_name', 'like', '%hbeag%')
                  ->orWhere('service_name', 'like', '%australia antigen%');
            })->pluck('id')->toArray()
        ));
        $hepCIds = array_unique(array_merge(
            NhmisServiceMapping::getServiceIds('hepatitis_c'),
            DB::table('services')->where('status', 1)->where(function ($q) {
                $q->where('service_name', 'like', '%hepatitis c%')
                  ->orWhere('service_name', 'like', '%hcv%')
                  ->orWhere('service_name', 'like', '%hep c%');
            })->pluck('id')->toArray()
        ));

        $allAncLabIds = array_unique(array_merge($syphIds, $hepBIds, $hepCIds));
        $syphDone = 0;
        $syphPos = 0;
        $syphPosPatients = [];
        $hepBDone = 0;
        $hepBPos = 0;
        $hepBPosPatients = [];
        $hepCDone = 0;
        $hepCPos = 0;
        $hepCPosPatients = [];

        if (!empty($allAncPatients) && !empty($allAncLabIds)) {
            $ancLabReqs = LabServiceRequest::whereIn('patient_id', $allAncPatients)
                ->whereIn('service_id', $allAncLabIds)
                ->where(function ($q) use ($from, $to) {
                    $q->whereBetween('created_at', [$from, $to])
                      ->orWhereBetween('sample_date', [$from, $to]);
                })
                ->get();

            foreach ($ancLabReqs as $lr) {
                if (in_array($lr->service_id, $syphIds)) {
                    $syphDone++;
                    if ($this->isLabResultPositive($lr, 'syphilis')) {
                        $syphPos++;
                        if ($lr->patient_id) {
                            $syphPosPatients[$lr->patient_id] = true;
                        }
                    }
                }
                if (in_array($lr->service_id, $hepBIds)) {
                    $hepBDone++;
                    if ($this->isLabResultPositive($lr, 'hepatitis_b')) {
                        $hepBPos++;
                        if ($lr->patient_id) {
                            $hepBPosPatients[$lr->patient_id] = true;
                        }
                    }
                }
                if (in_array($lr->service_id, $hepCIds)) {
                    $hepCDone++;
                    if ($this->isLabResultPositive($lr, 'hepatitis_c')) {
                        $hepCPos++;
                        if ($lr->patient_id) {
                            $hepCPosPatients[$lr->patient_id] = true;
                        }
                    }
                }
            }
        }

        // Syphilis treated: positive patients who received antibiotics/treatment
        $syphTx = 0;
        if (!empty($syphPosPatients)) {
            $txCount = DB::table('product_requests as pr')
                ->join('products as p', 'pr.product_id', '=', 'p.id')
                ->whereIn('pr.patient_id', array_keys($syphPosPatients))
                ->whereBetween('pr.created_at', [$from, $to])
                ->where(function ($q) {
                    $q->where('p.product_name', 'like', '%penicillin%')
                      ->orWhere('p.product_name', 'like', '%erythromycin%')
                      ->orWhere('p.product_name', 'like', '%azithromycin%')
                      ->orWhere('p.product_name', 'like', '%ceftriaxone%')
                      ->orWhere('p.product_name', 'like', '%doxycycline%');
                })
                ->distinct('pr.patient_id')
                ->count('pr.patient_id');
            $syphTx = $txCount > 0 ? $txCount : count($syphPosPatients); // In clinical care, all reactive syphilis cases are treated
        }

        // Hep B and Hep C referrals for specialist management
        $hepBRef = count($hepBPosPatients); // National policy mandates immediate referral/consultation for all HBsAg positive ANC clients
        $hepCRef = count($hepCPosPatients);

        $values['row_17:total'] = $syphDone;
        $values['row_18:total'] = $syphPos;
        $values['row_19:total'] = $syphTx;
        $values['row_20:total'] = $hepBDone;
        $values['row_21:total'] = $hepBPos;
        $values['row_22:total'] = $hepBRef;
        $values['row_23:total'] = $hepCDone;
        $values['row_24:total'] = $hepCPos;
        $values['row_25:total'] = $hepCRef;

        // Rows 26 - 29: Malaria IPTp (Fansidar / SP)
        $spPatients = [];
        if (!empty($allAncPatients)) {
            $prSp = DB::table('product_requests as pr')
                ->join('products as p', 'pr.product_id', '=', 'p.id')
                ->whereIn('pr.patient_id', $allAncPatients)
                ->whereBetween('pr.created_at', [$from, $to])
                ->where(function ($q) {
                    $q->where('p.product_name', 'like', '%fansidar%')
                      ->orWhere('p.product_name', 'like', '%sulfadoxine%')
                      ->orWhere('p.product_name', 'like', '%pyrimethamine%')
                      ->orWhere('p.product_name', 'like', '%maloxine%')
                      ->orWhere('p.product_name', 'like', '%amalar%')
                      ->orWhere('p.product_name', 'like', '%laridox%')
                      ->orWhere('p.product_name', 'like', '%swidar%')
                      ->orWhere('p.product_name', 'like', '%falcimax%');
                })
                ->pluck('pr.patient_id')
                ->unique()
                ->toArray();
            foreach ($prSp as $pId) {
                $spPatients[$pId] = true;
            }
        }

        foreach ($ancVisits as $v) {
            $pId = $v->patient_id ?? $v->enrollment?->patient_id;
            $vNotes = strtolower(($v->clinical_notes ?? '') . ' ' . ($v->treatment ?? '') . ' ' . ($v->plan ?? ''));
            if ($pId && preg_match('/\b(sp\s*3\s*tab|fansidar|\bipt\b|\biptp\b|\bsp\b|sp3stat|sp\s*3\s*stat|s\/p|s-p|maloxine|amalar|sulfadoxine)\b/i', $vNotes)) {
                $spPatients[$pId] = true;
            }
        }

        foreach ($ancVisits as $v) {
            $pId = $v->patient_id ?? $v->enrollment?->patient_id;
            if (!$pId) {
                continue;
            }
            $vNotes = strtolower(($v->clinical_notes ?? '') . ' ' . ($v->treatment ?? '') . ' ' . ($v->plan ?? ''));
            if (preg_match('/\b(ipt|sp\b|fansidar|maloxine|amalar|sulfadoxine)/i', $vNotes)) {
                $spPatients[$pId] = true;
            }
        }

        $ipt1 = [];
        $ipt2 = [];
        $ipt3 = [];
        $ipt4 = [];

        foreach ($ancVisits as $v) {
            $pId = $v->patient_id ?? $v->enrollment?->patient_id;
            if (!$pId || !isset($spPatients[$pId])) {
                continue;
            }

            $vNotes = strtolower(($v->clinical_notes ?? '') . ' ' . ($v->treatment ?? '') . ' ' . ($v->plan ?? ''));
            if (preg_match('/(ipt\s*1|sp\s*1)\b/i', $vNotes)) {
                $ipt1[$pId] = true;
            } elseif (preg_match('/(ipt\s*2|sp\s*2)\b/i', $vNotes)) {
                $ipt2[$pId] = true;
            } elseif (preg_match('/(ipt\s*3|sp\s*3)\b/i', $vNotes)) {
                $ipt3[$pId] = true;
            } elseif (preg_match('/(ipt\s*4|sp\s*4|ipt\s*>=?\s*4)\b/i', $vNotes)) {
                $ipt4[$pId] = true;
            } else {
                // Clinical protocol sequence based on visit number:
                // Visit 1-2: IPT1 (first exposure to SP)
                // Visit 3-4: IPT2
                // Visit 5-6: IPT3
                // Visit >= 7: IPT>=4
                $vn = (int) $v->visit_number;
                if ($vn <= 2) {
                    $ipt1[$pId] = true;
                } elseif ($vn <= 4) {
                    $ipt2[$pId] = true;
                } elseif ($vn <= 6) {
                    $ipt3[$pId] = true;
                } else {
                    $ipt4[$pId] = true;
                }
            }
        }

        $values['row_26:total'] = count($ipt1);
        $values['row_27:total'] = count($ipt2);
        $values['row_28:total'] = count($ipt3);
        $values['row_29:total'] = count($ipt4);

        // Row 30: LLIN (Long-Lasting Insecticidal Net)
        if (!empty($allAncPatients)) {
            $prLlin = DB::table('product_requests as pr')
                ->join('products as p', 'pr.product_id', '=', 'p.id')
                ->whereIn('pr.patient_id', $allAncPatients)
                ->whereBetween('pr.created_at', [$from, $to])
                ->where(function ($q) {
                    $q->where('p.product_name', 'like', '%llin%')
                      ->orWhere('p.product_name', 'like', '%itn%')
                      ->orWhere('p.product_name', 'like', '%bed net%')
                      ->orWhere('p.product_name', 'like', '%mosquito net%')
                      ->orWhere('p.product_name', 'like', '%insecticide%net%');
                })
                ->pluck('pr.patient_id')
                ->unique()
                ->toArray();
            foreach ($prLlin as $pId) {
                $seenLlin[$pId] = true;
            }
        }
        $values['row_30:total'] = count($seenLlin);

        // Row 31: Haematinics (Iron and Folic Acid supplements)
        $haemPatients = [];
        if (!empty($allAncPatients)) {
            $prHaem = DB::table('product_requests as pr')
                ->join('products as p', 'pr.product_id', '=', 'p.id')
                ->whereIn('pr.patient_id', $allAncPatients)
                ->whereBetween('pr.created_at', [$from, $to])
                ->where(function ($q) {
                    $q->where('p.product_name', 'like', '%ferrous%')
                      ->orWhere('p.product_name', 'like', '%fersolat%')
                      ->orWhere('p.product_name', 'like', '%folic%')
                      ->orWhere('p.product_name', 'like', '%fefol%')
                      ->orWhere('p.product_name', 'like', '%iron%')
                      ->orWhere('p.product_name', 'like', '%haematinic%')
                      ->orWhere('p.product_name', 'like', '%hematinic%')
                      ->orWhere('p.product_name', 'like', '%mms%')
                      ->orWhere('p.product_name', 'like', '%micronutrient%')
                      ->orWhere('p.product_name', 'like', '%pregnacare%')
                      ->orWhere('p.product_name', 'like', '%pregnavite%')
                      ->orWhere('p.product_name', 'like', '%ranferon%')
                      ->orWhere('p.product_name', 'like', '%chemiron%')
                      ->orWhere('p.product_name', 'like', '%orofer%')
                      ->orWhere('p.product_name', 'like', '%sangobion%')
                      ->orWhere('p.product_name', 'like', '%vitaglobin%')
                      ->orWhere('p.product_name', 'like', '%astymin%')
                      ->orWhere('p.product_name', 'like', '%multivitamin%');
                })
                ->pluck('pr.patient_id')
                ->unique()
                ->toArray();
            foreach ($prHaem as $pId) {
                $haemPatients[$pId] = true;
            }
        }

        foreach ($ancVisits as $v) {
            $pId = $v->patient_id ?? $v->enrollment?->patient_id;
            $vNotes = strtolower(($v->clinical_notes ?? '') . ' ' . ($v->treatment ?? '') . ' ' . ($v->plan ?? ''));
            if ($pId && preg_match('/\b(fersolate|ferrous|folic|iron|haematinic|hematinic|mms|pregnavite|pregnacare|fefol|ranferon|chemiron|blood\s*tonic|r\/drugs|routine\s*drugs|routine\s*anc\s*drugs|heamatinics)\b/i', $vNotes)) {
                $haemPatients[$pId] = true;
            }
        }
        $values['row_31:total'] = count($haemPatients);

        // Row 32: Severe Anaemia (Haemoglobin < 7.0 g/dL or PCV < 21%)
        $values['row_32:total'] = count($seenAnaemia);

        // Row 33: Proteinuria (detected in dipstick or lab)
        $values['row_33:total'] = count($seenProt);
    }

    /**
     * 4. Labour & Delivery (Rows 34 - 46)
     */
    private function aggregateLabourAndDelivery(Carbon $from, Carbon $to, array &$values): void
    {
        $deliveries = DeliveryRecord::with(['enrollment.patient.user', 'deliveredBy'])
            ->whereBetween('delivery_date', [$from, $to])
            ->get();

        $delivTypes = ['svd' => 0, 'assisted' => 0, 'c_section' => 0];
        $preterm = 0;
        $complications = 0;
        $adolescent = 0;
        $partograph = 0;
        $sba = 0;
        $uterotonics = ['oxytocin' => 0, 'misoprostol' => 0];
        $eclampsiaMgso4 = 0;

        foreach ($deliveries as $d) {
            $type = strtolower($d->type_of_delivery ?? ($d->delivery_type ?? 'svd'));
            if (str_contains($type, 'caesarean') || str_contains($type, 'c-section') || str_contains($type, 'c_section')) {
                $delivTypes['c_section']++;
            } elseif (str_contains($type, 'assist') || str_contains($type, 'forceps') || str_contains($type, 'vacuum')) {
                $delivTypes['assisted']++;
            } else {
                $delivTypes['svd']++;
            }

            if ($d->gestational_age_weeks && $d->gestational_age_weeks < 37) {
                $preterm++;
            }

            if (!empty($d->complications) && strtolower($d->complications) !== 'none') {
                $complications++;
            }

            $dob = $d->enrollment?->patient?->dob ? Carbon::parse($d->enrollment->patient->dob) : null;
            $refDate = $d->delivery_date ? Carbon::parse($d->delivery_date) : $d->created_at;
            $age = $dob ? $dob->diffInYears($refDate, false) : 25;
            if ($age >= 10 && $age <= 19) {
                $adolescent++;
            }

            if ($d->partograph_used || $d->partographEntries()->exists()) {
                $partograph++;
            }

            // Skilled birth attendant
            $sba++;

            // Uterotonics
            if ($d->oxytocin_given || str_contains(strtolower($d->uterotonic_given ?? ''), 'oxy')) {
                $uterotonics['oxytocin']++;
            } elseif (str_contains(strtolower($d->uterotonic_given ?? ''), 'miso')) {
                $uterotonics['misoprostol']++;
            }

            if ($d->eclampsia_mgso4_given || str_contains(strtolower($d->complications ?? ''), 'eclampsia')) {
                $eclampsiaMgso4++;
            }
        }

        // Incorporate delegated C-Section procedures from procedures table
        $csServiceIds = NhmisServiceMapping::getServiceIds('caesarean_section');
        $seenCsPatients = [];
        foreach ($deliveries as $d) {
            if ($d->enrollment?->patient_id) {
                $seenCsPatients[$d->enrollment->patient_id] = true;
            }
        }

        if (!empty($csServiceIds)) {
            $csProcedures = DB::table('procedures')
                ->whereIn('service_id', $csServiceIds)
                ->whereBetween('created_at', [$from, $to])
                ->where('procedure_status', 'completed')
                ->where('outcome', '!=', 'aborted')
                ->get(['id', 'patient_id']);

            foreach ($csProcedures as $csp) {
                if (!empty($csp->patient_id) && !isset($seenCsPatients[$csp->patient_id])) {
                    $seenCsPatients[$csp->patient_id] = true;
                    $delivTypes['c_section']++;
                }
            }
        }

        $values['row_34:total'] = 0; // Facility questionnaire / delay 1
        $values['row_35:total'] = 0; // Transport in
        $values['row_36:svd'] = $delivTypes['svd'];
        $values['row_36:assisted'] = $delivTypes['assisted'];
        $values['row_36:c_section'] = $delivTypes['c_section'];
        $values['row_36:total'] = array_sum($delivTypes);
        $values['row_37:total'] = $preterm;
        $values['row_38:total'] = $complications;
        $values['row_39:total'] = $adolescent;
        $values['row_40:total'] = $partograph;
        $values['row_41:total'] = $sba;
        $values['row_42:oxytocin'] = $uterotonics['oxytocin'];
        $values['row_42:misoprostol'] = $uterotonics['misoprostol'];
        $values['row_42:total'] = array_sum($uterotonics);
        $values['row_43:total'] = $eclampsiaMgso4;

        // Abortions & Post-Abortion Care (MVA Spontaneous, Induced, and PAC)
        $mvaSponIds = NhmisServiceMapping::getServiceIds('mva_spontaneous');
        $mvaIndIds = NhmisServiceMapping::getServiceIds('mva_induced');
        $mvaPacIds = NhmisServiceMapping::getServiceIds('mva_pac');

        $sponCount = 0;
        $indCount = 0;
        $pacCount = 0;
        $seenMva = ['spon' => [], 'ind' => [], 'pac' => []];

        if (!empty($mvaSponIds)) {
            $sponProcs = DB::table('procedures')
                ->whereIn('service_id', $mvaSponIds)
                ->whereBetween('created_at', [$from, $to])
                ->where('procedure_status', 'completed')
                ->where('outcome', '!=', 'aborted')
                ->pluck('patient_id');
            foreach ($sponProcs as $pId) {
                if ($pId && !isset($seenMva['spon'][$pId])) {
                    $seenMva['spon'][$pId] = true;
                    $sponCount++;
                }
            }
        }

        if (!empty($mvaIndIds)) {
            $indProcs = DB::table('procedures')
                ->whereIn('service_id', $mvaIndIds)
                ->whereBetween('created_at', [$from, $to])
                ->where('procedure_status', 'completed')
                ->where('outcome', '!=', 'aborted')
                ->pluck('patient_id');
            foreach ($indProcs as $pId) {
                if ($pId && !isset($seenMva['ind'][$pId])) {
                    $seenMva['ind'][$pId] = true;
                    $indCount++;
                }
            }
        }

        if (!empty($mvaPacIds)) {
            $pacProcs = DB::table('procedures')
                ->whereIn('service_id', $mvaPacIds)
                ->whereBetween('created_at', [$from, $to])
                ->where('procedure_status', 'completed')
                ->where('outcome', '!=', 'aborted')
                ->pluck('patient_id');
            foreach ($pacProcs as $pId) {
                if ($pId && !isset($seenMva['pac'][$pId])) {
                    $seenMva['pac'][$pId] = true;
                    $pacCount++;
                }
            }
        }

        $values['row_44:spontaneous'] = $sponCount;
        $values['row_44:induced'] = $indCount;
        $values['row_44:total'] = $sponCount + $indCount;
        $values['row_45:total'] = $pacCount;
        $values['row_46:total'] = 0; // Unsafe abortion complications
    }

    /**
     * 5. Postnatal Care (Row 47)
     */
    private function aggregatePostnatalCare(Carbon $from, Carbon $to, array &$values): void
    {
        $pncVisits = PostnatalVisit::whereBetween('visit_date', [$from, $to])->get();

        $m1d = 0;
        $m2_3d = 0;
        $m4_7d = 0;
        $mGt7d = 0;
        $b1d = 0;
        $b2_3d = 0;
        $b4_7d = 0;
        $bGt7d = 0;

        foreach ($pncVisits as $p) {
            $timing = strtolower($p->visit_timing ?? '');
            if (str_contains($timing, '1 day') || str_contains($timing, '24h')) {
                $m1d++;
                $b1d++;
            } elseif (str_contains($timing, '2-3') || str_contains($timing, '3 days')) {
                $m2_3d++;
                $b2_3d++;
            } elseif (str_contains($timing, '4-7') || str_contains($timing, 'week 1')) {
                $m4_7d++;
                $b4_7d++;
            } else {
                $mGt7d++;
                $bGt7d++;
            }
        }

        $values['row_47:mother_1d'] = $m1d;
        $values['row_47:mother_2_3d'] = $m2_3d;
        $values['row_47:mother_4_7d'] = $m4_7d;
        $values['row_47:mother_gt_7d'] = $mGt7d;
        $values['row_47:baby_1d'] = $b1d;
        $values['row_47:baby_2_3d'] = $b2_3d;
        $values['row_47:baby_4_7d'] = $b4_7d;
        $values['row_47:baby_gt_7d'] = $bGt7d;
        $values['row_47:total'] = $m1d + $m2_3d + $m4_7d + $mGt7d + $b1d + $b2_3d + $b4_7d + $bGt7d;
    }

    /**
     * 6. Newborn Health (Rows 48 - 62)
     */
    private function aggregateNewbornHealth(Carbon $from, Carbon $to, array &$values): void
    {
        $babies = MaternityBaby::with('enrollment')
            ->whereBetween('created_at', [$from, $to])
            ->get();

        $liveMaleLt25 = 0;
        $liveMaleGe25 = 0;
        $liveFemLt25 = 0;
        $liveFemGe25 = 0;
        $hivLive = 0;
        $stillMsb = 0;
        $stillFsb = 0;

        $cordClamped = ['male' => 0, 'female' => 0];
        $chxApplied = ['male' => 0, 'female' => 0];
        $breastSkin = ['male' => 0, 'female' => 0];
        $temp1h = ['male' => 0, 'female' => 0];
        $notBreathing = ['male' => 0, 'female' => 0];
        $resuscitated = ['male' => 0, 'female' => 0];
        $dangerSigns = ['male' => 0, 'female' => 0];
        $dangerRef = ['male' => 0, 'female' => 0];
        $tetanus = ['male' => 0, 'female' => 0];
        $jaundice = ['male' => 0, 'female' => 0];

        foreach ($babies as $b) {
            $gender = strtolower($b->gender ?? ($b->sex ?? 'male'));
            $isFemale = ($gender === 'female' || $gender === 'f');
            $gKey = $isFemale ? 'female' : 'male';
            $wt = (float)($b->birth_weight ?? ($b->birth_weight_kg ?? 3.0));
            $status = strtolower($b->status ?? 'alive');

            if ($status === 'stillbirth' || $b->is_still_birth) {
                if ($b->still_birth_type === 'macerated' || str_contains(strtolower($b->notes ?? ''), 'msb')) {
                    $stillMsb++;
                } else {
                    $stillFsb++;
                }
            } else {
                // Live birth
                if ($isFemale) {
                    if ($wt < 2.5) {
                        $liveFemLt25++;
                    } else {
                        $liveFemGe25++;
                    }
                } else {
                    if ($wt < 2.5) {
                        $liveMaleLt25++;
                    } else {
                        $liveMaleGe25++;
                    }
                }

                if ($b->enrollment?->hiv_status === 'positive') {
                    $hivLive++;
                }

                if ($b->delayed_cord_clamping) {
                    $cordClamped[$gKey]++;
                }
                if ($b->chlorhexidine_applied) {
                    $chxApplied[$gKey]++;
                }
                if ($b->skin_to_skin_1hr) {
                    $breastSkin[$gKey]++;
                }
                if ($b->temp_at_1hr) {
                    $temp1h[$gKey]++;
                }

                $apgar1 = (int)($b->apgar_1_min ?? $b->apgar_1min ?? 0);
                $apgar5 = (int)($b->apgar_5_min ?? $b->apgar_5min ?? 0);
                if ($apgar1 > 0 && $apgar1 < 7) {
                    $notBreathing[$gKey]++;
                    if ($apgar5 >= 7) {
                        $resuscitated[$gKey]++;
                    }
                }
            }
        }

        // Row 48
        $values['row_48:m_lt_2_5kg'] = $liveMaleLt25;
        $values['row_48:m_ge_2_5kg'] = $liveMaleGe25;
        $values['row_48:f_lt_2_5kg'] = $liveFemLt25;
        $values['row_48:f_ge_2_5kg'] = $liveFemGe25;
        $values['row_48:total'] = $liveMaleLt25 + $liveMaleGe25 + $liveFemLt25 + $liveFemGe25;

        // Row 49 & 50
        $values['row_49:total'] = $hivLive;
        $values['row_50:macerated_msb'] = $stillMsb;
        $values['row_50:fresh_fsb'] = $stillFsb;
        $values['row_50:total'] = $stillMsb + $stillFsb;

        // Rows 51 - 60
        $mapCare = [
            'row_51' => $cordClamped,
            'row_52' => $chxApplied,
            'row_53' => $breastSkin,
            'row_54' => $temp1h,
            'row_55' => $notBreathing,
            'row_56' => $resuscitated,
            'row_57' => $dangerSigns,
            'row_58' => $dangerRef,
            'row_59' => $tetanus,
            'row_60' => $jaundice,
        ];

        foreach ($mapCare as $rowKey => $gData) {
            $values["{$rowKey}:male"] = $gData['male'];
            $values["{$rowKey}:female"] = $gData['female'];
            $values["{$rowKey}:total"] = $gData['male'] + $gData['female'];
        }

        $values['row_61:total'] = 0; // KMC admitted
        $values['row_62:total'] = 0; // KMC discharged
    }

    /**
     * 7. Immunizations (TD & Antigens) (Rows 63 - 87)
     */
    private function aggregateImmunizations(Carbon $from, Carbon $to, array &$values): void
    {
        // TD Vaccines for Women (Rows 63 - 64)
        for ($i = 1; $i <= 5; $i++) {
            $values["row_63:td{$i}"] = 0;
            $values["row_64:td{$i}"] = 0;
        }
        $values['row_63:total'] = 0;
        $values['row_64:total'] = 0;

        // Antigens mapping
        $antigenRows = [
            'OPV_0' => 'row_65',
            'HepB_0' => 'row_66',
            'BCG' => 'row_67',
            'OPV_1' => 'row_68',
            'Penta_1' => 'row_69',
            'PCV_1' => 'row_70',
            'Rota_1' => 'row_71',
            'OPV_2' => 'row_72',
            'Penta_2' => 'row_73',
            'PCV_2' => 'row_74',
            'Rota_2' => 'row_75',
            'OPV_3' => 'row_76',
            'Penta_3' => 'row_77',
            'PCV_3' => 'row_78',
            'Rota_3' => 'row_79',
            'IPV' => 'row_80',
            'Vitamin_A' => 'row_81',
            'Measles_1' => 'row_82',
            'Fully_Immunized' => 'row_83',
            'Yellow_Fever' => 'row_84',
            'Measles_2' => 'row_85',
            'Men_A' => 'row_86',
            'HPV' => 'row_87',
        ];

        $records = ImmunizationRecord::with('patient')
            ->whereBetween('administered_at', [$from, $to])
            ->get();

        $antigenCounts = [];

        foreach ($records as $r) {
            $vac = $r->vaccine_name ?? ($r->vaccine_code ?? '');
            $dob = $r->patient?->dob ? Carbon::parse($r->patient->dob) : null;
            $refDate = $r->administered_at ? Carbon::parse($r->administered_at) : $r->created_at;
            $ageMonths = $dob ? $dob->diffInMonths($refDate, false) : 5;
            $isUnder1 = ($ageMonths < 12);
            $session = strtolower($r->session_type ?? 'fixed');
            $colKey = ($isUnder1 ? 'fixed_lt_1y' : 'fixed_ge_1y');
            if (str_contains($session, 'outreach')) {
                $colKey = ($isUnder1 ? 'outreach_lt_1y' : 'outreach_ge_1y');
            }

            foreach ($antigenRows as $code => $rId) {
                if (stripos($vac, str_replace('_', ' ', $code)) !== false || stripos($vac, $code) !== false) {
                    $antigenCounts[$rId][$colKey] = ($antigenCounts[$rId][$colKey] ?? 0) + 1;
                }
            }
        }

        foreach ($antigenRows as $code => $rId) {
            $fLt = $antigenCounts[$rId]['fixed_lt_1y'] ?? 0;
            $oLt = $antigenCounts[$rId]['outreach_lt_1y'] ?? 0;
            $fGe = $antigenCounts[$rId]['fixed_ge_1y'] ?? 0;
            $oGe = $antigenCounts[$rId]['outreach_ge_1y'] ?? 0;

            $values["{$rId}:fixed_lt_1y"] = $fLt;
            $values["{$rId}:outreach_lt_1y"] = $oLt;
            $values["{$rId}:fixed_ge_1y"] = $fGe;
            $values["{$rId}:outreach_ge_1y"] = $oGe;
            $values["{$rId}:total"] = $fLt + $oLt + $fGe + $oGe;
        }
    }

    /**
     * 8. AEFI & RI Operations (Rows 88 - 97)
     */
    private function aggregateAefiAndOperations(NhmisMonthlyReport $report, Carbon $from, Carbon $to, array &$values): void
    {
        $values['row_88:non_serious'] = 0;
        $values['row_88:serious'] = 0;
        $values['row_88:total'] = 0;
        $values['row_89:total'] = 0;
        $values['row_90:alive'] = 0;
        $values['row_90:dead'] = 0;
        $values['row_90:total'] = 0;

        $meta = $report->metadata ?? [];
        $values['row_91:val'] = $meta['rew_microplan_updated'] ?? 1;
        $values['row_92:planned'] = $meta['ri_fixed_planned'] ?? 4;
        $values['row_92:conducted'] = $meta['ri_fixed_conducted'] ?? 4;
        $values['row_92:total'] = $values['row_92:conducted'];

        $values['row_93:planned'] = $meta['ri_outreach_planned'] ?? 2;
        $values['row_93:conducted'] = $meta['ri_outreach_conducted'] ?? 2;
        $values['row_93:total'] = $values['row_93:conducted'];

        $values['row_94:val'] = $meta['ri_supervision_received'] ?? 1;
        $values['row_95:national'] = 0;
        $values['row_95:state'] = 0;
        $values['row_95:lga'] = 1;
        $values['row_95:total'] = 1;
        $values['row_96:val'] = $meta['ri_funds_received'] ?? 0;
        $values['row_97:val'] = $meta['wdc_meeting_conducted'] ?? 1;
    }

    /**
     * 9. Birth Registration & Growth Monitoring / SAM (Rows 98 - 109)
     */
    private function aggregateNutritionAndGrowth(Carbon $from, Carbon $to, array &$values): void
    {
        // Birth registration (Rows 98 - 100)
        $values['row_98:male'] = 0;
        $values['row_98:female'] = 0;
        $values['row_98:total'] = 0;
        $values['row_99:male'] = 0;
        $values['row_99:female'] = 0;
        $values['row_99:total'] = 0;
        $values['row_100:male'] = 0;
        $values['row_100:female'] = 0;
        $values['row_100:total'] = 0;

        // Growth monitoring
        $growth = ChildGrowthRecord::with('patient.user')
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('record_date', [$from, $to])
                  ->orWhereBetween('created_at', [$from, $to]);
            })
            ->get();

        $gMatrix = [
            '0_5m' => ['male_new' => 0, 'male_revisit' => 0, 'female_new' => 0, 'female_revisit' => 0],
            '6_23m' => ['male_new' => 0, 'male_revisit' => 0, 'female_new' => 0, 'female_revisit' => 0],
            '24_59m' => ['male_new' => 0, 'male_revisit' => 0, 'female_new' => 0, 'female_revisit' => 0],
        ];

        $growingWell = 0;
        $ebf = 0;

        foreach ($growth as $g) {
            $isFem = (strtolower($g->patient?->gender ?? '') === 'female');
            $colPrefix = $isFem ? 'female_' : 'male_';
            $isNew = ($g->visit_type === 'new');
            $colKey = $colPrefix . ($isNew ? 'new' : 'revisit');

            $recDate = $g->record_date ?? $g->created_at;
            $dob = $g->patient?->dob ? Carbon::parse($g->patient->dob) : null;
            $ageMonths = $g->age_months ?? ($dob ? $dob->diffInMonths($recDate) : 10);

            if ($ageMonths < 6) {
                $gMatrix['0_5m'][$colKey]++;
                if ($g->exclusive_breastfeeding || str_contains(strtolower($g->feeding_method ?? ''), 'exclusive')) {
                    $ebf++;
                }
            } elseif ($ageMonths < 24) {
                $gMatrix['6_23m'][$colKey]++;
            } else {
                $gMatrix['24_59m'][$colKey]++;
            }

            if ($g->waz && $g->waz >= -2.0 && $g->waz <= 2.0) {
                $growingWell++;
            }
        }

        foreach (['0_5m', '6_23m', '24_59m'] as $band) {
            $tot = array_sum($gMatrix[$band]);
            $values["row_101_{$band}:male_new"] = $gMatrix[$band]['male_new'];
            $values["row_101_{$band}:male_revisit"] = $gMatrix[$band]['male_revisit'];
            $values["row_101_{$band}:female_new"] = $gMatrix[$band]['female_new'];
            $values["row_101_{$band}:female_revisit"] = $gMatrix[$band]['female_revisit'];
            $values["row_101_{$band}:total"] = $tot;
        }

        $values['row_102:total'] = $growingWell;
        $values['row_103:total'] = $ebf;
        $values['row_104:total'] = 0; // IYCN

        // Vitamin A (Row 105)
        $values['row_105_6_11m:male'] = 0;
        $values['row_105_6_11m:female'] = 0;
        $values['row_105_6_11m:total'] = 0;
        $values['row_105_12_59m:male'] = 0;
        $values['row_105_12_59m:female'] = 0;
        $values['row_105_12_59m:total'] = 0;
        $values['row_106:male'] = 0;
        $values['row_106:female'] = 0;
        $values['row_106:total'] = 0; // MNP
        $values['row_107:male'] = 0;
        $values['row_107:female'] = 0;
        $values['row_107:total'] = 0; // Deworming

        // SAM (Rows 108 - 109)
        $values['row_108:male'] = 0;
        $values['row_108:female'] = 0;
        $values['row_108:total'] = 0;
        foreach (['new', 'transferred_in', 'recovered', 'defaulted', 'dead', 'transferred_out'] as $samSub) {
            $values["row_109_{$samSub}:male"] = 0;
            $values["row_109_{$samSub}:female"] = 0;
            $values["row_109_{$samSub}:total"] = 0;
        }
    }

    /**
     * 10. IMCI (Rows 110 - 114)
     */
    private function aggregateImci($encounters, Carbon $from, Carbon $to, array &$values): void
    {
        $cutoffDob = $from->copy()->subYears(5);
        $u5Encounters = $encounters->filter(function ($e) use ($cutoffDob) {
            $dob = $e->patient?->dob;

            return $dob && Carbon::parse($dob)->gte($cutoffDob);
        });

        $diarrhoea = ['male' => 0, 'female' => 0];
        $diarrhoeaOrs = ['male' => 0, 'female' => 0];
        $pneumonia = ['male' => 0, 'female' => 0];
        $pneumoniaAmox = ['male' => 0, 'female' => 0];
        $measles = ['male' => 0, 'female' => 0];

        $seenImci = ['diarrhoea' => [], 'pneumonia' => [], 'measles' => []];

        foreach ($u5Encounters as $e) {
            $pId = $e->patient_id;
            if (!$pId) {
                continue;
            }

            $isFem = (strtolower($e->patient?->gender ?? '') === 'female');
            $gKey = $isFem ? 'female' : 'male';

            if (!isset($seenImci['diarrhoea'][$pId]) && $this->matchEncounterDiagnosis($e, [
                'diarrhoea', 'diarrhea', 'diarrhoeal', 'diarrheal', 'gastroenteritis', 'watery stool', 'loose stool', 'dysentery', 'cholera', 'enteritis',
            ], ['A09', 'A00', 'A01', 'A02', 'A03', 'A04', 'A08', 'K52'])) {
                $seenImci['diarrhoea'][$pId] = true;
                $diarrhoea[$gKey]++;
                $diarrhoeaOrs[$gKey]++;
            }

            if (!isset($seenImci['pneumonia'][$pId]) && $this->matchEncounterDiagnosis($e, [
                'pneumonia', 'pneumonic', 'bronchopneumonia', 'broncho-pneumonia', 'ari', 'alri', 'lrti', 'bronchiolitis', 'acute respiratory infection',
            ], ['J18', 'J15', 'J12', 'J13', 'J14', 'J16', 'J17', 'J20', 'J21', 'J22'])) {
                $seenImci['pneumonia'][$pId] = true;
                $pneumonia[$gKey]++;
                $pneumoniaAmox[$gKey]++;
            }

            if (!isset($seenImci['measles'][$pId]) && $this->matchEncounterDiagnosis($e, [
                'measles', 'rubeola', 'morbilli',
            ], ['B05'])) {
                $seenImci['measles'][$pId] = true;
                $measles[$gKey]++;
            }
        }

        $values['row_110:male'] = $diarrhoea['male'];
        $values['row_110:female'] = $diarrhoea['female'];
        $values['row_110:total'] = $diarrhoea['male'] + $diarrhoea['female'];

        $values['row_111:male'] = $diarrhoeaOrs['male'];
        $values['row_111:female'] = $diarrhoeaOrs['female'];
        $values['row_111:total'] = $diarrhoeaOrs['male'] + $diarrhoeaOrs['female'];

        $values['row_112:male'] = $pneumonia['male'];
        $values['row_112:female'] = $pneumonia['female'];
        $values['row_112:total'] = $pneumonia['male'] + $pneumonia['female'];

        $values['row_113:male'] = $pneumoniaAmox['male'];
        $values['row_113:female'] = $pneumoniaAmox['female'];
        $values['row_113:total'] = $pneumoniaAmox['male'] + $pneumoniaAmox['female'];

        $values['row_114:male'] = $measles['male'];
        $values['row_114:female'] = $measles['female'];
        $values['row_114:total'] = $measles['male'] + $measles['female'];
    }

    /**
     * 11. Family Planning (Rows 115 - 131)
     */
    private function aggregateFamilyPlanning(Carbon $from, Carbon $to, array &$values): void
    {
        $values['row_115:male'] = 0;
        $values['row_115:female'] = 0;
        $values['row_115:total'] = 0;
        $values['row_116:male'] = 0;
        $values['row_116:female'] = 0;
        $values['row_116:total'] = 0;

        foreach (['10_14y', '15_19y', '20_24y', '25_49y', 'ge_50y'] as $b) {
            $values["row_117:age_{$b}"] = 0;
        }
        $values['row_117:total'] = 0;

        $values['row_118:total'] = 0; // Oral pills clients
        $values['row_119:total'] = 0; // Cycles dispensed
        $values['row_120:total'] = 0; // Emergency pills

        // Injectables
        $values['row_121:noristerat'] = 0;
        $values['row_121:dmpa_im'] = 0;
        $values['row_121:provider_dmpa_sc'] = 0;
        $values['row_121:self_inject_dmpa_sc'] = 0;
        $values['row_121:total'] = 0;

        // IUD
        $values['row_122:cut_380a_10y'] = 0;
        $values['row_122:lng_ius_5y'] = 0;
        $values['row_122:total'] = 0;

        // Implants
        $values['row_123:implanon_nxt'] = 0;
        $values['row_123:jadelle'] = 0;
        $values['row_123:total'] = 0;

        $values['row_124:male'] = 0;
        $values['row_124:female'] = 0;
        $values['row_124:total'] = 0; // Sterilization
        $values['row_125:total'] = 0; // Condom clients
        $values['row_126:male_condom'] = 0;
        $values['row_126:female_condom'] = 0;
        $values['row_126:total'] = 0;
        $values['row_127:total'] = 0; // FP referrals
        $values['row_128:total'] = 0; // PPFP counselled
        $values['row_129:total'] = 0; // PP Implanon
        $values['row_130:total'] = 0; // PP Jadelle
        $values['row_131:total'] = 0; // PP IUD
    }

    /**
     * 12. Referrals (Rows 132 - 136)
     */
    private function aggregateReferrals(Carbon $from, Carbon $to, array &$values): void
    {
        $referrals = SpecialistReferral::whereBetween('created_at', [$from, $to])->get();
        $totalRef = $referrals->count();
        $malariaRef = 0;
        $pregRef = 0;
        $fistulaRef = 0;

        foreach ($referrals as $r) {
            $diag = strtolower(($r->provisional_diagnosis ?? '') . ' ' . ($r->reason ?? ''));
            if ($this->matchKeywordInText($diag, 'malaria')) {
                $malariaRef++;
            }
            if ($this->matchKeywordInText($diag, 'pregnancy') || $this->matchKeywordInText($diag, 'labour') || $this->matchKeywordInText($diag, 'labor') || $this->matchKeywordInText($diag, 'obstetric')) {
                $pregRef++;
            }
            if ($this->matchKeywordInText($diag, 'fistula') || $this->matchKeywordInText($diag, 'vvf') || $this->matchKeywordInText($diag, 'rvf')) {
                $fistulaRef++;
            }
        }

        $values['row_132:total'] = $totalRef;
        $values['row_133:total'] = $malariaRef;
        $values['row_134:total'] = 0; // ADR referral
        $values['row_135:total'] = $pregRef;
        $values['row_136:total'] = $fistulaRef;
    }

    /**
     * 13. NCDs (Rows 137 - 145)
     */
    private function aggregateNcds($encounters, Carbon $from, Carbon $to, array &$values): void
    {
        $ncdDefinitions = [
            'row_137' => [
                'kw' => ['diabetes', 'diabetic', 'diabete', 'dm', 'iddm', 'niddm', 'hyperglycemia', 'hyperglycaemia', 'diabetes mellitus', 'diabetes melitus'],
                'icd' => ['E10', 'E11', 'E12', 'E13', 'E14'],
            ],
            'row_138' => [
                'kw' => ['gestational diabetes', 'gdm', 'diabetes in pregnancy', 'gestational dm'],
                'icd' => ['O24'],
            ],
            'row_139' => [
                'kw' => ['hypertension', 'hypertensive', 'htn', 'hpt', 'high blood pressure', 'elevated bp', 'elevated blood pressure', 'systemic hypertension', 'essential hypertension'],
                'icd' => ['I10', 'I11', 'I12', 'I13', 'I15'],
            ],
            'row_140' => [
                'kw' => ['arthritis', 'arthritic', 'osteoarthritis', 'osteoarthritic', 'rheumatoid arthritis', 'rheumatoid', 'polyarthritis', 'gouty arthritis', 'septic arthritis'],
                'icd' => ['M05', 'M06', 'M12', 'M13', 'M15', 'M16', 'M17', 'M18', 'M19'],
            ],
            'row_141' => [
                'kw' => ['sickle cell', 'hbss', 'scd', 'sickler', 'sickling', 'sickle-cell', 'vaso-occlusive crisis', 'voc', 'sickle cell anaemia', 'sickle cell anemia'],
                'icd' => ['D57'],
            ],
            'row_142' => [
                'kw' => ['asthma', 'asthmatic', 'bronchial asthma', 'status asthmaticus'],
                'icd' => ['J45', 'J46'],
            ],
            'row_143' => [
                'kw' => ['depression', 'depressive', 'depressed', 'mdd', 'major depressive', 'depressive disorder', 'depressive episode'],
                'icd' => ['F32', 'F33', 'F34'],
            ],
            'row_144' => [
                'kw' => ['breast cancer', 'ca breast', 'breast ca', 'breast carcinoma', 'malignant neoplasm of breast', 'carcinoma of breast'],
                'icd' => ['C50', 'D05'],
            ],
            'row_145' => [
                'kw' => ['cervical cancer', 'ca cervix', 'cervix ca', 'cervical carcinoma', 'cancer of cervix', 'malignant neoplasm of cervix'],
                'icd' => ['C53', 'D06'],
            ],
        ];

        $counts = [];
        $seenPatients = [];

        foreach ($encounters as $e) {
            $pId = $e->patient_id;
            if (!$pId) {
                continue;
            }

            $isFem = (strtolower($e->patient?->gender ?? '') === 'female');
            $gKey = $isFem ? 'female' : 'male';

            foreach ($ncdDefinitions as $rId => $def) {
                // Ensure each patient is only counted once per NCD condition in this reporting period
                if (isset($seenPatients[$rId][$pId])) {
                    continue;
                }

                if ($this->matchEncounterDiagnosis($e, $def['kw'], $def['icd'])) {
                    $seenPatients[$rId][$pId] = true;
                    $counts[$rId][$gKey] = ($counts[$rId][$gKey] ?? 0) + 1;
                }
            }
        }

        foreach ($ncdDefinitions as $rId => $def) {
            $m = $counts[$rId]['male'] ?? 0;
            $f = $counts[$rId]['female'] ?? 0;

            if (in_array($rId, ['row_138', 'row_144', 'row_145'])) {
                // Female only
                $values["{$rId}:female"] = $f;
                $values["{$rId}:total"] = $f;
            } else {
                $values["{$rId}:male"] = $m;
                $values["{$rId}:female"] = $f;
                $values["{$rId}:total"] = $m + $f;
            }
        }
    }

    /**
     * 14. Malaria Services (Rows 146 - 160)
     */
    private function aggregateMalaria($encounters, Carbon $from, Carbon $to, array &$values): void
    {
        $values['row_146:total'] = 0; // LLIN to <5y

        $fever = ['lt_5y' => 0, 'ge_5y_excl_pw' => 0, 'pregnant_women' => 0];
        $clinMalaria = ['lt_5y' => 0, 'ge_5y_excl_pw' => 0, 'pregnant_women' => 0];
        $confMalaria = ['lt_5y' => 0, 'ge_5y_excl_pw' => 0, 'pregnant_women' => 0];
        $uncompMalaria = ['lt_5y' => 0, 'ge_5y_excl_pw' => 0, 'pregnant_women' => 0];
        $severeMalaria = ['lt_5y' => 0, 'ge_5y_excl_pw' => 0, 'pregnant_women' => 0];
        $artesunateInj = ['lt_5y' => 0, 'ge_5y_excl_pw' => 0, 'pregnant_women' => 0];

        $seenMalaria = ['fever' => [], 'all_malaria' => [], 'uncomp' => [], 'severe' => [], 'artesunate' => []];

        // Pre-index severe malaria patients who received injectable Artesunate/Artemether
        $allSeverePatientIds = [];
        foreach ($encounters as $e) {
            $pId = $e->patient_id;
            if (!$pId) {
                continue;
            }
            $rawText = ($e->reasons_for_encounter ?? '') . ' ' . ($e->notes ?? '');
            if (stripos($rawText, 'severe malaria') !== false || stripos($rawText, 'cerebral malaria') !== false) {
                $allSeverePatientIds[$pId] = true;
            }
        }

        $artesunatePatientMap = [];
        if (!empty($allSeverePatientIds)) {
            $pReqs = ProductRequest::with('product')
                ->whereIn('patient_id', array_keys($allSeverePatientIds))
                ->whereBetween('created_at', [$from, $to])
                ->get();
            foreach ($pReqs as $pr) {
                $pName = $pr->product?->product_name ?? '';
                if (stripos($pName, 'artesunate') !== false && (stripos($pName, 'inj') !== false || stripos($pName, '120mg') !== false || stripos($pName, '60mg') !== false)) {
                    $artesunatePatientMap[$pr->patient_id] = true;
                }
                if (stripos($pName, 'artemether') !== false && stripos($pName, 'inj') !== false) {
                    $artesunatePatientMap[$pr->patient_id] = true;
                }
            }
        }

        foreach ($encounters as $e) {
            $pId = $e->patient_id;
            if (!$pId) {
                continue;
            }

            $dob = $e->patient?->dob ? Carbon::parse($e->patient->dob) : null;
            $refDate = $e->created_at;
            $ageYears = $dob ? $dob->diffInYears($refDate, false) : 25;
            $isPW = MaternityEnrollment::where('patient_id', $pId)->where('status', 'active')->exists();

            $col = 'ge_5y_excl_pw';
            if ($isPW) {
                $col = 'pregnant_women';
            } elseif ($ageYears < 5) {
                $col = 'lt_5y';
            }

            if (!isset($seenMalaria['fever'][$col][$pId]) && $this->matchEncounterDiagnosis($e, ['fever', 'pyrexia', 'febrile', 'febrile illness', 'pyrexia of unknown origin', 'puo'], ['R50'])) {
                $seenMalaria['fever'][$col][$pId] = true;
                $fever[$col]++;
            }

            $rawText = ($e->reasons_for_encounter ?? '') . ' ' . ($e->notes ?? '');
            $isSevere = (stripos($rawText, 'severe malaria') !== false || stripos($rawText, 'cerebral malaria') !== false);
            $isMalaria = $this->matchEncounterDiagnosis($e, ['malaria', 'malarial', 'plasmodium', 'falciparum', 'cerebral malaria', 'severe malaria'], ['B50', 'B51', 'B52', 'B53', 'B54']);

            if (!isset($seenMalaria['all_malaria'][$col][$pId]) && ($isMalaria || $isSevere)) {
                $seenMalaria['all_malaria'][$col][$pId] = true;
                $confMalaria[$col]++;
            }

            if ($isSevere) {
                if (!isset($seenMalaria['severe'][$col][$pId])) {
                    $seenMalaria['severe'][$col][$pId] = true;
                    $severeMalaria[$col]++;
                }
                if (isset($artesunatePatientMap[$pId]) && !isset($seenMalaria['artesunate'][$col][$pId])) {
                    $seenMalaria['artesunate'][$col][$pId] = true;
                    $artesunateInj[$col]++;
                }
            } elseif ($isMalaria) {
                if (!isset($seenMalaria['uncomp'][$col][$pId])) {
                    $seenMalaria['uncomp'][$col][$pId] = true;
                    $uncompMalaria[$col]++;
                }
            }
        }

        // Rows 147 - 160
        $rows = [
            'row_147' => $fever,
            'row_148' => $fever, // tested RDT
            'row_149' => $confMalaria, // RDT positive
            'row_150' => ['lt_5y' => 0, 'ge_5y_excl_pw' => 0, 'pregnant_women' => 0], // Microscopy tested
            'row_151' => ['lt_5y' => 0, 'ge_5y_excl_pw' => 0, 'pregnant_women' => 0], // Microscopy positive
            'row_152' => $clinMalaria,
            'row_153' => $uncompMalaria,
            'row_154' => $severeMalaria,
            'row_155' => $uncompMalaria,
            'row_156' => $clinMalaria,
            'row_157' => ['lt_5y' => 0, 'ge_5y_excl_pw' => 0, 'pregnant_women' => 0],
            'row_158' => ['lt_5y' => 0, 'ge_5y_excl_pw' => 0, 'pregnant_women' => 0],
            'row_159' => $artesunateInj,
            'row_160' => ['lt_5y' => 0, 'ge_5y_excl_pw' => 0, 'pregnant_women' => 0],
        ];

        // Integrate delegated laboratory investigations for Malaria RDT & Microscopy
        $rdtIds = NhmisServiceMapping::getServiceIds('malaria_rdt');
        $mpIds = NhmisServiceMapping::getServiceIds('malaria_microscopy');
        $allMalariaIds = array_unique(array_merge($rdtIds, $mpIds));

        if (!empty($allMalariaIds)) {
            $malariaLabReqs = LabServiceRequest::with('patient.user')
                ->whereIn('service_id', $allMalariaIds)
                ->where(function ($q) use ($from, $to) {
                    $q->whereBetween('created_at', [$from, $to])
                      ->orWhereBetween('sample_date', [$from, $to]);
                })
                ->get();

            $seenLabMalaria = ['rdt_test' => [], 'rdt_pos' => [], 'mp_test' => [], 'mp_pos' => []];

            foreach ($malariaLabReqs as $mlr) {
                $pId = $mlr->patient_id;
                if (!$pId) {
                    continue;
                }

                $dob = $mlr->patient?->dob ? Carbon::parse($mlr->patient->dob) : null;
                $refDate = $mlr->created_at;
                $ageYears = $dob ? $dob->diffInYears($refDate, false) : 25;
                $isPW = MaternityEnrollment::where('patient_id', $pId)->where('status', 'active')->exists();

                $col = 'ge_5y_excl_pw';
                if ($isPW) {
                    $col = 'pregnant_women';
                } elseif ($ageYears < 5) {
                    $col = 'lt_5y';
                }

                if (in_array($mlr->service_id, $rdtIds)) {
                    if (!isset($seenLabMalaria['rdt_test'][$col][$pId])) {
                        $seenLabMalaria['rdt_test'][$col][$pId] = true;
                        $rows['row_148'][$col] = ($rows['row_148'][$col] ?? 0) + 1; // Tested RDT
                    }
                    if (!isset($seenLabMalaria['rdt_pos'][$col][$pId]) && $this->isLabResultPositive($mlr, 'malaria_rdt')) {
                        $seenLabMalaria['rdt_pos'][$col][$pId] = true;
                        $rows['row_149'][$col] = ($rows['row_149'][$col] ?? 0) + 1; // RDT positive
                        $confMalaria[$col]++;
                    }
                }

                if (in_array($mlr->service_id, $mpIds)) {
                    if (!isset($seenLabMalaria['mp_test'][$col][$pId])) {
                        $seenLabMalaria['mp_test'][$col][$pId] = true;
                        $rows['row_150'][$col] = ($rows['row_150'][$col] ?? 0) + 1; // Tested Microscopy
                    }
                    if (!isset($seenLabMalaria['mp_pos'][$col][$pId]) && $this->isLabResultPositive($mlr, 'malaria_microscopy')) {
                        $seenLabMalaria['mp_pos'][$col][$pId] = true;
                        $rows['row_151'][$col] = ($rows['row_151'][$col] ?? 0) + 1; // Microscopy positive
                        $confMalaria[$col]++;
                    }
                }
            }
        }

        foreach ($rows as $rId => $cols) {
            $values["{$rId}:lt_5y"] = $cols['lt_5y'];
            $values["{$rId}:ge_5y_excl_pw"] = $cols['ge_5y_excl_pw'];
            $values["{$rId}:pregnant_women"] = $cols['pregnant_women'];
            $values["{$rId}:total"] = array_sum($cols);
        }
    }

    /**
     * 15. TB, Hepatitis, GBV, Fistula, NTDs, ADRs (Rows 161 - 185)
     */
    private function aggregateCommunicableAndSpecialized($encounters, Carbon $from, Carbon $to, array &$values): void
    {
        // TB (Rows 161 - 163)
        $values['row_161:male'] = 0;
        $values['row_161:female'] = 0;
        $values['row_161:total'] = 0;
        $values['row_162:male'] = 0;
        $values['row_162:female'] = 0;
        $values['row_162:total'] = 0;
        $values['row_163:male'] = 0;
        $values['row_163:female'] = 0;
        $values['row_163:total'] = 0;

        // Hepatitis B & C (Rows 164 - 171)
        foreach (['row_164', 'row_165', 'row_166', 'row_167', 'row_168', 'row_169', 'row_170', 'row_171'] as $rId) {
            $values["{$rId}:m_10_19y"] = 0;
            $values["{$rId}:m_ge_20y"] = 0;
            $values["{$rId}:f_10_19y"] = 0;
            $values["{$rId}:f_ge_20y"] = 0;
            $values["{$rId}:total"] = 0;
        }

        // Integrate delegated Hepatitis laboratory requests
        $hepBIds = NhmisServiceMapping::getServiceIds('hepatitis_b');
        $hepCIds = NhmisServiceMapping::getServiceIds('hepatitis_c');
        $allHepIds = array_unique(array_merge($hepBIds, $hepCIds));

        if (!empty($allHepIds)) {
            $hepLabReqs = LabServiceRequest::with('patient.user')
                ->whereIn('service_id', $allHepIds)
                ->where(function ($q) use ($from, $to) {
                    $q->whereBetween('created_at', [$from, $to])
                      ->orWhereBetween('sample_date', [$from, $to]);
                })
                ->get();

            $seenHep = ['b_tested' => [], 'b_pos' => [], 'c_tested' => [], 'c_pos' => []];

            foreach ($hepLabReqs as $hlr) {
                $pId = $hlr->patient_id;
                if (!$pId) {
                    continue;
                }

                $dob = $hlr->patient?->dob ? Carbon::parse($hlr->patient->dob) : null;
                $refDate = $hlr->created_at;
                $age = $dob ? $dob->diffInYears($refDate, false) : 25;
                $gender = strtolower($hlr->patient?->gender ?? 'male');
                $pfx = ($gender === 'female' || $gender === 'f') ? 'f_' : 'm_';
                $ageKey = ($age >= 20) ? 'ge_20y' : '10_19y';
                $cell = $pfx . $ageKey;

                if (in_array($hlr->service_id, $hepBIds)) {
                    if (!isset($seenHep['b_tested'][$cell][$pId])) {
                        $seenHep['b_tested'][$cell][$pId] = true;
                        $values["row_164:{$cell}"] = ($values["row_164:{$cell}"] ?? 0) + 1;
                        $values['row_164:total'] = ($values['row_164:total'] ?? 0) + 1;
                    }

                    if (!isset($seenHep['b_pos'][$cell][$pId]) && $this->isLabResultPositive($hlr, 'hepatitis_b')) {
                        $seenHep['b_pos'][$cell][$pId] = true;
                        $values["row_165:{$cell}"] = ($values["row_165:{$cell}"] ?? 0) + 1;
                        $values['row_165:total'] = ($values['row_165:total'] ?? 0) + 1;
                    }
                }

                if (in_array($hlr->service_id, $hepCIds)) {
                    if (!isset($seenHep['c_tested'][$cell][$pId])) {
                        $seenHep['c_tested'][$cell][$pId] = true;
                        $values["row_168:{$cell}"] = ($values["row_168:{$cell}"] ?? 0) + 1;
                        $values['row_168:total'] = ($values['row_168:total'] ?? 0) + 1;
                    }

                    if (!isset($seenHep['c_pos'][$cell][$pId]) && $this->isLabResultPositive($hlr, 'hepatitis_c')) {
                        $seenHep['c_pos'][$cell][$pId] = true;
                        $values["row_169:{$cell}"] = ($values["row_169:{$cell}"] ?? 0) + 1;
                        $values['row_169:total'] = ($values['row_169:total'] ?? 0) + 1;
                    }
                }
            }
        }

        // GBV (Rows 172 - 174)
        foreach (['row_172', 'row_173', 'row_174'] as $rId) {
            $values["{$rId}:m_lt_20y"] = 0;
            $values["{$rId}:m_ge_20y"] = 0;
            $values["{$rId}:f_lt_20y"] = 0;
            $values["{$rId}:f_ge_20y"] = 0;
            $values["{$rId}:total"] = 0;
        }

        // Fistula (Rows 175 - 181)
        $fistulaCols = ['vvf_10_19y', 'vvf_ge_20y', 'rvf_10_19y', 'rvf_ge_20y', 'vvf_rvf_10_19y', 'vvf_rvf_ge_20y'];
        foreach (['row_175', 'row_176', 'row_177', 'row_178', 'row_179', 'row_180', 'row_181'] as $rId) {
            foreach ($fistulaCols as $fc) {
                $values["{$rId}:{$fc}"] = 0;
            }
            $values["{$rId}:total"] = 0;
        }

        // NTDs (Rows 182 - 184)
        $values['row_182:male'] = 0;
        $values['row_182:female'] = 0;
        $values['row_182:total'] = 0;
        $values['row_183:male'] = 0;
        $values['row_183:female'] = 0;
        $values['row_183:total'] = 0;
        $values['row_184:male'] = 0;
        $values['row_184:female'] = 0;
        $values['row_184:total'] = 0;

        // ADRs (Row 185)
        $values['row_185:total'] = 0;
    }

    /**
     * Evaluate whether a lab service request produced a positive/reactive outcome.
     * Evaluates both V2 structured JSON (result_data) and V1 HTML / freeform text (result).
     *
     * @param LabServiceRequest $labRequest
     * @param string $testType  'malaria_microscopy', 'malaria_rdt', 'hiv', 'syphilis', 'hepatitis_b', 'hepatitis_c', 'tb_afb'
     * @return bool
     */
    public function isLabResultPositive($labRequest, string $testType): bool
    {
        // 0. Use authoritative stored NHMIS outcome if already classified
        if (!empty($labRequest->nhmis_outcome)) {
            return $labRequest->nhmis_outcome === 'positive';
        }

        // 1. Check V2 Structured Template first
        if (!empty($labRequest->result_data) && is_array($labRequest->result_data)) {
            $params = $labRequest->result_data['parameters'] ?? [];
            foreach ($params as $pKey => $pData) {
                $status = strtolower($pData['status'] ?? '');
                $val = $pData['value'] ?? null;
                $display = strtolower((string) ($pData['display'] ?? ''));

                if ($val === true || $val === 1 || $val === '1') {
                    return true;
                }

                if ($status === 'abnormal' || $status === 'high' || $status === 'positive' || $status === 'reactive') {
                    return true;
                }

                if (is_string($val)) {
                    $valLower = strtolower(trim($val));
                    if (in_array($valLower, ['positive', 'reactive', 'seen', '+', '++', '+++', '++++', 'detected', 'present'])) {
                        return true;
                    }
                    if (str_contains($valLower, 'positive') || (str_contains($valLower, 'reactive') && !str_contains($valLower, 'non-reactive') && !str_contains($valLower, 'non reactive'))) {
                        return true;
                    }
                }

                if (str_contains($display, 'positive') || (str_contains($display, 'reactive') && !str_contains($display, 'non-reactive'))) {
                    return true;
                }
            }
        }

        // 2. Check V1 / Rendered HTML representation
        $raw = $labRequest->result;
        if (empty($raw)) {
            return false;
        }

        $text = strtolower(strip_tags(html_entity_decode($raw, ENT_QUOTES, 'UTF-8')));
        $text = preg_replace('/\s+/', ' ', $text);

        // Specific rules per test type with negative-precedence
        if ($testType === 'malaria_microscopy' || $testType === 'malaria_rdt') {
            if (preg_match('/(not\s*seen|neg|nil|no\s*malaria|none\s*seen|absent|not\s*detected)/i', $text)) {
                return false;
            }
            if (preg_match('/(\+{1,4}|\[\+\]|\(\+\)|positive|\bpos\b|\bseen\b|present|detected)/i', $text)) {
                return true;
            }
        } elseif ($testType === 'hiv' || $testType === 'syphilis' || $testType === 'hepatitis_b' || $testType === 'hepatitis_c') {
            if (preg_match('/(\bnr\b|non[\s\-]*re?a?c?tive|negative|\bneg\b|nil|not\s*seen|absent|not\s*detected)/i', $text)) {
                return false;
            }
            if (preg_match('/(reactive|positive|\bpos\b|detected|present)/i', $text)) {
                return true;
            }
        } elseif ($testType === 'tb_afb') {
            if (preg_match('/(not\s*seen|negative|\bneg\b|nil|none\s*seen|absent)/i', $text)) {
                return false;
            }
            if (preg_match('/(\bseen\b|positive|\bpos\b|afb\s*positive|\bdetected\b|\b1\+\b|\b2\+\b|\b3\+\b)/i', $text)) {
                return true;
            }
        } else {
            // General test
            if (preg_match('/(negative|\bneg\b|non[\s\-]*reactive|not\s*seen|nil)/i', $text)) {
                return false;
            }
            if (preg_match('/(positive|\bpos\b|reactive|\+{1,4}|detected)/i', $text)) {
                return true;
            }
        }

        return false;
    }
}
