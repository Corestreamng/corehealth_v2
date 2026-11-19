<?php

namespace App\Services\Nhmis\Concerns;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Attendance, admissions, mortality, referrals, NCDs, malaria, TB, hepatitis, GBV,
 * fistula and NTD rows. Each count is built from evidence records via $this->add().
 */
trait AggregatesClinicalSections
{
    /* ---------------------------------------------------------------
     | Rows 1-4: attendance (one per patient per day) & inpatient care
     * --------------------------------------------------------------- */
    protected function aggregateAttendanceAndInpatient(): void
    {
        // Encounters do not carry their admission link, so an encounter is inpatient when it
        // falls after the patient's admission was created and before discharge.
        $stays = [];
        $stayRows = DB::table('admission_requests')
            ->where('created_at', '<=', $this->to)
            ->where(function ($q) {
                $q->whereNull('discharge_date')->where('discharged', '<>', 1)
                    ->orWhere('discharge_date', '>=', $this->from);
            })
            ->get(['patient_id', 'created_at', 'discharge_date']);
        foreach ($stayRows as $s) {
            $stays[$s->patient_id][] = [$s->created_at, $s->discharge_date ?? '9999-12-31'];
        }
        $isInpatient = function ($pid, $at) use ($stays) {
            foreach ($stays[$pid] ?? [] as [$in, $out]) {
                if ($at > $in && $at <= $out) {
                    return true;
                }
            }

            return false;
        };

        $days = [];
        foreach ($this->encounters() as $e) {
            if (!$e->patient_id) {
                continue;
            }
            $key = $e->patient_id . '|' . substr($e->created_at, 0, 10);
            if (!isset($days[$key])) {
                $days[$key] = ['src' => 'Encounter', 'id' => $e->id, 'patient_id' => $e->patient_id, 'date' => $e->created_at, 'opd' => false, 'clinic' => 'Consultation'];
            }
            if ($e->admission_request_id === null && !$isInpatient($e->patient_id, (string) $e->created_at)) {
                $days[$key]['opd'] = true;
            }
        }

        // Nursing-led attendances without a doctor encounter that day (ANC, PNC, immunization)
        $nursing = [
            ['anc_visits', 'visit_date', 'ANC Visit'],
            ['postnatal_visits', 'visit_date', 'Postnatal Visit'],
        ];
        foreach ($nursing as [$table, $col, $label]) {
            foreach (DB::table($table)->whereNull('deleted_at')->whereBetween($col, [$this->from, $this->to])->get(['id', 'patient_id', $col . ' as dt']) as $v) {
                $key = $v->patient_id . '|' . substr($v->dt, 0, 10);
                if ($v->patient_id && !isset($days[$key])) {
                    $days[$key] = ['src' => $label, 'id' => $v->id, 'patient_id' => $v->patient_id, 'date' => $v->dt, 'opd' => true, 'clinic' => $label];
                }
            }
        }
        foreach (DB::table('immunization_records')->whereNull('deleted_at')->whereNotNull('patient_id')->whereBetween('administered_at', [$this->from, $this->to])->get(['id', 'patient_id', 'administered_at']) as $v) {
            $key = $v->patient_id . '|' . substr($v->administered_at, 0, 10);
            if (!isset($days[$key])) {
                $days[$key] = ['src' => 'Immunization', 'id' => $v->id, 'patient_id' => $v->patient_id, 'date' => $v->administered_at, 'opd' => true, 'clinic' => 'Immunization'];
            }
        }

        $this->loadPatients(array_column($days, 'patient_id'));
        foreach ($days as $d) {
            $col = $this->ageSexKey($d['patient_id'], $d['date']);
            $this->add('row_1', $col, $d['src'], $d['id'], $d['patient_id'], $d['date'], $d['clinic'] . ' attendance');
            if ($d['opd']) {
                $this->add('row_2', $col, $d['src'], $d['id'], $d['patient_id'], $d['date'], 'Outpatient attendance (not linked to an admission)');
            }
        }

        $admissions = DB::table('admission_requests')
            ->whereRaw('COALESCE(bed_assign_date, created_at) BETWEEN ? AND ?', [$this->from, $this->to])
            ->get(['id', 'patient_id', 'bed_assign_date', 'created_at', 'admission_reason', 'admission_status']);
        $this->loadPatients($admissions->pluck('patient_id'));
        foreach ($admissions as $a) {
            $date = $a->bed_assign_date ?? $a->created_at;
            $this->add('row_3', $this->ageSexKey($a->patient_id, $date), 'Admission', $a->id, $a->patient_id, $date, 'Admitted' . ($a->admission_reason ? ': ' . strip_tags($a->admission_reason) : ''));
        }

        $discharges = DB::table('admission_requests')->where('discharged', 1)->whereBetween('discharge_date', [$this->from, $this->to])
            ->get(['id', 'patient_id', 'discharge_date', 'discharge_reason']);
        $this->loadPatients($discharges->pluck('patient_id'));
        foreach ($discharges as $d) {
            $this->add('row_4', $this->ageSexKey($d->patient_id, $d->discharge_date), 'Admission', $d->id, $d->patient_id, $d->discharge_date, 'Discharged' . ($d->discharge_reason ? ': ' . $d->discharge_reason : ''));
        }
    }

    /* ---------------------------------------------------------------
     | Rows 5-9: mortality (still births excluded; they belong in row 50)
     * --------------------------------------------------------------- */
    protected function aggregateMortality(): void
    {
        $deaths = DB::table('death_records')
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereBetween('date_of_death', [$this->from, $this->to])
                  ->orWhere(fn ($q2) => $q2->whereNull('date_of_death')->whereBetween('created_at', [$this->from, $this->to]));
            })
            ->where(function ($q) {
                $q->whereNull('death_type')->orWhere('death_type', 'not like', '%still%');
            })
            ->get(['id', 'patient_id', 'date_of_death', 'created_at', 'cause_of_death_primary', 'cause_of_death_description']);
        $this->loadPatients($deaths->pluck('patient_id'));

        foreach ($deaths as $d) {
            $date = $d->date_of_death ?? $d->created_at;
            $cause = self::cleanText(($d->cause_of_death_primary ?? '') . ' ' . ($d->cause_of_death_description ?? ''));
            $detail = 'Death' . ($cause ? ': ' . $cause : ' (cause not recorded)');
            $this->add('row_5', $this->ageSexKey($d->patient_id, $date), 'Death Record', $d->id, $d->patient_id, $date, $detail);

            $age = $this->ageYears($d->patient_id, $date);
            $sex = $this->sexOf($d->patient_id);
            $has = fn (array $kws) => (bool) array_filter($kws, fn ($k) => $this->matchKeywordInText($cause, $k));

            // Maternal: female who died while pregnant or within 42 days of delivery, or a pregnancy-related cause
            $isMaternal = $sex === 'f' && ($this->isPregnantOrPostpartum($d->patient_id, $date)
                || $has(['pregnancy', 'postpartum', 'puerperal', 'labour', 'labor', 'maternal', 'eclampsia', 'ectopic', 'abortion', 'obstetric']));
            if ($isMaternal) {
                $col = $age === null ? null : ($age < 20 ? 'age_10_19y' : 'age_ge_20y');
                $this->add('row_6', $col, 'Death Record', $d->id, $d->patient_id, $date, $detail);
                $mc = match (true) {
                    $has(['haemorrhage', 'hemorrhage', 'pph', 'aph', 'bleeding']) => 'pph',
                    $has(['sepsis', 'septic', 'septicaemia', 'puerperal infection']) => 'sepsis',
                    $has(['obstructed', 'obstructed labour', 'uterine rupture', 'ruptured uterus', 'dystocia']) => 'obstructed_labour',
                    $has(['abortion', 'miscarriage']) => 'abortion',
                    $has(['malaria']) => 'malaria',
                    $has(['anaemia', 'anemia']) => 'anaemia',
                    $has(['hiv', 'aids']) => 'hiv',
                    default => 'other',
                };
                $this->add('row_7', $mc, 'Death Record', $d->id, $d->patient_id, $date, $detail);
            }

            $days = $this->ageDays($d->patient_id, $date);
            if ($days !== null && $days < 28) {
                $nc = match (true) {
                    $has(['prematurity', 'preterm', 'premature', 'respiratory distress syndrome', 'rds']) => 'prematurity',
                    $has(['tetanus']) => 'neonatal_tetanus',
                    $has(['congenital', 'anomaly', 'malformation']) => 'congenital_malformation',
                    default => 'other',
                };
                $this->add('row_8', $nc, 'Death Record', $d->id, $d->patient_id, $date, $detail);
            }

            if ($age !== null && $age < 5) {
                $uc = match (true) {
                    $has(['malaria']) => 'malaria',
                    $has(['pneumonia', 'bronchopneumonia']) => 'pneumonia',
                    $has(['malnutrition', 'kwashiorkor', 'marasmus', 'sam', 'severe acute malnutrition']) => 'malnutrition',
                    default => 'other',
                };
                $this->add('row_9', $uc, 'Death Record', $d->id, $d->patient_id, $date, $detail);
            }
        }
    }

    protected function isPregnantOrPostpartum(?int $patientId, $date): bool
    {
        if (!$patientId) {
            return false;
        }
        $this->isPregnantOn($patientId, $date); // builds the index
        $at = Carbon::parse($date);
        foreach ($this->pregnancyIndex[$patientId] ?? [] as [$start, $end]) {
            if ($at->between($start, $end->copy()->addDays(42))) {
                return true;
            }
        }

        return false;
    }

    /* ---------------------------------------------------------------
     | Rows 132-136: referrals out (external referrals + transfer discharges)
     * --------------------------------------------------------------- */
    protected function aggregateReferrals(): void
    {
        $refs = DB::table('specialist_referrals')
            ->whereNull('deleted_at')
            ->where('referral_type', 'external')
            ->whereBetween('created_at', [$this->from, $this->to])
            ->get(['id', 'patient_id', 'created_at', 'provisional_diagnosis', 'reason', 'clinical_summary', 'external_facility_name']);
        $this->loadPatients($refs->pluck('patient_id'));

        foreach ($refs as $r) {
            $text = self::cleanText(($r->provisional_diagnosis ?? '') . ' . ' . ($r->reason ?? '') . ' . ' . ($r->clinical_summary ?? ''));
            $detail = 'Referred out' . ($r->external_facility_name ? ' to ' . $r->external_facility_name : '') . ($r->provisional_diagnosis ? ': ' . strip_tags($r->provisional_diagnosis) : '');
            $this->add('row_132', 'total', 'Referral', $r->id, $r->patient_id, $r->created_at, $detail);
            $has = fn (array $kws) => (bool) array_filter($kws, fn ($k) => $this->matchKeywordInText($text, $k));
            if ($has(['malaria', 'plasmodiasis', 'plasmodium'])) {
                $this->add('row_133', 'total', 'Referral', $r->id, $r->patient_id, $r->created_at, $detail);
            }
            if ($this->sexOf($r->patient_id) === 'f' && ($this->isPregnantOrPostpartum($r->patient_id, $r->created_at)
                || $has(['pregnancy', 'antepartum', 'postpartum', 'labour', 'labor', 'obstetric', 'eclampsia', 'pre-eclampsia', 'ectopic']))) {
                $this->add('row_135', 'total', 'Referral', $r->id, $r->patient_id, $r->created_at, $detail);
            }
            if ($has(['fistula', 'vvf', 'rvf'])) {
                $this->add('row_136', 'total', 'Referral', $r->id, $r->patient_id, $r->created_at, $detail);
            }
        }

        // Inpatients discharged as transfers to another facility (as in the Nursing DNS report)
        $transfers = DB::table('admission_requests')->where('discharged', 1)
            ->whereBetween('discharge_date', [$this->from, $this->to])
            ->where(function ($q) {
                foreach (['%transfer%', '%higher level%', '%another facility%', '%referral%', '%referred%'] as $l) {
                    $q->orWhere('discharge_reason', 'like', $l);
                }
            })
            ->get(['id', 'patient_id', 'discharge_date', 'discharge_reason']);
        foreach ($transfers as $t) {
            $this->add('row_132', 'total', 'Admission', $t->id, $t->patient_id, $t->discharge_date, 'Discharged as transfer: ' . $t->discharge_reason);
        }
    }

    /* ---------------------------------------------------------------
     | Rows 137-145: NCD new cases (first time the condition is recorded for the patient)
     * --------------------------------------------------------------- */
    protected function aggregateNcds(): void
    {
        $defs = [
            'row_137' => [['diabetes', 'diabetic', 'diabetes mellitus', 'dm', 'dmx', 't2dm', 't2dmx', 't1dm', 'iddm', 'niddm'], ['E10', 'E11', 'E13', 'E14']],
            'row_138' => [['gestational diabetes', 'gdm', 'diabetes in pregnancy', 'gestational dm'], ['O24']],
            'row_139' => [['hypertension', 'hypertensive', 'htn', 'hpt', 'high blood pressure', 'hhd', 'hhdx', 'hypertensive heart disease'], ['I10', 'I11', 'I12', 'I13', 'I15']],
            'row_140' => [['arthritis', 'osteoarthritis', 'oa', 'rheumatoid arthritis', 'polyarthritis', 'gouty arthritis'], ['M05', 'M06', 'M13', 'M15', 'M16', 'M17', 'M18', 'M19']],
            'row_141' => [['sickle cell', 'sickle cell disease', 'sickle cell anaemia', 'sickle cell anemia', 'hbss', 'scd', 'scdx', 'scda', 'sickler', 'vaso-occlusive crisis', 'voc'], ['D57']],
            'row_142' => [['asthma', 'asthmatic', 'bronchial asthma', 'status asthmaticus'], ['J45', 'J46']],
            'row_143' => [['depression', 'depressive', 'depressive disorder', 'major depressive disorder', 'mdd'], ['F32', 'F33']],
            'row_144' => [['breast cancer', 'ca breast', 'breast ca', 'breast carcinoma', 'carcinoma of the breast', 'malignant neoplasm of breast'], ['C50']],
            'row_145' => [['cervical cancer', 'ca cervix', 'cervical carcinoma', 'carcinoma of the cervix', 'malignant neoplasm of cervix'], ['C53']],
        ];

        foreach ($defs as $rowId => [$kws, $icd]) {
            $firstHit = [];
            foreach ($this->encounters() as $e) {
                if (!$e->patient_id || isset($firstHit[$e->patient_id])) {
                    continue;
                }
                if ($this->diagnosisStatus($e, $kws, $icd) !== null) {
                    $firstHit[$e->patient_id] = $e;
                }
            }
            $prior = $this->patientsWithPriorDiagnosis(array_keys($firstHit), $kws, $icd, $this->from);

            foreach ($firstHit as $pid => $e) {
                if (isset($prior[$pid])) {
                    continue; // known case, not new
                }
                $sex = $this->sexOf($pid);
                if ($rowId === 'row_138' && $sex === 'm') {
                    continue; // gestational diabetes cannot be a male record
                }
                if ($rowId === 'row_137' && $this->diagnosisStatus($e, $defs['row_138'][0], $defs['row_138'][1]) !== null) {
                    continue; // gestational diabetes is reported in row 138
                }
                $col = $sex === 'm' ? 'male' : ($sex === 'f' ? 'female' : null);
                $this->add($rowId, $col, 'Encounter', $e->id, $pid, $e->created_at, 'New case: ' . $this->diagnosisLabel($e));
            }
        }
    }

    /* ---------------------------------------------------------------
     | Rows 146-160: malaria
     * --------------------------------------------------------------- */
    protected function malariaCol(?int $pid, $date): ?string
    {
        if ($this->isPregnantOn($pid, $date)) {
            return 'pregnant_women';
        }
        $age = $this->ageYears($pid, $date);

        return $age === null ? null : ($age < 5 ? 'lt_5y' : 'ge_5y_excl_pw');
    }

    protected function aggregateMalaria(): void
    {
        if ($this->requireProducts('llin', ['row_146'])) {
            foreach ($this->dispensed('llin', null, $this->from, $this->to) as $r) {
                $age = $this->ageYears($r->patient_id, $r->dt);
                if ($age !== null && $age < 5) {
                    $this->add('row_146', 'total', 'Dispensed', $r->id, $r->patient_id, $r->dt, $r->product_name);
                }
            }
        }

        // Fever (symptom), from the full clinical note or ICD R50
        $seen = [];
        foreach ($this->encounters() as $e) {
            if (!$e->patient_id || isset($seen[$e->patient_id])) {
                continue;
            }
            if ($this->diagnosisStatus($e, ['fever', 'pyrexia', 'febrile', 'hyperpyrexia'], ['R50'], true) === 'confirmed') {
                $seen[$e->patient_id] = true;
                $this->add('row_147', $this->malariaCol($e->patient_id, $e->created_at), 'Encounter', $e->id, $e->patient_id, $e->created_at, 'Presented with fever');
            }
        }

        // Parasitological tests (linked RDT / microscopy services, resulted)
        $positive = [];
        foreach (['malaria_rdt' => ['row_148', 'row_149', 'RDT'], 'malaria_microscopy' => ['row_150', 'row_151', 'Microscopy']] as $ind => [$testedRow, $posRow, $label]) {
            if (!$this->requireLinks($ind, [$testedRow, $posRow], $label)) {
                continue;
            }
            $tested = [];
            $pos = [];
            $labs = $this->resultedLabs($ind, $this->from, $this->to);
            $this->loadPatients($labs->pluck('patient_id'));
            foreach ($labs as $lr) {
                $pid = $lr->patient_id;
                if (!$pid) {
                    continue;
                }
                $date = self::labDate($lr);
                $svc = $lr->service->service_name ?? $label;
                if (!isset($tested[$pid])) {
                    $tested[$pid] = true;
                    $this->add($testedRow, $this->malariaCol($pid, $date), 'Lab Request', $lr->id, $pid, $date, "{$svc} done");
                }
                if (!isset($pos[$pid]) && $this->isLabResultPositive($lr, $ind)) {
                    $pos[$pid] = true;
                    $positive[$pid] ??= ['date' => $date, 'lab' => $lr->id];
                    $this->add($posRow, $this->malariaCol($pid, $date), 'Lab Request', $lr->id, $pid, $date, "{$svc} positive" . ($lr->nhmis_outcome_raw ? " ({$lr->nhmis_outcome_raw})" : ''));
                }
            }
        }

        // Diagnoses (confirmed mentions only; "? malaria" is a query, not a diagnosis)
        $malariaKw = ['malaria', 'plasmodiasis', 'plasmodium', 'falciparum malaria'];
        $severeKw = ['severe malaria', 'cerebral malaria', 'complicated malaria', 'severe plasmodiasis', 'complicated plasmodiasis'];
        $diagnosed = [];
        $severe = [];
        foreach ($this->encounters() as $e) {
            $pid = $e->patient_id;
            if (!$pid) {
                continue;
            }
            if (!isset($severe[$pid]) && $this->diagnosisStatus($e, $severeKw, ['B50.0']) === 'confirmed') {
                $severe[$pid] = $e;
            }
            if (!isset($diagnosed[$pid]) && $this->diagnosisStatus($e, $malariaKw, ['B50', 'B51', 'B52', 'B53', 'B54']) === 'confirmed') {
                $diagnosed[$pid] = $e;
            }
        }

        $allPids = array_unique(array_merge(array_keys($positive), array_keys($diagnosed), array_keys($severe)));
        $this->requireProducts('act', ['row_155', 'row_156']);
        $this->requireProducts('other_oral_antimalarial', ['row_157']);
        $this->requireProducts('artesunate_injection', ['row_159']);
        $this->requireProducts('other_injectable_antimalarial', ['row_160']);
        $act = $this->dispensed('act', $allPids, $this->from, $this->to->copy()->addDays(3))->groupBy('patient_id');
        $otherOral = $this->dispensed('other_oral_antimalarial', $allPids, $this->from, $this->to->copy()->addDays(3))->groupBy('patient_id');
        $artesInj = $this->dispensed('artesunate_injection', array_keys($severe), $this->from, $this->to->copy()->addDays(3))->groupBy('patient_id');
        $otherInj = $this->dispensed('other_injectable_antimalarial', array_keys($severe), $this->from, $this->to->copy()->addDays(3))->groupBy('patient_id');

        foreach ($positive as $pid => $p) {
            if (isset($severe[$pid])) {
                continue;
            }
            $col = $this->malariaCol($pid, $p['date']);
            $this->add('row_153', $col, 'Lab Request', $p['lab'], $pid, $p['date'], 'Confirmed uncomplicated malaria (positive test)');
            if (isset($act[$pid])) {
                $r = $act[$pid]->first();
                $this->add('row_155', $col, 'Dispensed', $r->id, $pid, $r->dt, 'ACT dispensed: ' . $r->product_name);
            } elseif (isset($otherOral[$pid])) {
                $r = $otherOral[$pid]->first();
                $this->add('row_157', $col, 'Dispensed', $r->id, $pid, $r->dt, 'Other antimalarial dispensed: ' . $r->product_name);
            }
        }

        foreach ($diagnosed as $pid => $e) {
            if (isset($positive[$pid]) || isset($severe[$pid])) {
                continue;
            }
            $col = $this->malariaCol($pid, $e->created_at);
            $this->add('row_152', $col, 'Encounter', $e->id, $pid, $e->created_at, 'Clinically diagnosed (no positive test): ' . $this->diagnosisLabel($e));
            if (isset($act[$pid])) {
                $r = $act[$pid]->first();
                $this->add('row_156', $col, 'Dispensed', $r->id, $pid, $r->dt, 'ACT dispensed: ' . $r->product_name);
            }
        }

        $hasRectal = $this->requireProducts('rectal_artesunate', ['row_158']);
        foreach ($severe as $pid => $e) {
            $col = $this->malariaCol($pid, $e->created_at);
            $this->add('row_154', $col, 'Encounter', $e->id, $pid, $e->created_at, 'Severe malaria: ' . $this->diagnosisLabel($e));
            if (isset($artesInj[$pid])) {
                $r = $artesInj[$pid]->first();
                $this->add('row_159', $col, 'Dispensed', $r->id, $pid, $r->dt, 'Artesunate injection: ' . $r->product_name);
            } elseif (isset($otherInj[$pid])) {
                $r = $otherInj[$pid]->first();
                $this->add('row_160', $col, 'Dispensed', $r->id, $pid, $r->dt, 'Other injectable antimalarial: ' . $r->product_name);
            }
        }
        if ($hasRectal) {
            foreach ($this->dispensed('rectal_artesunate', array_keys($severe), $this->from, $this->to) as $r) {
                $this->add('row_158', $this->malariaCol($r->patient_id, $r->dt), 'Dispensed', $r->id, $r->patient_id, $r->dt, 'Pre-referral rectal artesunate');
            }
        }
    }

    /* ---------------------------------------------------------------
     | Rows 161-185: TB, hepatitis, GBV, fistula, NTDs
     * --------------------------------------------------------------- */
    protected function sexCol(?int $pid): ?string
    {
        $s = $this->sexOf($pid);

        return $s === 'm' ? 'male' : ($s === 'f' ? 'female' : null);
    }

    protected function aggregateCommunicableAndSpecialized(): void
    {
        // Row 162: presumptive TB = persons sent for a TB diagnostic test (AFB / GeneXpert links)
        $seen = [];
        $this->requireLinks('tb_sputum_afb', ['row_162'], 'TB diagnostic test');
        foreach ($this->resultedLabs('tb_sputum_afb', $this->from, $this->to) as $lr) {
            if (!$lr->patient_id || isset($seen[$lr->patient_id])) {
                continue;
            }
            $seen[$lr->patient_id] = true;
            $this->add('row_162', $this->sexCol($lr->patient_id), 'Lab Request', $lr->id, $lr->patient_id, self::labDate($lr), ($lr->service->service_name ?? 'TB test') . ($this->isLabResultPositive($lr, 'tb_afb') ? ' (positive)' : ' (negative)'));
        }

        // Rows 164-171: hepatitis B & C
        $hepCol = function (?int $pid, $date): ?string {
            $sex = $this->sexOf($pid);
            $age = $this->ageYears($pid, $date);
            if (!$sex || $age === null || $age < 10) {
                return null;
            }

            return $sex . '_' . ($age < 20 ? '10_19y' : 'ge_20y');
        };
        $hep = [
            'hepatitis_b' => ['row_164', 'row_165', 'row_166', 'row_167', 'hbv_antiviral'],
            'hepatitis_c' => ['row_168', 'row_169', 'row_170', 'row_171', 'hcv_antiviral'],
        ];
        foreach ($hep as $ind => [$tRow, $pRow, $txRow, $refRow, $drug]) {
            if (!$this->requireLinks($ind, [$tRow, $pRow, $txRow, $refRow], str_replace('_', ' ', $ind))) {
                continue;
            }
            $tested = [];
            $pos = [];
            foreach ($this->resultedLabs($ind, $this->from, $this->to) as $lr) {
                $pid = $lr->patient_id;
                if (!$pid) {
                    continue;
                }
                $date = self::labDate($lr);
                if (!isset($tested[$pid])) {
                    $tested[$pid] = true;
                    $this->add($tRow, $hepCol($pid, $date), 'Lab Request', $lr->id, $pid, $date, ($lr->service->service_name ?? 'Test') . ' done');
                }
                if (!isset($pos[$pid]) && $this->isLabResultPositive($lr, $ind)) {
                    $pos[$pid] = [$date, $lr->id];
                    $this->add($pRow, $hepCol($pid, $date), 'Lab Request', $lr->id, $pid, $date, ($lr->service->service_name ?? 'Test') . ' positive');
                }
            }
            if ($this->requireProducts($drug, [$txRow])) {
                foreach ($this->dispensed($drug, array_keys($pos), $this->from, $this->to)->unique('patient_id') as $r) {
                    $this->add($txRow, $hepCol($r->patient_id, $r->dt), 'Dispensed', $r->id, $r->patient_id, $r->dt, 'Antiviral dispensed: ' . $r->product_name);
                }
            }
            foreach (array_keys($this->externalReferralPatients(array_keys($pos))) as $pid) {
                [$date, $labId] = $pos[$pid];
                $this->add($refRow, $hepCol($pid, $date), 'Lab Request', $labId, $pid, $date, 'Positive and referred out');
            }
        }

        // Rows 172 & 174: gender-based violence
        $gbvKw = ['rape', 'sexual assault', 'sexual abuse', 'sexual violence', 'defilement', 'gender based violence', 'gender-based violence', 'gbv', 'domestic violence', 'intimate partner violence'];
        $gbv = [];
        foreach ($this->encounters() as $e) {
            if ($e->patient_id && !isset($gbv[$e->patient_id]) && $this->diagnosisStatus($e, $gbvKw, ['T74.2', 'Y05']) === 'confirmed') {
                $gbv[$e->patient_id] = $e;
            }
        }
        $gbvCol = function (?int $pid, $date): ?string {
            $sex = $this->sexOf($pid);
            $age = $this->ageYears($pid, $date);

            return ($sex && $age !== null) ? $sex . '_' . ($age < 20 ? 'lt_20y' : 'ge_20y') : null;
        };
        $gbvRef = $this->externalReferralPatients(array_keys($gbv));
        foreach ($gbv as $pid => $e) {
            $this->add('row_172', $gbvCol($pid, $e->created_at), 'Encounter', $e->id, $pid, $e->created_at, $this->diagnosisLabel($e));
            if (isset($gbvRef[$pid])) {
                $this->add('row_174', $gbvCol($pid, $e->created_at), 'Encounter', $e->id, $pid, $e->created_at, 'Referred out');
            }
        }

        // Rows 175-177, 181: obstetric fistula
        $fistulaCol = function (string $text, ?int $pid, $date): ?string {
            $age = $this->ageYears($pid, $date);
            if ($age === null || $age < 10) {
                return null;
            }
            $v = (bool) preg_match('/\b(vvf|vesico\s*-?\s*vaginal)\b/', $text);
            $r = (bool) preg_match('/\b(rvf|recto\s*-?\s*vaginal)\b/', $text);
            $type = $v && $r ? 'vvf_rvf' : ($v ? 'vvf' : ($r ? 'rvf' : null));

            return $type ? $type . '_' . ($age < 20 ? '10_19y' : 'ge_20y') : null;
        };
        $fistula = [];
        foreach ($this->encounters() as $e) {
            if ($e->patient_id && !isset($fistula[$e->patient_id]) && $this->diagnosisStatus($e, ['vvf', 'rvf', 'vesicovaginal fistula', 'vesico-vaginal fistula', 'rectovaginal fistula', 'recto-vaginal fistula', 'obstetric fistula'], ['N82']) === 'confirmed') {
                $fistula[$e->patient_id] = $e;
            }
        }
        $fRef = $this->externalReferralPatients(array_keys($fistula));
        foreach ($fistula as $pid => $e) {
            $label = $this->diagnosisLabel($e);
            $col = $fistulaCol(strtolower($label), $pid, $e->created_at);
            $this->add('row_175', $col, 'Encounter', $e->id, $pid, $e->created_at, $label);
            if (isset($fRef[$pid])) {
                $this->add('row_181', $col, 'Encounter', $e->id, $pid, $e->created_at, 'Referred out');
            }
        }
        $repairIds = \App\Models\NhmisServiceMapping::getServiceIds('fistula_repair');
        if ($repairIds) {
            $procs = DB::table('procedures as pr')->leftJoin('services as s', 's.id', '=', 'pr.service_id')
                ->whereNull('pr.deleted_at')->whereIn('pr.service_id', $repairIds)->where('pr.procedure_status', 'completed')
                ->whereRaw('COALESCE(pr.actual_end_time, pr.updated_at) BETWEEN ? AND ?', [$this->from, $this->to])
                ->get(['pr.id', 'pr.patient_id', 'pr.outcome', 's.service_name', DB::raw('COALESCE(pr.actual_end_time, pr.updated_at) as dt')]);
            foreach ($procs as $p) {
                $outcome = strtolower((string) $p->outcome);
                $row = $outcome === 'successful' ? 'row_176' : (in_array($outcome, ['failed', 'unsuccessful'], true) ? 'row_177' : null);
                if ($row) {
                    $this->add($row, $fistulaCol(strtolower((string) $p->service_name), $p->patient_id, $p->dt), 'Procedure', $p->id, $p->patient_id, $p->dt, $p->service_name . ': ' . $p->outcome);
                }
            }
        }

        // Rows 182-184: trachoma, snake bite, anti-snake venom
        $ntd = ['row_182' => [['trachoma', 'trachomatous'], ['A71']], 'row_183' => [['snake bite', 'snakebite', 'snake envenomation', 'envenomation'], ['T63.0']]];
        $snake = [];
        foreach ($ntd as $rowId => [$kws, $icd]) {
            $seen = [];
            foreach ($this->encounters() as $e) {
                if ($e->patient_id && !isset($seen[$e->patient_id]) && $this->diagnosisStatus($e, $kws, $icd) === 'confirmed') {
                    $seen[$e->patient_id] = true;
                    $this->add($rowId, $this->sexCol($e->patient_id), 'Encounter', $e->id, $e->patient_id, $e->created_at, $this->diagnosisLabel($e));
                    if ($rowId === 'row_183') {
                        $snake[] = $e->patient_id;
                    }
                }
            }
        }
        if ($this->requireProducts('antivenom', ['row_184'])) {
            foreach ($this->dispensed('antivenom', $snake, $this->from, $this->to)->unique('patient_id') as $r) {
                $this->add('row_184', $this->sexCol($r->patient_id), 'Dispensed', $r->id, $r->patient_id, $r->dt, $r->product_name);
            }
        }
    }
}
