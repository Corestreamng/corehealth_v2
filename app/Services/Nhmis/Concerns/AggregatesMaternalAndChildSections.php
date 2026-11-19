<?php

namespace App\Services\Nhmis\Concerns;

use App\Models\ImmunizationRecord;
use App\Models\NhmisServiceMapping;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Antenatal, delivery, postnatal, newborn, immunization, nutrition, IMCI and family planning rows.
 */
trait AggregatesMaternalAndChildSections
{
    protected function pregnancyStartOn(?int $pid, $date): ?Carbon
    {
        $this->isPregnantOn($pid, $date);
        $at = Carbon::parse($date);
        foreach ($this->pregnancyIndex[$pid] ?? [] as [$start, $end]) {
            if ($at->between($start, $end)) {
                return $start;
            }
        }

        return null;
    }

    /* ---------------------------------------------------------------
     | Rows 10-33: antenatal care
     * --------------------------------------------------------------- */
    protected function aggregateAntenatalCare(): void
    {
        $visits = DB::table('anc_visits as v')
            ->leftJoin('maternity_enrollments as e', 'e.id', '=', 'v.enrollment_id')
            ->whereNull('v.deleted_at')
            ->whereBetween('v.visit_date', [$this->from, $this->to])
            ->orderBy('v.visit_date')
            ->get(['v.*', 'e.patient_id as enrol_patient_id', 'e.lmp', 'e.gestational_age_at_booking']);
        $pidOf = fn ($v) => $v->patient_id ?: $v->enrol_patient_id;
        $this->loadPatients($visits->map($pidOf));

        $firstVisitSeen = [];
        $seen = ['r12' => [], 'r13' => [], 'r14' => [], 'r15' => [], 'r16' => [], 'r32' => [], 'r33' => []];
        foreach ($visits as $v) {
            $pid = $pidOf($v);
            $age = $this->ageYears($pid, $v->visit_date);
            $col = match (true) {
                $age === null || $age < 10 => null,
                $age < 15 => 'age_10_14y',
                $age < 20 => 'age_15_19y',
                $age < 35 => 'age_20_35y',
                $age < 50 => 'age_35_49y',
                default => 'age_ge_50y',
            };
            $this->add('row_10', $col, 'ANC Visit', $v->id, $pid, $v->visit_date, 'ANC visit ' . ($v->visit_number ?? '') . ' (' . ($v->visit_type ?? 'routine') . ')');

            $enrolKey = $v->enrollment_id ?: ('p' . $pid);
            if (($v->visit_type === 'booking' || (int) $v->visit_number === 1) && !isset($firstVisitSeen[$enrolKey])) {
                $firstVisitSeen[$enrolKey] = true;
                $ga = $v->gestational_age_weeks ?? $v->gestational_age_at_booking;
                if ($ga === null && $v->lmp) {
                    $ga = intdiv((int) Carbon::parse($v->lmp)->diffInDays(Carbon::parse($v->visit_date)), 7);
                }
                $gaCol = $ga === null ? null : ((float) $ga < 20 ? 'ga_lt_20wks' : 'ga_ge_20wks');
                $this->add('row_11', $gaCol, 'ANC Visit', $v->id, $pid, $v->visit_date, 'First ANC visit' . ($ga !== null ? " at {$ga} weeks" : ' (gestational age not recorded)'));
            }

            if ($pid && (int) $v->visit_number === 4 && !isset($seen['r12'][$pid])) {
                $seen['r12'][$pid] = true;
                $this->add('row_12', 'total', 'ANC Visit', $v->id, $pid, $v->visit_date, '4th ANC contact');
            }
            if ($pid && (int) $v->visit_number === 8 && !isset($seen['r13'][$pid])) {
                $seen['r13'][$pid] = true;
                $this->add('row_13', 'total', 'ANC Visit', $v->id, $pid, $v->visit_date, '8th ANC contact');
            }

            $notes = self::cleanText(implode(' . ', [$v->clinical_notes, $v->treatment, $v->plan, $v->notes, $v->examination_notes]));
            $counsel = [
                'r14' => ['row_14', '/\b(fgm|female\s+genital\s+(mutilation|cutting))\b/', 'Counselled on FGM'],
                'r15' => ['row_15', '/\b(family\s+planning|child\s+spacing|contracepti\w*|post\s*-?\s*partum\s+fp|ppfp)\b/', 'Counselled on family planning'],
                'r16' => ['row_16', '/\b(nutrition\w*|balanced\s+diet|dietary\s+(advice|counsel\w*)|diet\s+counsel\w*)\b/', 'Counselled on maternal nutrition'],
            ];
            foreach ($counsel as $k => [$row, $re, $label]) {
                if ($pid && !isset($seen[$k][$pid]) && preg_match($re, $notes)) {
                    $seen[$k][$pid] = true;
                    $this->add($row, 'total', 'ANC Visit', $v->id, $pid, $v->visit_date, $label);
                }
            }

            if ($pid && !isset($seen['r32'][$pid]) && is_numeric($v->haemoglobin) && (float) $v->haemoglobin > 0 && (float) $v->haemoglobin < 7) {
                $seen['r32'][$pid] = true;
                $this->add('row_32', 'total', 'ANC Visit', $v->id, $pid, $v->visit_date, "Haemoglobin {$v->haemoglobin} g/dL");
            }
            $prot = trim(self::cleanText($v->urine_protein), ' .');
            if ($pid && !isset($seen['r33'][$pid]) && preg_match('/^(\+{1,4}|[1-4]\s*\+|positive|pos)$/', $prot)) {
                $seen['r33'][$pid] = true;
                $this->add('row_33', 'total', 'ANC Visit', $v->id, $pid, $v->visit_date, "Urine protein {$prot}");
            }
        }

        // Rows 17-25: ANC screening tests (women pregnant on the test date)
        $tests = [
            'syphilis_vdrl' => ['row_17', 'row_18', 'Syphilis'],
            'hepatitis_b' => ['row_20', 'row_21', 'Hepatitis B'],
            'hepatitis_c' => ['row_23', 'row_24', 'Hepatitis C'],
        ];
        $positives = [];
        $this->requireLinks('syphilis_vdrl', ['row_19'], 'syphilis');
        $this->requireLinks('hepatitis_b', ['row_22'], 'hepatitis B');
        $this->requireLinks('hepatitis_c', ['row_25'], 'hepatitis C');
        foreach ($tests as $ind => [$doneRow, $posRow, $label]) {
            if (!$this->requireLinks($ind, [$doneRow, $posRow], $label)) {
                continue;
            }
            $tested = [];
            foreach ($this->resultedLabs($ind, $this->from, $this->to) as $lr) {
                $pid = $lr->patient_id;
                $date = self::labDate($lr);
                if (!$pid || !$this->isPregnantOn($pid, $date)) {
                    continue;
                }
                if (!isset($tested[$pid])) {
                    $tested[$pid] = true;
                    $this->add($doneRow, 'total', 'Lab Request', $lr->id, $pid, $date, ($lr->service->service_name ?? $label) . ' done');
                }
                if (!isset($positives[$ind][$pid]) && $this->isLabResultPositive($lr, $ind === 'syphilis_vdrl' ? 'syphilis' : $ind)) {
                    $positives[$ind][$pid] = [$date, $lr->id];
                    $this->add($posRow, 'total', 'Lab Request', $lr->id, $pid, $date, ($lr->service->service_name ?? $label) . ' positive');
                }
            }
        }
        $syphTx = $this->dispensed(
            ['penicillin', 'benzathine', 'erythromycin', 'azithromycin', 'ceftriaxone', 'rocephin', 'ocexone'],
            '/(penicillin|benzathine|erythromycin|azithromycin|ceftriaxone|rocephin|ocexone)/',
            array_keys($positives['syphilis_vdrl'] ?? []),
            $this->from,
            $this->to,
            '/oint/'
        );
        foreach ($syphTx->unique('patient_id') as $r) {
            $this->add('row_19', 'total', 'Dispensed', $r->id, $r->patient_id, $r->dt, 'Treated: ' . $r->product_name);
        }
        foreach (['hepatitis_b' => 'row_22', 'hepatitis_c' => 'row_25'] as $ind => $row) {
            foreach (array_keys($this->externalReferralPatients(array_keys($positives[$ind] ?? []))) as $pid) {
                [$date, $labId] = $positives[$ind][$pid];
                $this->add($row, 'total', 'Lab Request', $labId, $pid, $date, 'Positive and referred out');
            }
        }

        // Rows 26-29: IPTp-SP doses (dose number = SP dispenses in this pregnancy up to this one)
        $spLike = ['fansidar', 'sulfadoxine', 'pyrimethamine', 'maloxine', 'amalar', 'laridox', 'swidar'];
        $spRe = '/(fansidar|sulfadoxine|pyrimethamine|maloxine|amalar|laridox|swidar)/';
        $current = $this->dispensed($spLike, $spRe, null, $this->from, $this->to)->filter(fn ($r) => $this->isPregnantOn($r->patient_id, $r->dt));
        $history = $this->dispensed($spLike, $spRe, $current->pluck('patient_id')->unique()->all(), $this->from->copy()->subMonths(10), $this->to)->groupBy('patient_id');
        foreach ($current as $r) {
            $start = $this->pregnancyStartOn($r->patient_id, $r->dt);
            $n = $history[$r->patient_id]->filter(fn ($h) => Carbon::parse($h->dt)->gte($start) && Carbon::parse($h->dt)->lte(Carbon::parse($r->dt)))
                ->map(fn ($h) => substr($h->dt, 0, 10))->unique()->count();
            $row = match (true) {
                $n <= 1 => 'row_26',
                $n === 2 => 'row_27',
                $n === 3 => 'row_28',
                default => 'row_29',
            };
            $this->add($row, 'total', 'Dispensed', $r->id, $r->patient_id, $r->dt, "IPTp dose {$n}: {$r->product_name}");
        }

        // Row 30: LLIN; Row 31: iron / folic acid
        $llin = [['llin', 'bed net', 'mosquito net', 'insecticid'], '/\b(llin|bed\s*net|mosquito\s*net|insecticid\w*\s*(treated)?\s*net)\b/'];
        if (!$this->productExists($llin[0], $llin[1])) {
            $this->markManual('row_30', 'No LLIN/bed net product exists in the pharmacy catalogue.');
        } else {
            foreach ($this->dispensed($llin[0], $llin[1], null, $this->from, $this->to)->unique('patient_id') as $r) {
                if ($this->isPregnantOn($r->patient_id, $r->dt)) {
                    $this->add('row_30', 'total', 'Dispensed', $r->id, $r->patient_id, $r->dt, $r->product_name);
                }
            }
        }
        $ifa = $this->dispensed(
            ['ferrous', 'fersolat', 'folic', 'fefol', 'ranferon', 'chemiron', 'haematinic', 'hematinic'],
            '/\b(ferrous|fersolat\w*|folic|fefol|ranferon|chemiron|ha?ematinic\w*)\b/',
            null,
            $this->from,
            $this->to
        )
            ->filter(fn ($r) => $this->isPregnantOn($r->patient_id, $r->dt))->unique('patient_id');
        foreach ($ifa as $r) {
            $this->add('row_31', 'total', 'Dispensed', $r->id, $r->patient_id, $r->dt, $r->product_name);
        }
    }

    /* ---------------------------------------------------------------
     | Rows 36-46: labour & delivery
     * --------------------------------------------------------------- */
    protected function aggregateLabourAndDelivery(): void
    {
        $deliveries = DB::table('delivery_records as d')
            ->leftJoin('maternity_enrollments as e', 'e.id', '=', 'd.enrollment_id')
            ->whereNull('d.deleted_at')
            ->whereBetween('d.delivery_date', [$this->from, $this->to])
            ->get(['d.*', 'e.patient_id as enrol_patient_id', 'e.lmp']);
        $pidOf = fn ($d) => $d->patient_id ?: $d->enrol_patient_id;
        $this->loadPatients($deliveries->map($pidOf));
        $partographIds = DB::table('delivery_partograph')->whereIn('delivery_record_id', $deliveries->pluck('id'))->pluck('delivery_record_id')->unique()->flip();
        $miso = $this->dispensed(['misoprostol', 'cytotec'], '/(misoprostol|cytotec)/', $deliveries->map($pidOf)->filter()->values()->all(), $this->from->copy()->subDay(), $this->to->copy()->addDay(), '/arthrotec|diclofenac/')->groupBy('patient_id');
        $deliveredPatients = [];

        foreach ($deliveries as $d) {
            $pid = $pidOf($d);
            $deliveredPatients[$pid] = true;
            $type = strtolower((string) $d->type_of_delivery);
            $col = match (true) {
                (bool) preg_match('/(^|_)cs$|^cs|caesar|cesar|c-section|c_section/', $type) => 'c_section',
                (bool) preg_match('/assist|vacuum|forceps|ventouse/', $type) => 'assisted',
                (bool) preg_match('/svd|normal|vaginal|spontaneous/', $type) => 'svd',
                default => null,
            };
            $label = 'Delivery: ' . ($d->type_of_delivery ?: 'type not recorded');
            $this->add('row_36', $col, 'Delivery', $d->id, $pid, $d->delivery_date, $label);

            if ($d->lmp) {
                $weeks = Carbon::parse($d->lmp)->diffInDays(Carbon::parse($d->delivery_date)) / 7;
                if ($weeks >= 20 && $weeks < 37) {
                    $this->add('row_37', 'total', 'Delivery', $d->id, $pid, $d->delivery_date, sprintf('Preterm birth at %.1f weeks (from LMP)', $weeks));
                }
            }

            $comp = trim(self::cleanText($d->complications), " .-");
            if ($comp !== '' && !preg_match('/^(nil+|none|no|nad|n\/?a|no complications?)$/', $comp)) {
                $this->add('row_38', 'total', 'Delivery', $d->id, $pid, $d->delivery_date, 'Complication: ' . $comp);
            }

            $age = $this->ageYears($pid, $d->delivery_date);
            if ($age !== null && $age >= 10 && $age <= 19) {
                $this->add('row_39', 'total', 'Delivery', $d->id, $pid, $d->delivery_date, "Mother aged {$age}");
            }
            if (isset($partographIds[$d->id])) {
                $this->add('row_40', 'total', 'Delivery', $d->id, $pid, $d->delivery_date, 'Partograph entries recorded');
            }
            if ($d->delivered_by) {
                $this->add('row_41', 'total', 'Delivery', $d->id, $pid, $d->delivery_date, 'Delivered by a registered clinical staff member');
            }
            if ($d->oxytocin_given) {
                $this->add('row_42', 'oxytocin', 'Delivery', $d->id, $pid, $d->delivery_date, 'Oxytocin given');
            } elseif (isset($miso[$pid])) {
                $r = $miso[$pid]->first();
                $this->add('row_42', 'misoprostol', 'Dispensed', $r->id, $pid, $r->dt, $r->product_name);
            }
        }

        // Caesarean sections recorded only as theatre procedures (no delivery record)
        $csIds = NhmisServiceMapping::getServiceIds('caesarean_section');
        if ($csIds) {
            foreach ($this->completedProcedures($csIds) as $p) {
                if (!isset($deliveredPatients[$p->patient_id])) {
                    $deliveredPatients[$p->patient_id] = true;
                    $this->add('row_36', 'c_section', 'Procedure', $p->id, $p->patient_id, $p->dt, $p->service_name . ' (theatre record)');
                }
            }
        }

        // Row 43: MgSO4
        $mg = [['magnesium', 'mgso4'], '/(magnesium\s*sul|mgso4)/'];
        if (!$this->productExists($mg[0], $mg[1])) {
            $this->markManual('row_43', 'No magnesium sulphate product exists in the pharmacy catalogue.');
        } else {
            foreach ($this->dispensed($mg[0], $mg[1], null, $this->from, $this->to)->unique('patient_id') as $r) {
                if ($this->isPregnantOrPostpartum($r->patient_id, $r->dt)) {
                    $this->add('row_43', 'total', 'Dispensed', $r->id, $r->patient_id, $r->dt, $r->product_name);
                }
            }
        }

        // Row 44: abortions (MVA), each procedure counted once; Row 45: PAC
        $spon = NhmisServiceMapping::getServiceIds('mva_spontaneous');
        $ind = NhmisServiceMapping::getServiceIds('mva_induced');
        foreach ($this->completedProcedures(array_unique(array_merge($spon, $ind))) as $p) {
            $inS = in_array($p->service_id, $spon);
            $inI = in_array($p->service_id, $ind);
            $name = strtolower((string) $p->service_name);
            $col = ($inS && !$inI) ? 'spontaneous' : (($inI && !$inS) ? 'induced'
                : (str_contains($name, 'induc') ? 'induced' : (str_contains($name, 'spontan') ? 'spontaneous' : null)));
            $this->add('row_44', $col, 'Procedure', $p->id, $p->patient_id, $p->dt, $p->service_name);
        }
        foreach ($this->completedProcedures(NhmisServiceMapping::getServiceIds('mva_pac')) as $p) {
            $this->add('row_45', 'total', 'Procedure', $p->id, $p->patient_id, $p->dt, $p->service_name);
        }

        // Row 46: admissions for complications of unsafe abortion
        $abKw = ['septic abortion', 'unsafe abortion', 'induced abortion', 'incomplete abortion', 'criminal abortion', 'post abortion sepsis', 'post-abortion sepsis', 'post abortal sepsis'];
        $adms = DB::table('admission_requests as a')->leftJoin('encounters as e', 'e.id', '=', 'a.encounter_id')
            ->whereRaw('COALESCE(a.bed_assign_date, a.created_at) BETWEEN ? AND ?', [$this->from, $this->to])
            ->get(['a.id', 'a.patient_id', 'a.created_at', 'a.admission_reason', 'e.id as enc_id', 'e.reasons_for_encounter', 'e.reasons_for_encounter_comment_1', 'e.reasons_for_encounter_comment_2', 'e.notes']);
        foreach ($adms as $a) {
            $reason = self::cleanText($a->admission_reason);
            $hit = (bool) array_filter($abKw, fn ($k) => $this->matchKeywordInText($reason, $k));
            if (!$hit && $a->enc_id) {
                $hit = $this->diagnosisStatus((object) ['id' => 'adm' . $a->enc_id, 'reasons_for_encounter' => $a->reasons_for_encounter, 'reasons_for_encounter_comment_1' => $a->reasons_for_encounter_comment_1, 'reasons_for_encounter_comment_2' => $a->reasons_for_encounter_comment_2, 'notes' => $a->notes], $abKw, ['O08']) === 'confirmed';
            }
            if ($hit && $this->sexOf($a->patient_id) !== 'm') {
                $this->add('row_46', 'total', 'Admission', $a->id, $a->patient_id, $a->created_at, 'Admitted: ' . ($reason ?: 'abortion complication'));
            }
        }
    }

    protected function completedProcedures(array $serviceIds)
    {
        if (!$serviceIds) {
            return collect();
        }

        return DB::table('procedures as pr')->leftJoin('services as s', 's.id', '=', 'pr.service_id')
            ->whereNull('pr.deleted_at')->whereIn('pr.service_id', $serviceIds)->where('pr.procedure_status', 'completed')
            ->whereRaw('COALESCE(pr.actual_end_time, pr.updated_at) BETWEEN ? AND ?', [$this->from, $this->to])
            ->get(['pr.id', 'pr.patient_id', 'pr.service_id', 'pr.outcome', 's.service_name', DB::raw('COALESCE(pr.actual_end_time, pr.updated_at) as dt')]);
    }

    /* ---------------------------------------------------------------
     | Row 47: postnatal visits by days after delivery
     * --------------------------------------------------------------- */
    protected function aggregatePostnatalCare(): void
    {
        $visits = DB::table('postnatal_visits as v')
            ->whereNull('v.deleted_at')
            ->whereBetween('v.visit_date', [$this->from, $this->to])
            ->select(['v.id', 'v.patient_id', 'v.visit_date', 'v.days_postpartum', 'v.baby_weight_kg', 'v.baby_general_condition', 'v.baby_feeding', 'v.cord_status', 'v.jaundice', 'v.family_planning_counselled', 'v.family_planning_method'])
            ->selectSub(DB::table('delivery_records as d')->whereColumn('d.enrollment_id', 'v.enrollment_id')->whereNull('d.deleted_at')->selectRaw('MIN(d.delivery_date)'), 'delivered_on')
            ->get();

        foreach ($visits as $v) {
            $days = $v->days_postpartum !== null ? (int) $v->days_postpartum
                : ($v->delivered_on ? (int) Carbon::parse($v->delivered_on)->startOfDay()->diffInDays(Carbon::parse($v->visit_date)->startOfDay()) : null);
            $bucket = $days === null ? null : ($days <= 1 ? '1d' : ($days <= 3 ? '2_3d' : ($days <= 7 ? '4_7d' : 'gt_7d')));
            $label = 'Postnatal visit' . ($days !== null ? " on day {$days}" : ' (days after delivery not recorded)');
            $this->add('row_47', $bucket ? "mother_{$bucket}" : null, 'Postnatal Visit', $v->id, $v->patient_id, $v->visit_date, 'Mother: ' . $label);
            if ($v->baby_weight_kg !== null || $v->baby_general_condition || $v->baby_feeding || $v->cord_status) {
                $this->add('row_47', $bucket ? "baby_{$bucket}" : null, 'Postnatal Visit', $v->id, $v->patient_id, $v->visit_date, 'Baby: ' . $label);
            }
        }

        $this->postnatalVisits = $visits;
    }

    protected $postnatalVisits;

    /* ---------------------------------------------------------------
     | Rows 48-60: newborn health
     * --------------------------------------------------------------- */
    protected function aggregateNewbornHealth(): void
    {
        $babies = DB::table('maternity_babies as b')
            ->leftJoin('maternity_enrollments as e', 'e.id', '=', 'b.enrollment_id')
            ->leftJoin('delivery_records as d', function ($j) {
                $j->on('d.enrollment_id', '=', 'b.enrollment_id')->whereNull('d.deleted_at');
            })
            ->whereNull('b.deleted_at')
            ->groupBy('b.id')
            ->havingRaw('COALESCE(MIN(d.delivery_date), MIN(b.created_at)) BETWEEN ? AND ?', [$this->from, $this->to])
            ->get(['b.id', DB::raw('MIN(b.patient_id) as patient_id'), DB::raw('MIN(b.sex) as sex'), DB::raw('MIN(b.birth_weight_kg) as birth_weight_kg'), DB::raw('MIN(b.status) as status'), DB::raw('MAX(b.is_still_birth) as is_still_birth'),
                DB::raw('MIN(b.apgar_1_min) as apgar_1_min'), DB::raw('MIN(b.apgar_5_min) as apgar_5_min'), DB::raw('MIN(b.resuscitation) as resuscitation'), DB::raw('MIN(b.notes) as notes'), DB::raw('MIN(b.cause_of_death) as cause_of_death'),
                DB::raw('MIN(b.bcg_given) as bcg_given'), DB::raw('MIN(b.opv0_given) as opv0_given'), DB::raw('MIN(b.hbv0_given) as hbv0_given'),
                DB::raw('MIN(e.patient_id) as mother_id'), DB::raw('COALESCE(MIN(d.delivery_date), MIN(b.created_at)) as born_on')]);

        $mothers = $babies->pluck('mother_id')->filter()->unique()->values()->all();
        $hivMothers = $this->hivPositivePatients($mothers, $this->to);
        $stillPatients = [];
        $this->birthDoseBabies = $babies;

        foreach ($babies as $b) {
            $sex = self::normaliseSex($b->sex);
            $isStill = $b->is_still_birth || str_contains(strtolower((string) $b->status), 'still');
            if ($isStill) {
                $txt = strtolower($b->notes . ' ' . $b->cause_of_death);
                $col = preg_match('/macerat|\bmsb\b/', $txt) ? 'macerated_msb' : (preg_match('/fresh|\bfsb\b/', $txt) ? 'fresh_fsb' : null);
                $this->add('row_50', $col, 'Baby Record', $b->id, $b->mother_id, $b->born_on, 'Still birth');
                $stillPatients[$b->patient_id ?? 0] = true;

                continue;
            }

            $wt = $b->birth_weight_kg !== null ? (float) $b->birth_weight_kg : null;
            $col = ($sex && $wt) ? $sex . '_' . ($wt < 2.5 ? 'lt_2_5kg' : 'ge_2_5kg') : null;
            $this->add('row_48', $col, 'Baby Record', $b->id, $b->mother_id, $b->born_on, 'Live birth' . ($wt ? ", {$wt} kg" : ', weight not recorded'));
            if (isset($hivMothers[$b->mother_id])) {
                $this->add('row_49', 'total', 'Baby Record', $b->id, $b->mother_id, $b->born_on, 'Mother HIV positive');
            }

            $sexCol = $sex === 'm' ? 'male' : ($sex === 'f' ? 'female' : null);
            if ($b->apgar_1_min !== null && (int) $b->apgar_1_min < 7) {
                $this->add('row_55', $sexCol, 'Baby Record', $b->id, $b->mother_id, $b->born_on, "APGAR {$b->apgar_1_min} at 1 minute");
                if (($b->apgar_5_min !== null && (int) $b->apgar_5_min >= 7) && !str_contains(strtolower((string) $b->status), 'deceased')) {
                    $this->add('row_56', $sexCol, 'Baby Record', $b->id, $b->mother_id, $b->born_on, "APGAR {$b->apgar_1_min} → {$b->apgar_5_min} at 5 minutes");
                }
            }
        }

        // Still births entered only in the death register
        $deathStill = DB::table('death_records')->whereNull('deleted_at')->where('death_type', 'like', '%still%')
            ->whereRaw('COALESCE(date_of_death, created_at) BETWEEN ? AND ?', [$this->from, $this->to])
            ->get(['id', 'patient_id', 'cause_of_death_description', DB::raw('COALESCE(date_of_death, created_at) as dt')]);
        foreach ($deathStill as $d) {
            if (!isset($stillPatients[$d->patient_id ?? -1])) {
                $txt = strtolower((string) $d->cause_of_death_description);
                $col = preg_match('/macerat|\bmsb\b/', $txt) ? 'macerated_msb' : (preg_match('/fresh|\bfsb\b/', $txt) ? 'fresh_fsb' : null);
                $this->add('row_50', $col, 'Death Record', $d->id, $d->patient_id, $d->dt, 'Still birth (death register)');
            }
        }

        // Rows 59-60: neonatal tetanus & jaundice diagnosed in babies under 28 days
        $neo = ['row_59' => [['neonatal tetanus', 'tetanus neonatorum'], ['A33']], 'row_60' => [['neonatal jaundice', 'jaundice', 'nnj', 'hyperbilirubinaemia', 'hyperbilirubinemia'], ['P59']]];
        foreach ($neo as $rowId => [$kws, $icd]) {
            $seen = [];
            foreach ($this->encounters() as $e) {
                $pid = $e->patient_id;
                if (!$pid || isset($seen[$pid])) {
                    continue;
                }
                $days = $this->ageDays($pid, $e->created_at);
                if ($days !== null && $days < 28 && $this->diagnosisStatus($e, $kws, $icd) === 'confirmed') {
                    $seen[$pid] = true;
                    $this->add($rowId, $this->sexCol($pid), 'Encounter', $e->id, $pid, $e->created_at, $this->diagnosisLabel($e));
                }
            }
        }
        foreach ($this->postnatalVisits ?? [] as $v) {
            $j = strtolower(trim((string) $v->jaundice));
            if ($j !== '' && !in_array($j, ['0', 'no', 'none', 'nil', 'absent', 'false'], true)) {
                $this->add('row_60', null, 'Postnatal Visit', $v->id, $v->patient_id, $v->visit_date, 'Baby jaundice noted at postnatal visit');
            }
        }
    }

    protected $birthDoseBabies;

    /** Mothers with a positive HIV test or an HIV diagnosis on record before $until. */
    protected function hivPositivePatients(array $patientIds, Carbon $until): array
    {
        if (!$patientIds) {
            return [];
        }
        $pos = [];
        $ids = array_merge(NhmisServiceMapping::getServiceIds('hiv_screening'), NhmisServiceMapping::getServiceIds('hiv_confirmatory'));
        if ($ids) {
            $labs = \App\Models\LabServiceRequest::whereIn('service_id', $ids)->whereIn('patient_id', $patientIds)->where('created_at', '<=', $until)
                ->get(['id', 'patient_id', 'result', 'result_data', 'nhmis_outcome']);
            foreach ($labs as $lr) {
                if ($this->isLabResultPositive($lr, 'hiv')) {
                    $pos[$lr->patient_id] = true;
                }
            }
        }

        return $pos + $this->patientsWithPriorDiagnosis(array_values(array_diff($patientIds, array_keys($pos))), ['hiv', 'rvd', 'retroviral disease', 'pmtct', 'pitc positive'], ['B20', 'B24', 'Z21'], $until);
    }

    /* ---------------------------------------------------------------
     | Rows 63-88: immunization
     * --------------------------------------------------------------- */
    protected function aggregateImmunizations(): void
    {
        $records = ImmunizationRecord::query()
            ->where(function ($q) {
                $q->whereBetween('administered_at', [$this->from, $this->to])
                  ->orWhere(fn ($q2) => $q2->whereNull('administered_at')->whereBetween('created_at', [$this->from, $this->to]));
            })
            ->get();
        $this->loadPatients($records->pluck('patient_id'));
        $given = [];

        foreach ($records as $r) {
            $date = $r->administered_at ?? $r->created_at;
            $count = max(1, (int) ($r->headcount ?? 1));
            $label = trim($r->vaccine_name . ' ' . ($r->dose ?? ($r->dose_number !== null ? 'dose ' . $r->dose_number : '')));

            if (!empty(trim((string) $r->adverse_reaction))) {
                $this->add('row_88', null, 'Immunization', $r->id, $r->patient_id, $date, 'AEFI: ' . $r->adverse_reaction);
            }

            $pregnant = $r->patient_id ? $this->isPregnantOn($r->patient_id, $date) : null;
            if ($td = self::resolveTdRowAndDose($r, $pregnant)) {
                $this->add($td['row_id'], $td['col_key'], 'Immunization', $r->id, $r->patient_id, $date, $label, $count);

                continue;
            }

            $row = self::resolveAntigenRow($r);
            if (!$row) {
                continue;
            }
            $months = $this->ageMonths($r->patient_id, $date);
            if ($months !== null) {
                $under1 = $months < 12;
            } else {
                $ag = strtolower((string) $r->age_group) . ' ' . strtolower((string) $r->target_group);
                $under1 = preg_match('/<\s*1|0\s*-\s*11|lt_1y|infant/', $ag) ? true : (preg_match('/ge_1y|12\s*-\s*59|5\s*-\s*9|9\s*-\s*14|15\s*-\s*49|under_5|adolescent|adult/', $ag) ? false : null);
            }
            $outreach = str_contains(strtolower((string) $r->session_type), 'outreach') || $r->outreach_session_id;
            $col = $under1 === null ? null : (($outreach ? 'outreach_' : 'fixed_') . ($under1 ? 'lt_1y' : 'ge_1y'));
            $this->add($row, $col, 'Immunization', $r->id, $r->patient_id, $date, $label . ($outreach ? ' (outreach)' : ''), $count);
            if ($r->patient_id) {
                $given[$r->patient_id][$row] = true;
            }
        }

        // Birth doses recorded on the maternity baby record (not also entered as immunizations)
        $patientsWithAny = DB::table('immunization_records')->whereNull('deleted_at')
            ->whereIn('patient_id', collect($this->birthDoseBabies ?? [])->pluck('patient_id')->filter()->all())
            ->get(['patient_id', 'vaccine_name', 'dose', 'dose_number']);
        $hadDose = [];
        foreach ($patientsWithAny as $r) {
            $row = self::resolveAntigenRow((new ImmunizationRecord())->forceFill((array) $r));
            if ($row) {
                $hadDose[$r->patient_id][$row] = true;
            }
        }
        foreach ($this->birthDoseBabies ?? [] as $b) {
            if ($b->is_still_birth) {
                continue;
            }
            foreach (['bcg_given' => ['row_67', 'BCG'], 'opv0_given' => ['row_65', 'OPV 0'], 'hbv0_given' => ['row_66', 'Hepatitis B 0']] as $field => [$row, $label]) {
                if ($b->{$field} && !isset($hadDose[$b->patient_id ?? 0][$row])) {
                    $this->add($row, 'fixed_lt_1y', 'Baby Record', $b->id, $b->mother_id, $b->born_on, "{$label} given at birth (delivery record)");
                }
            }
        }
    }

    /* ---------------------------------------------------------------
     | Rows 101-108: growth monitoring & nutrition
     * --------------------------------------------------------------- */
    protected function aggregateNutritionAndGrowth(): void
    {
        $growth = DB::table('child_growth_records')->whereNull('deleted_at')
            ->whereBetween('record_date', [$this->from, $this->to])->orderBy('record_date')
            ->get(['id', 'patient_id', 'baby_id', 'record_date', 'age_months', 'weight_for_age_z', 'feeding_method', 'dietary_notes'])
            // Birth anthropometry on the newborn record is not a growth-monitoring session
            ->reject(fn ($g) => $g->baby_id && (float) $g->age_months === 0.0);
        $this->loadPatients($growth->pluck('patient_id'));
        $earlier = DB::table('child_growth_records')->whereNull('deleted_at')->whereIn('patient_id', $growth->pluck('patient_id')->filter()->unique()->all())
            ->where('record_date', '<', $this->from)->pluck('patient_id')->flip();
        $firstInPeriod = [];
        $seen = ['102' => [], '103' => [], '104' => []];

        foreach ($growth as $g) {
            $pid = $g->patient_id;
            $months = $g->age_months !== null ? (int) $g->age_months : $this->ageMonths($pid, $g->record_date);
            if ($months === null || $months > 59) {
                continue;
            }
            $isNew = !isset($earlier[$pid]) && !isset($firstInPeriod[$pid]);
            $firstInPeriod[$pid] = true;
            $sex = $this->sexOf($pid);
            $row = $months < 6 ? 'row_101_0_5m' : ($months < 24 ? 'row_101_6_23m' : 'row_101_24_59m');
            $col = $sex ? ($sex === 'm' ? 'male_' : 'female_') . ($isNew ? 'new' : 'revisit') : null;
            $this->add($row, $col, 'Growth Record', $g->id, $pid, $g->record_date, ($isNew ? 'First' : 'Repeat') . " GMP visit, {$months} months");

            $waz = $g->weight_for_age_z;
            if ($waz !== null && !isset($seen['102'][$pid]) && (float) $waz >= -2 && (float) $waz <= 2) {
                $seen['102'][$pid] = true;
                $this->add('row_102', 'total', 'Growth Record', $g->id, $pid, $g->record_date, "Weight-for-age z-score {$waz}");
            }
            if ($months < 6 && !isset($seen['103'][$pid]) && str_contains(strtolower((string) $g->feeding_method), 'exclusive')) {
                $seen['103'][$pid] = true;
                $this->add('row_103', 'total', 'Growth Record', $g->id, $pid, $g->record_date, 'Exclusive breastfeeding');
            }
            if (!isset($seen['104'][$pid]) && trim(strip_tags((string) $g->dietary_notes)) !== '') {
                $seen['104'][$pid] = true;
                $this->add('row_104', 'total', 'Growth Record', $g->id, $pid, $g->record_date, 'IYCN counselling noted');
            }
        }

        // Row 105: vitamin A (immunization record or dispensed capsule)
        $vitA = [];
        foreach (ImmunizationRecord::whereBetween('administered_at', [$this->from, $this->to])->where('vaccine_name', 'like', '%vitamin a%')->get() as $r) {
            $vitA[] = [$r->patient_id, $r->administered_at, 'Immunization', $r->id, 'Vitamin A (immunization record)'];
        }
        foreach ($this->dispensed(['vitamin a', 'retinol'], '/(vitamin\s*a\b|retinol)/', null, $this->from, $this->to) as $r) {
            $vitA[] = [$r->patient_id, $r->dt, 'Dispensed', $r->id, $r->product_name];
        }
        $seenVa = [];
        foreach ($vitA as [$pid, $date, $src, $id, $label]) {
            $m = $this->ageMonths($pid, $date);
            if ($m === null || $m < 6 || $m > 59 || isset($seenVa[$pid])) {
                continue;
            }
            $seenVa[$pid] = true;
            $this->add($m < 12 ? 'row_105_6_11m' : 'row_105_12_59m', $this->sexCol($pid), $src, $id, $pid, $date, $label);
        }

        if (!$this->productExists(['micronutrient powder', 'mnp'], '/(micronutrient\s*powder|\bmnp\b)/')) {
            $this->markManual('row_106', 'No micronutrient powder product exists in the pharmacy catalogue.');
        } else {
            foreach ($this->dispensed(['micronutrient powder', 'mnp'], '/(micronutrient\s*powder|\bmnp\b)/', null, $this->from, $this->to)->unique('patient_id') as $r) {
                $m = $this->ageMonths($r->patient_id, $r->dt);
                if ($m !== null && $m >= 6 && $m <= 23) {
                    $this->add('row_106', $this->sexCol($r->patient_id), 'Dispensed', $r->id, $r->patient_id, $r->dt, $r->product_name);
                }
            }
        }

        foreach ($this->dispensed(['albendazole', 'mebendazole', 'vermox', 'zentel'], '/(albendazole|mebendazole|vermox|zentel)/', null, $this->from, $this->to)->unique('patient_id') as $r) {
            $m = $this->ageMonths($r->patient_id, $r->dt);
            if ($m !== null && $m >= 12 && $m <= 59) {
                $this->add('row_107', $this->sexCol($r->patient_id), 'Dispensed', $r->id, $r->patient_id, $r->dt, $r->product_name);
            }
        }

        // Row 108: under-5 admissions for severe acute malnutrition
        $samKw = ['severe acute malnutrition', 'sam', 'kwashiorkor', 'marasmus', 'marasmic', 'marasmic kwashiorkor'];
        $adms = DB::table('admission_requests as a')->leftJoin('encounters as e', 'e.id', '=', 'a.encounter_id')
            ->whereRaw('COALESCE(a.bed_assign_date, a.created_at) BETWEEN ? AND ?', [$this->from, $this->to])
            ->get(['a.id', 'a.patient_id', 'a.created_at', 'a.admission_reason', 'e.id as enc_id', 'e.reasons_for_encounter', 'e.reasons_for_encounter_comment_1', 'e.reasons_for_encounter_comment_2', 'e.notes']);
        $this->loadPatients($adms->pluck('patient_id'));
        foreach ($adms as $a) {
            $age = $this->ageYears($a->patient_id, $a->created_at);
            if ($age === null || $age >= 5) {
                continue;
            }
            $reason = self::cleanText($a->admission_reason);
            $hit = (bool) array_filter($samKw, fn ($k) => $this->matchKeywordInText($reason, $k));
            if (!$hit && $a->enc_id) {
                $hit = $this->diagnosisStatus((object) ['id' => 'adm' . $a->enc_id, 'reasons_for_encounter' => $a->reasons_for_encounter, 'reasons_for_encounter_comment_1' => $a->reasons_for_encounter_comment_1, 'reasons_for_encounter_comment_2' => $a->reasons_for_encounter_comment_2, 'notes' => $a->notes], $samKw, ['E40', 'E41', 'E42', 'E43']) === 'confirmed';
            }
            if ($hit) {
                $this->add('row_108', $this->sexCol($a->patient_id), 'Admission', $a->id, $a->patient_id, $a->created_at, 'Admitted for SAM');
            }
        }
    }

    /* ---------------------------------------------------------------
     | Rows 110-114: IMCI new cases in under-5s (no same diagnosis in the previous 14 days)
     * --------------------------------------------------------------- */
    protected function aggregateImci(): void
    {
        $defs = [
            'row_110' => [['diarrhoea', 'diarrhea', 'diarrhoeal disease', 'gastroenteritis', 'acute gastroenteritis', 'dysentery', 'cholera', 'enteritis', 'loose stool', 'watery stool'], ['A00', 'A03', 'A06', 'A09', 'K52']],
            'row_112' => [['pneumonia', 'bronchopneumonia', 'broncho-pneumonia', 'lobar pneumonia', 'lrti', 'lower respiratory tract infection'], ['J12', 'J13', 'J14', 'J15', 'J16', 'J18']],
            'row_114' => [['measles', 'rubeola'], ['B05']],
        ];
        $cases = [];
        foreach ($defs as $rowId => [$kws, $icd]) {
            $first = [];
            foreach ($this->encounters() as $e) {
                $pid = $e->patient_id;
                if (!$pid || isset($first[$pid])) {
                    continue;
                }
                $age = $this->ageYears($pid, $e->created_at);
                if ($age === null || $age >= 5) {
                    continue;
                }
                if ($this->diagnosisStatus($e, $kws, $icd) === 'confirmed') {
                    $first[$pid] = $e;
                }
            }
            $recent = $this->patientsWithPriorDiagnosis(array_keys($first), $kws, $icd, $this->from, $this->from->copy()->subDays(14));
            foreach ($first as $pid => $e) {
                if (isset($recent[$pid])) {
                    continue;
                }
                $cases[$rowId][$pid] = $e;
                $this->add($rowId, $this->sexCol($pid), 'Encounter', $e->id, $pid, $e->created_at, 'New case: ' . $this->diagnosisLabel($e));
            }
        }

        $window = fn ($e, $rows) => $rows->filter(fn ($r) => Carbon::parse($r->dt)->between(Carbon::parse($e->created_at)->subDay(), Carbon::parse($e->created_at)->addDays(3)));
        $diar = array_keys($cases['row_110'] ?? []);
        $ors = $this->dispensed(['oral rehydration', 'ors'], '/(oral\s*rehydration|\bors\b)/', $diar, $this->from->copy()->subDay(), $this->to->copy()->addDays(3))->groupBy('patient_id');
        $zinc = $this->dispensed(['zinc'], '/\bzinc\b/', $diar, $this->from->copy()->subDay(), $this->to->copy()->addDays(3), '/oxide|anusol/')->groupBy('patient_id');
        foreach ($cases['row_110'] ?? [] as $pid => $e) {
            $o = isset($ors[$pid]) ? $window($e, $ors[$pid]) : collect();
            $z = isset($zinc[$pid]) ? $window($e, $zinc[$pid]) : collect();
            if ($o->isNotEmpty() && $z->isNotEmpty()) {
                $this->add('row_111', $this->sexCol($pid), 'Dispensed', $o->first()->id, $pid, $o->first()->dt, $o->first()->product_name . ' + ' . $z->first()->product_name);
            }
        }
        $pneu = array_keys($cases['row_112'] ?? []);
        $amox = $this->dispensed(['amoxi', 'amoxy'], '/amox[iy]/', $pneu, $this->from->copy()->subDay(), $this->to->copy()->addDays(3), '/esofag|clarithro/')->groupBy('patient_id');
        foreach ($cases['row_112'] ?? [] as $pid => $e) {
            $a = isset($amox[$pid]) ? $window($e, $amox[$pid]) : collect();
            if ($a->isNotEmpty()) {
                $this->add('row_113', $this->sexCol($pid), 'Dispensed', $a->first()->id, $pid, $a->first()->dt, $a->first()->product_name);
            }
        }
    }

    /* ---------------------------------------------------------------
     | Rows 115-131: family planning (pharmacy dispensing + postnatal FP fields)
     * --------------------------------------------------------------- */
    protected function aggregateFamilyPlanning(): void
    {
        $methods = [
            'oral' => [['levofem', 'microgynon', 'combined oral', 'exluton', 'microlut', 'oral contracep'], '/(levofem|microgynon|combined\s*oral|exluton|microlut|oral\s*contracep)/'],
            'ec' => [['postinor', 'emergency contracep', 'levonorgestrel 1.5'], '/(postinor|emergency\s*contracep|levonorgestrel\s*1\.5)/'],
            'noristerat' => [['noristerat'], '/noristerat/'],
            'dmpa_im' => [['depo', 'dmpa'], '/(depo[\s-]*provera|\bdmpa\b)/'],
            'dmpa_sc' => [['sayana', 'dmpa-sc', 'dmpa sc'], '/(sayana|dmpa[\s-]*sc)/'],
            'iud' => [['iucd', 'copper t', 'iud'], '/(iucd|copper\s*t\b|\biud\b|cu[\s-]*t\s*380)/'],
            'lng_ius' => [['mirena', 'lng-ius', 'lng ius'], '/(mirena|lng[\s-]*ius)/'],
            'implanon' => [['implanon'], '/implanon/'],
            'jadelle' => [['jadelle'], '/jadelle/'],
            'male_condom' => [['condom'], '/condom/'],
            'female_condom' => [['female condom', 'femidom'], '/(female\s*condom|femidom)/'],
        ];
        $cells = [
            'oral' => ['row_118', 'row_119'], 'ec' => ['row_120'], 'noristerat' => ['row_121:noristerat'], 'dmpa_im' => ['row_121:dmpa_im'],
            'dmpa_sc' => ['row_121:provider_dmpa_sc', 'row_121:self_inject_dmpa_sc'], 'iud' => ['row_122:cut_380a_10y'], 'lng_ius' => ['row_122:lng_ius_5y'],
            'implanon' => ['row_123:implanon_nxt'], 'jadelle' => ['row_123:jadelle'], 'male_condom' => ['row_125', 'row_126:male_condom'], 'female_condom' => ['row_126:female_condom'],
        ];
        $methodNames = [
            'oral' => 'oral contraceptive pill', 'ec' => 'emergency contraceptive pill', 'noristerat' => 'Noristerat',
            'dmpa_im' => 'DMPA-IM (Depo-Provera)', 'dmpa_sc' => 'DMPA-SC (Sayana Press)', 'iud' => 'copper IUD',
            'lng_ius' => 'LNG-IUS', 'implanon' => 'Implanon NXT', 'jadelle' => 'Jadelle',
            'male_condom' => 'male condom', 'female_condom' => 'female condom',
        ];
        $all = collect();
        foreach ($methods as $m => [$likes, $re]) {
            if (!$this->productExists($likes, $re, $m === 'male_condom' ? '/female|femidom/' : null)) {
                foreach ($cells[$m] as $c) {
                    $this->markManual($c, 'No ' . $methodNames[$m] . ' product exists in the pharmacy catalogue.');
                }

                continue;
            }
            $rows = $this->dispensed($likes, $re, null, $this->from, $this->to, $m === 'male_condom' ? '/female|femidom/' : null);
            foreach ($rows as $r) {
                $r->method = $m;
                $all->push($r);
            }
        }
        $this->markManual('row_121:self_inject_dmpa_sc', 'Provider-given and self-injected DMPA-SC are not distinguished in pharmacy records.');

        $this->loadPatients($all->pluck('patient_id'));
        foreach ($all as $r) {
            $pid = $r->patient_id;
            $qty = max(1, (float) $r->qty);
            switch ($r->method) {
                case 'oral':
                    $this->add('row_119', 'total', 'Dispensed', $r->id, $pid, $r->dt, "{$r->product_name} x{$qty}", $qty);

                    break;
                case 'ec':
                    $this->add('row_120', 'total', 'Dispensed', $r->id, $pid, $r->dt, $r->product_name, $qty);

                    break;
                case 'noristerat':
                case 'dmpa_im':
                    $this->add('row_121', $r->method, 'Dispensed', $r->id, $pid, $r->dt, $r->product_name);

                    break;
                case 'dmpa_sc':
                    $this->add('row_121', 'provider_dmpa_sc', 'Dispensed', $r->id, $pid, $r->dt, $r->product_name);

                    break;
                case 'iud':
                    $this->add('row_122', 'cut_380a_10y', 'Dispensed', $r->id, $pid, $r->dt, $r->product_name);

                    break;
                case 'lng_ius':
                    $this->add('row_122', 'lng_ius_5y', 'Dispensed', $r->id, $pid, $r->dt, $r->product_name);

                    break;
                case 'implanon':
                    $this->add('row_123', 'implanon_nxt', 'Dispensed', $r->id, $pid, $r->dt, $r->product_name);

                    break;
                case 'jadelle':
                    $this->add('row_123', 'jadelle', 'Dispensed', $r->id, $pid, $r->dt, $r->product_name);

                    break;
                case 'male_condom':
                case 'female_condom':
                    $this->add('row_126', $r->method, 'Dispensed', $r->id, $pid, $r->dt, "{$r->product_name} x{$qty}", $qty);

                    break;
            }
        }
        foreach ($all->where('method', 'oral')->unique('patient_id') as $r) {
            $this->add('row_118', 'total', 'Dispensed', $r->id, $r->patient_id, $r->dt, $r->product_name);
        }
        foreach ($all->whereIn('method', ['male_condom', 'female_condom'])->unique('patient_id') as $r) {
            $this->add('row_125', 'total', 'Dispensed', $r->id, $r->patient_id, $r->dt, $r->product_name);
        }

        // Rows 116-117: acceptors and users of a modern method (condoms excluded from "females using")
        $clients = $all->filter(fn ($r) => $r->patient_id)->sortBy('dt')->unique('patient_id');
        $prior = [];
        if ($clients->isNotEmpty()) {
            $priorRows = collect();
            foreach ($methods as $m => [$likes, $re]) {
                $priorRows = $priorRows->merge($this->dispensed($likes, $re, $clients->pluck('patient_id')->all(), Carbon::create(2000), $this->from->copy()->subSecond()));
            }
            $prior = $priorRows->pluck('patient_id')->flip()->all();
        }
        foreach ($clients as $r) {
            $pid = $r->patient_id;
            if (!isset($prior[$pid])) {
                $this->add('row_116', $this->sexCol($pid), 'Dispensed', $r->id, $pid, $r->dt, 'First family planning method: ' . $r->product_name);
            }
        }
        foreach ($all->filter(fn ($r) => $r->patient_id && !in_array($r->method, ['male_condom'], true))->unique('patient_id') as $r) {
            if ($this->sexOf($r->patient_id) !== 'f') {
                continue;
            }
            $age = $this->ageYears($r->patient_id, $r->dt);
            $col = match (true) {
                $age === null || $age < 10 => null,
                $age < 15 => 'age_10_14y',
                $age < 20 => 'age_15_19y',
                $age < 25 => 'age_20_24y',
                $age < 50 => 'age_25_49y',
                default => 'age_ge_50y',
            };
            $this->add('row_117', $col, 'Dispensed', $r->id, $r->patient_id, $r->dt, $r->product_name);
        }

        // Row 124: voluntary sterilisation (linked theatre procedures)
        foreach (['vasectomy' => 'male', 'tubal_ligation' => 'female'] as $ind => $col) {
            foreach ($this->completedProcedures(NhmisServiceMapping::getServiceIds($ind))->unique('patient_id') as $p) {
                $this->add('row_124', $col, 'Procedure', $p->id, $p->patient_id, $p->dt, $p->service_name);
            }
        }

        // Rows 115, 128-131: counselling and postpartum methods from postnatal visits & ANC notes
        $counselled = [];
        foreach ($this->postnatalVisits ?? [] as $v) {
            if ($v->family_planning_counselled && $v->patient_id && !isset($counselled[$v->patient_id])) {
                $counselled[$v->patient_id] = true;
                $this->add('row_128', 'total', 'Postnatal Visit', $v->id, $v->patient_id, $v->visit_date, 'Counselled on postpartum family planning');
                $this->add('row_115', $this->sexCol($v->patient_id), 'Postnatal Visit', $v->id, $v->patient_id, $v->visit_date, 'Family planning counselling (postnatal)');
            }
            $method = strtolower((string) $v->family_planning_method);
            $ppRow = str_contains($method, 'implanon') ? 'row_129' : (str_contains($method, 'jadelle') ? 'row_130' : (preg_match('/iucd|\biud\b|copper/', $method) ? 'row_131' : null));
            if ($ppRow) {
                $this->add($ppRow, 'total', 'Postnatal Visit', $v->id, $v->patient_id, $v->visit_date, 'Postpartum method: ' . $v->family_planning_method);
            }
        }
        foreach ($this->evidence['row_15:total'] ?? [] as $ev) {
            if ($ev['patient_id'] && !isset($counselled[$ev['patient_id']])) {
                $counselled[$ev['patient_id']] = true;
                $this->add('row_115', $this->sexCol($ev['patient_id']), $ev['src'], $ev['id'], $ev['patient_id'], $ev['date'], 'Family planning counselling (antenatal)');
            }
        }
    }
}
