<?php

namespace App\Services\Nhmis;

use App\Models\ImmunizationRecord;
use App\Models\LabServiceRequest;
use App\Models\MaternityEnrollment;
use App\Models\NhmisMonthlyReport;
use App\Models\NhmisMonthlyReportValue;
use App\Models\NhmisServiceMapping;
use App\Services\Nhmis\Concerns\AggregatesClinicalSections;
use App\Services\Nhmis\Concerns\AggregatesMaternalAndChildSections;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Builds the NHMIS monthly summary from EMR records.
 *
 * Every computed cell is the count (or quantity sum) of a list of evidence records.
 * The same list is served by the Drill Down, so a number on the form always equals
 * the records behind it. Rows the EMR does not record are left blank (null) and listed
 * in MANUAL_ROWS so the records officer enters them by hand.
 */
class NhmisDataAggregatorService
{
    use AggregatesMaternalAndChildSections;
    use AggregatesClinicalSections;

    /**
     * Rows (or single cells) the EMR has no data for. Value = reason shown to the user.
     */
    public const MANUAL_ROWS = [
        'row_34' => 'Care-seeking decision time is not recorded in the EMR.',
        'row_35' => 'Mode of arrival (transported in) is not recorded in the EMR.',
        'row_51' => 'Cord clamping time is not recorded on the baby record.',
        'row_52' => 'Chlorhexidine application is not recorded on the baby record.',
        'row_53' => 'Breastfeeding / skin-to-skin within 1 hour is not recorded on the baby record.',
        'row_54' => 'Temperature at 1 hour of birth is not recorded on the baby record.',
        'row_57' => 'Newborn danger signs are not recorded as structured data.',
        'row_58' => 'Newborn danger signs are not recorded as structured data.',
        'row_61' => 'Kangaroo Mother Care is not recorded in the EMR.',
        'row_62' => 'Kangaroo Mother Care is not recorded in the EMR.',
        'row_89' => 'AEFI investigations are not recorded in the EMR.',
        'row_90' => 'AEFI investigation outcomes are not recorded in the EMR.',
        'row_91' => 'Facility microplan status is an administrative return; enter it manually.',
        'row_92' => 'RI session planning is an administrative return; enter it manually.',
        'row_93' => 'RI session planning is an administrative return; enter it manually.',
        'row_94' => 'Supportive supervision is an administrative return; enter it manually.',
        'row_95' => 'Supportive supervision is an administrative return; enter it manually.',
        'row_96' => 'RI funds received is an administrative return; enter it manually.',
        'row_97' => 'WDC meetings are an administrative return; enter it manually.',
        'row_98' => 'Birth registration is not recorded in the EMR.',
        'row_99' => 'Birth certificates are not recorded in the EMR.',
        'row_100' => 'Birth certificates are not recorded in the EMR.',
        'row_109_new' => 'CMAM programme register is not part of the EMR.',
        'row_109_transferred_in' => 'CMAM programme register is not part of the EMR.',
        'row_109_recovered' => 'CMAM programme register is not part of the EMR.',
        'row_109_defaulted' => 'CMAM programme register is not part of the EMR.',
        'row_109_dead' => 'CMAM programme register is not part of the EMR.',
        'row_109_transferred_out' => 'CMAM programme register is not part of the EMR.',
        'row_127' => 'Internal referrals to family planning are not distinguishable in the EMR.',
        'row_134' => 'Adverse drug reaction referrals are not recorded in the EMR.',
        'row_161' => 'TB symptom screening is not recorded as structured data.',
        'row_163' => 'Referrals to TB/DOTS services are not recorded as structured data.',
        'row_173' => 'Post-GBV care packages are not recorded as structured data.',
        'row_178' => 'Fistula FP counselling is not recorded in the EMR.',
        'row_179' => 'Pregnancy after fistula repair is not recorded in the EMR.',
        'row_180' => 'Elective CS scheduling for fistula cases is not recorded in the EMR.',
        'row_185' => 'NAFDAC ADR reports are not recorded in the EMR.',
        'row_88:non_serious' => 'AEFI seriousness is not recorded; only the total is computed.',
        'row_88:serious' => 'AEFI seriousness is not recorded; only the total is computed.',
    ];

    /** Rows whose required drug is absent from the pharmacy catalogue (filled at build time). */
    protected array $dynamicManual = [];

    protected Carbon $from;

    protected Carbon $to;

    /** @var array<string, array<int, array>> */
    protected array $evidence = [];

    /** @var array<string, int> row id => records that could not be placed in a column */
    protected array $unplaced = [];

    /** @var array<int, array{dob: ?Carbon, sex: ?string}> */
    protected array $patients = [];

    protected ?Collection $encounters = null;

    protected array $diagCache = [];

    protected ?array $pregnancyIndex = null;

    /* =====================================================================
     | Public API
     * ===================================================================== */

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function periodFor(NhmisMonthlyReport $report): array
    {
        $year = (int) $report->year;
        $month = (int) $report->month;

        if ($report->period_type === 'custom' || $month === 255) {
            return [Carbon::parse($report->start_date)->startOfDay(), Carbon::parse($report->end_date)->endOfDay()];
        }
        if ($month === 0) {
            return [Carbon::create($year, 1, 1)->startOfYear(), Carbon::create($year, 12, 31)->endOfYear()];
        }

        return [Carbon::create($year, $month, 1)->startOfMonth(), Carbon::create($year, $month, 1)->endOfMonth()];
    }

    /**
     * Compute every cell and persist auto values. Manual cells are stored as null.
     *
     * @return array<string, int|float|null>
     */
    public function compileReport(NhmisMonthlyReport $report): array
    {
        $result = $this->build($report);

        DB::beginTransaction();

        try {
            foreach ($result['values'] as $cellKey => $autoVal) {
                $record = NhmisMonthlyReportValue::firstOrNew(['report_id' => $report->id, 'cell_key' => $cellKey]);
                $record->auto_value = $autoVal;
                if ($record->override_value === null) {
                    $record->final_value = $autoVal;
                }
                $meta = is_array($record->metadata) ? $record->metadata : [];
                unset($meta['unplaced'], $meta['manual_reason']);
                [$rowId] = explode(':', $cellKey, 2);
                if (str_ends_with($cellKey, ':total') && !empty($result['unplaced'][$rowId])) {
                    $meta['unplaced'] = $result['unplaced'][$rowId];
                }
                if ($autoVal === null) {
                    $meta['manual_reason'] = $result['manual'][$cellKey] ?? null;
                }
                $record->metadata = $meta ?: null;
                $record->save();
            }

            $report->update([
                'status' => 'compiled',
                'compiled_by' => auth()->id() ?? $report->compiled_by,
                'compiled_at' => now(),
            ]);

            DB::commit();
            Cache::put($this->cacheKey($report->fresh()), $result, now()->addMinutes(30));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error compiling NHMIS report: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            throw $e;
        }

        return $result['values'];
    }

    /**
     * Evidence records behind one cell (the Drill Down list).
     */
    public function getCellEvidence(NhmisMonthlyReport $report, string $cellKey): array
    {
        $result = Cache::remember($this->cacheKey($report), now()->addMinutes(30), fn () => $this->build($report));

        return [
            'records' => $result['evidence'][$cellKey] ?? [],
            'manual_reason' => $result['manual'][$cellKey] ?? null,
            'unplaced' => str_ends_with($cellKey, ':total') ? ($result['unplaced'][explode(':', $cellKey)[0]] ?? 0) : 0,
        ];
    }

    public function forgetCache(NhmisMonthlyReport $report): void
    {
        Cache::forget($this->cacheKey($report));
    }

    /**
     * Pure computation: values, evidence and manual reasons for every schema cell.
     */
    public function build(NhmisMonthlyReport $report): array
    {
        [$this->from, $this->to] = self::periodFor($report);
        $this->evidence = [];
        $this->unplaced = [];
        $this->dynamicManual = [];
        $this->diagCache = [];
        $this->history = [];
        $this->productLinkCache = [];
        $this->pregnancyIndex = null;
        $this->encounters = null;

        $this->aggregateAttendanceAndInpatient();
        $this->aggregateMortality();
        $this->aggregateAntenatalCare();
        $this->aggregateLabourAndDelivery();
        $this->aggregatePostnatalCare();
        $this->aggregateNewbornHealth();
        $this->aggregateImmunizations();
        $this->aggregateNutritionAndGrowth();
        $this->aggregateImci();
        $this->aggregateFamilyPlanning();
        $this->aggregateReferrals();
        $this->aggregateNcds();
        $this->aggregateMalaria();
        $this->aggregateCommunicableAndSpecialized();

        $values = [];
        $manual = [];
        $schema = NhmisFormRegistry::getSchema($report->form_version ?: 'v2019');
        foreach ($schema->getPages() as $page) {
            foreach ($page['sections'] as $section) {
                foreach ($section['rows'] as $row) {
                    $cols = $section['columns'] ?? $row['columns'] ?? [];
                    $rowManual = self::MANUAL_ROWS[$row['id']] ?? ($this->dynamicManual[$row['id']] ?? null);
                    foreach (array_keys($cols) as $col) {
                        $key = "{$row['id']}:{$col}";
                        $cellManual = $rowManual ?? (self::MANUAL_ROWS[$key] ?? ($this->dynamicManual[$key] ?? null));
                        if ($cellManual !== null) {
                            $values[$key] = null;
                            $manual[$key] = $cellManual;

                            continue;
                        }
                        $values[$key] = $this->sumEvidence($key);
                    }
                }
            }
        }

        return [
            'values' => $values,
            'evidence' => $this->evidence,
            'unplaced' => $this->unplaced,
            'manual' => $manual,
            'period' => [$this->from->toDateTimeString(), $this->to->toDateTimeString()],
        ];
    }

    /* =====================================================================
     | Evidence helpers
     * ===================================================================== */

    protected function cacheKey(NhmisMonthlyReport $report): string
    {
        return 'nhmis_evidence:' . $report->id . ':' . optional($report->compiled_at)->timestamp . ':' . $report->period_type . ':' . $report->month;
    }

    /**
     * Record one piece of evidence. $col null = could not be placed in a column (counted in total only).
     */
    protected function add(string $rowId, ?string $col, string $src, $id, ?int $patientId, $date, string $detail, $qty = 1): void
    {
        $item = [
            'src' => $src,
            'id' => $id,
            'patient_id' => $patientId,
            'date' => $date instanceof Carbon ? $date->format('Y-m-d H:i') : ($date ? Carbon::parse($date)->format('Y-m-d H:i') : null),
            'detail' => $detail,
            'qty' => $qty,
        ];
        if ($col === null) {
            $this->unplaced[$rowId] = ($this->unplaced[$rowId] ?? 0) + 1;
        } else {
            $this->evidence["{$rowId}:{$col}"][] = $item;
        }
        if ($col !== 'total' && $col !== 'val') {
            $this->evidence["{$rowId}:total"][] = $item;
        }
    }

    protected function sumEvidence(string $key): int|float
    {
        $sum = 0;
        foreach ($this->evidence[$key] ?? [] as $e) {
            $sum += $e['qty'] ?? 1;
        }

        return $sum;
    }

    protected function markManual(string $key, string $reason): void
    {
        $this->dynamicManual[$key] = $reason;
    }

    /* =====================================================================
     | Patient demographics (DOB / sex). Missing values are never guessed.
     * ===================================================================== */

    protected function loadPatients(iterable $ids): void
    {
        $missing = [];
        foreach ($ids as $id) {
            if ($id && !isset($this->patients[$id])) {
                $missing[$id] = true;
            }
        }
        foreach (array_chunk(array_keys($missing), 2000) as $chunk) {
            foreach (DB::table('patients')->whereIn('id', $chunk)->get(['id', 'dob', 'gender']) as $p) {
                $dob = null;
                if ($p->dob) {
                    try {
                        $dob = Carbon::parse($p->dob)->startOfDay();
                    } catch (\Throwable $e) {
                        $dob = null;
                    }
                }
                $this->patients[$p->id] = ['dob' => $dob, 'sex' => self::normaliseSex($p->gender)];
            }
            foreach ($chunk as $id) {
                $this->patients[$id] ??= ['dob' => null, 'sex' => null];
            }
        }
    }

    public static function normaliseSex(?string $value): ?string
    {
        $v = strtolower(trim((string) $value));

        return match ($v) {
            'male', 'm' => 'm',
            'female', 'f' => 'f',
            default => null,
        };
    }

    protected function sexOf(?int $patientId): ?string
    {
        if (!$patientId) {
            return null;
        }
        $this->loadPatients([$patientId]);

        return $this->patients[$patientId]['sex'];
    }

    protected function dobOf(?int $patientId): ?Carbon
    {
        if (!$patientId) {
            return null;
        }
        $this->loadPatients([$patientId]);
        $dob = $this->patients[$patientId]['dob'];

        return $dob;
    }

    /** Age in whole years at a date; null when DOB unknown or after the date. */
    protected function ageYears(?int $patientId, $date): ?int
    {
        $dob = $this->dobOf($patientId);
        $at = Carbon::parse($date);
        if (!$dob || $dob->gt($at)) {
            return null;
        }

        return (int) $dob->diffInYears($at);
    }

    protected function ageMonths(?int $patientId, $date): ?int
    {
        $dob = $this->dobOf($patientId);
        $at = Carbon::parse($date);
        if (!$dob || $dob->gt($at)) {
            return null;
        }

        return (int) $dob->diffInMonths($at);
    }

    protected function ageDays(?int $patientId, $date): ?int
    {
        $dob = $this->dobOf($patientId);
        $at = Carbon::parse($date);
        if (!$dob || $dob->gt($at)) {
            return null;
        }

        return (int) $dob->diffInDays($at);
    }

    /**
     * NHMIS standard age band. Returns null when DOB is unknown (never defaults to adult).
     */
    public function getNhmisAgeBand(?Carbon $dob, Carbon $referenceDate): ?string
    {
        if (!$dob || $dob->gt($referenceDate)) {
            return null;
        }
        if ($dob->diffInDays($referenceDate) <= 28) {
            return '0_28d';
        }
        $months = $dob->diffInMonths($referenceDate);
        if ($months < 12) {
            return '29d_11m';
        }
        if ($months < 60) {
            return '12_59m';
        }
        $years = $dob->diffInYears($referenceDate);
        if ($years < 10) {
            return '5_9y';
        }

        return $years < 20 ? '10_19y' : 'ge_20y';
    }

    /** Standard 12-column age/sex key (e.g. f_12_59m) or null when DOB or sex is unknown. */
    protected function ageSexKey(?int $patientId, $date): ?string
    {
        $sex = $this->sexOf($patientId);
        $band = $this->getNhmisAgeBand($this->dobOf($patientId), Carbon::parse($date));

        return ($sex && $band) ? "{$sex}_{$band}" : null;
    }

    /* =====================================================================
     | Pregnancy status at a date, from maternity enrolments (LMP -> delivery / EDD).
     * ===================================================================== */

    protected function isPregnantOn(?int $patientId, $date): bool
    {
        if (!$patientId) {
            return false;
        }
        if ($this->pregnancyIndex === null) {
            $this->pregnancyIndex = [];
            $windowStart = $this->from->copy()->subMonths(10);
            $rows = DB::table('maternity_enrollments as e')
                ->leftJoin('delivery_records as d', function ($j) {
                    $j->on('d.enrollment_id', '=', 'e.id')->whereNull('d.deleted_at');
                })
                ->whereNull('e.deleted_at')
                ->whereNotNull('e.lmp')
                ->where('e.lmp', '<=', $this->to)
                ->where(function ($q) use ($windowStart) {
                    $q->where('e.edd', '>=', $windowStart)->orWhereNull('e.edd');
                })
                ->groupBy('e.id', 'e.patient_id', 'e.lmp', 'e.edd')
                ->get(['e.id', 'e.patient_id', 'e.lmp', 'e.edd', DB::raw('MIN(d.delivery_date) as delivered_on')]);
            foreach ($rows as $r) {
                $start = Carbon::parse($r->lmp)->startOfDay();
                $end = $r->delivered_on
                    ? Carbon::parse($r->delivered_on)->endOfDay()
                    : ($r->edd ? Carbon::parse($r->edd)->addDays(14)->endOfDay() : $start->copy()->addDays(294));
                $this->pregnancyIndex[$r->patient_id][] = [$start, $end, $r->id];
            }
        }
        $at = Carbon::parse($date);
        foreach ($this->pregnancyIndex[$patientId] ?? [] as [$start, $end]) {
            if ($at->between($start, $end)) {
                return true;
            }
        }

        return false;
    }

    /* =====================================================================
     | Encounters & diagnoses (structured ICD items + assessment text of notes)
     * ===================================================================== */

    protected function encounters(): Collection
    {
        if ($this->encounters === null) {
            $this->encounters = DB::table('encounters')
                ->whereNull('deleted_at')
                ->whereBetween('created_at', [$this->from, $this->to])
                ->orderBy('created_at')
                ->get(['id', 'patient_id', 'doctor_id', 'admission_request_id', 'reasons_for_encounter', 'reasons_for_encounter_comment_1', 'reasons_for_encounter_comment_2', 'notes', 'created_at']);
            $this->loadPatients($this->encounters->pluck('patient_id'));
        }

        return $this->encounters;
    }

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

        return [
            'group_key' => $isCustom ? 'CUSTOM_' . strtolower($name) : 'ICD_' . strtoupper($code),
            'code' => $isCustom ? 'CUSTOM' : strtoupper(trim($code)),
            'name' => $isCustom ? ($name !== '' ? mb_convert_case($name, MB_CASE_TITLE, 'UTF-8') : 'Unknown') : ($name ?: strtoupper($code)),
            'raw_name' => $name,
            'query' => $query,
            'status' => $status,
        ];
    }

    public static function cleanText(?string $html): string
    {
        if (!$html) {
            return '';
        }
        $t = preg_replace('/<(br|\/p|\/div|\/li|\/h[1-6]|\/tr)[^>]*>/i', ". ", $html);
        $t = html_entity_decode(strip_tags($t), ENT_QUOTES, 'UTF-8');
        $t = str_replace("\xC2\xA0", ' ', $t);

        return strtolower(trim(preg_replace('/\s+/', ' ', $t)));
    }

    /**
     * Diagnosis parts of an encounter: structured items, assessment text, full note text.
     */
    protected function diagParts(object $enc): array
    {
        if (isset($this->diagCache[$enc->id])) {
            return $this->diagCache[$enc->id];
        }

        $items = [];
        $raw = $enc->reasons_for_encounter ?? null;
        $decoded = $raw ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            $decoded = ($raw && trim($raw) !== '') ? array_filter(array_map('trim', explode(',', $raw))) : [];
        }
        foreach ($decoded as $item) {
            $n = self::normalizeDiagnosisItem($item, $enc->reasons_for_encounter_comment_1 ?? null, $enc->reasons_for_encounter_comment_2 ?? null);
            $q = strtoupper((string) $n['query']);
            $items[] = [
                'text' => strtolower(html_entity_decode($n['raw_name'] . ' ' . (is_array($item) ? ($item['display'] ?? '') : ''), ENT_QUOTES, 'UTF-8')),
                'code' => $n['code'],
                'query' => in_array($q, ['QUERY', 'DIFFRENTIAL', 'DIFFERENTIAL', 'PROVISIONAL'], true),
            ];
        }

        $full = self::cleanText($enc->notes ?? '');
        $assess = '';
        if ($full !== '' && preg_match_all('/(?:\bass(?:essment)?\b|\bdiagnos[ie]s\b|\bimpression\b|\bdx\b|\bmanaged for\b)\s*[;:\-.]?\s*(.{0,260}?)(?=\bplan\b|\brx\b|\btreatment\b|\bmgt\b|$)/', $full, $m)) {
            $assess = trim(implode(' . ', array_filter($m[1])));
        }

        return $this->diagCache[$enc->id] = ['items' => $items, 'assess' => $assess, 'full' => $full];
    }

    /**
     * Is the term at $offset in $text negated or queried? Looks back to the previous clause break.
     *
     * @return 'neg'|'query'|null
     */
    protected static function qualifierBefore(string $text, int $offset): ?string
    {
        $prefix = substr($text, max(0, $offset - 40), min(40, $offset));
        $parts = preg_split('/[.;,\n]|\bplan\b/', $prefix);
        $clause = trim((string) end($parts));
        if (preg_match('/\b(no|not|nil|negative|neg|non|denies|denied|without|absent|excluded|resolved)\b|\bno\s*h\/?o\b/', $clause)) {
            return 'neg';
        }
        if (preg_match('/\?|\b(query|r\/o|rule\s*out|kiv|suspected|susp|differential|dd|likely|possible|vs)\b/', $clause)) {
            return 'query';
        }

        return null;
    }

    /**
     * Match a condition on an encounter.
     *
     * @return 'confirmed'|'query'|null
     */
    public function diagnosisStatus(object $enc, array $keywords, array $icdPrefixes = [], bool $useFullNotes = false): ?string
    {
        $p = $this->diagParts($enc);
        $best = null;

        foreach ($p['items'] as $it) {
            $hit = false;
            foreach ($icdPrefixes as $icd) {
                if ($it['code'] !== 'CUSTOM' && str_starts_with($it['code'], strtoupper($icd))) {
                    $hit = true;

                    break;
                }
            }
            if (!$hit) {
                foreach ($keywords as $kw) {
                    if (preg_match('/\b' . preg_quote(strtolower($kw), '/') . '\b/', $it['text'])) {
                        $hit = true;

                        break;
                    }
                }
            }
            if ($hit) {
                if (!$it['query']) {
                    return 'confirmed';
                }
                $best = 'query';
            }
        }

        $text = (!$useFullNotes && $p['assess'] !== '') ? $p['assess'] : $p['full'];
        if ($text === '') {
            return $best;
        }
        $terms = array_merge($keywords, $icdPrefixes);
        foreach ($terms as $term) {
            if (!preg_match_all('/\b' . preg_quote(strtolower($term), '/') . '\b/', $text, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($m[0] as [$found, $offset]) {
                $q = self::qualifierBefore($text, $offset);
                if ($q === 'neg') {
                    continue;
                }
                if ($q === null) {
                    return 'confirmed';
                }
                $best = 'query';
            }
        }

        return $best;
    }

    /** Backwards-compatible boolean matcher (confirmed or queried mention). */
    public function matchEncounterDiagnosis($encounter, array $keywords, array $icdPrefixes = []): bool
    {
        return $this->diagnosisStatus((object) $encounter->getAttributes(), $keywords, $icdPrefixes) !== null;
    }

    public function matchKeywordInText(string $text, string $kw): bool
    {
        $kw = trim($kw);

        return $kw !== '' && (bool) preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $text);
    }

    protected function diagnosisLabel(object $enc): string
    {
        $p = $this->diagParts($enc);
        if ($p['items']) {
            return ucfirst(implode('; ', array_map(fn ($i) => trim($i['text']), $p['items'])));
        }

        return ucfirst(\Illuminate\Support\Str::limit($p['assess'] ?: $p['full'], 140));
    }

    /**
     * Has this patient had the condition recorded before $before (any earlier encounter)?
     */
    protected function patientsWithPriorDiagnosis(array $patientIds, array $keywords, array $icd, Carbon $before, ?Carbon $since = null): array
    {
        if (!$patientIds) {
            return [];
        }
        $this->loadHistory($patientIds);
        $prior = [];
        $beforeStr = $before->toDateTimeString();
        $sinceStr = $since?->toDateTimeString();
        foreach ($patientIds as $pid) {
            foreach ($this->history[$pid] ?? [] as $r) {
                if ($r->created_at >= $beforeStr || ($sinceStr && $r->created_at < $sinceStr)) {
                    continue;
                }
                if ($this->diagnosisStatus($r, $keywords, $icd, true) !== null) {
                    $prior[$pid] = true;

                    break;
                }
            }
        }

        return $prior;
    }

    /** Encounters per patient up to the period end, loaded once per build (callers filter by date). */
    protected array $history = [];

    protected function loadHistory(array $patientIds): void
    {
        $missing = array_values(array_filter($patientIds, fn ($p) => $p && !isset($this->history[$p])));
        foreach (array_chunk($missing, 500) as $chunk) {
            foreach ($chunk as $pid) {
                $this->history[$pid] = [];
            }
            $rows = DB::table('encounters')
                ->whereNull('deleted_at')
                ->whereIn('patient_id', $chunk)
                ->where('created_at', '<', $this->to)
                ->where(function ($q) {
                    $q->where(fn ($q2) => $q2->whereNotNull('reasons_for_encounter')->where('reasons_for_encounter', '<>', ''))
                        ->orWhere(fn ($q2) => $q2->whereNotNull('notes')->where('notes', '<>', ''));
                })
                ->orderBy('created_at')
                ->get(['id', 'patient_id', 'reasons_for_encounter', 'reasons_for_encounter_comment_1', 'reasons_for_encounter_comment_2', 'notes', 'created_at']);
            foreach ($rows as $r) {
                $this->history[$r->patient_id][] = $r;
            }
        }
    }

    /* =====================================================================
     | Pharmacy (dispensed items only: product_requests.status = 3)
     * ===================================================================== */

    /**
     * Dispensed items of the products linked to an NHMIS drug group (Service Links → Pharmacy).
     */
    protected function dispensed(string $drugGroup, ?array $patientIds, Carbon $from, Carbon $to): Collection
    {
        if ($patientIds !== null && empty($patientIds)) {
            return collect();
        }
        $products = $this->linkedProducts($drugGroup);
        if (!$products) {
            return collect();
        }
        $q = DB::table('product_requests as pr')
            ->whereIn('pr.product_id', array_keys($products))
            ->where('pr.status', 3)
            ->whereNull('pr.deleted_at')
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('pr.dispense_date', [$from, $to])
                    ->orWhere(fn ($q2) => $q2->whereNull('pr.dispense_date')->whereBetween('pr.created_at', [$from, $to]));
            });
        if ($patientIds !== null) {
            $q->whereIn('pr.patient_id', $patientIds);
        }

        return $q->get(['pr.id', 'pr.patient_id', 'pr.qty', 'pr.product_id', DB::raw('COALESCE(pr.dispense_date, pr.created_at) as dt')])
            ->each(function ($r) use ($products) {
                $r->product_name = $products[$r->product_id];
            })
            ->values();
    }

    /** @return array<int, string> product id => name */
    protected function linkedProducts(string $drugGroup): array
    {
        return $this->productLinkCache[$drugGroup] ??= \App\Models\NhmisProductMapping::getLinkedProducts($drugGroup);
    }

    protected array $productLinkCache = [];

    /**
     * Pharmacy-based cells need at least one product linked to the drug group; otherwise they are manual.
     */
    protected function requireProducts(string $drugGroup, array $cells): bool
    {
        if ($this->linkedProducts($drugGroup)) {
            return true;
        }
        $label = \App\Models\NhmisProductMapping::INDICATORS[$drugGroup]['label'] ?? $drugGroup;
        foreach ($cells as $c) {
            $this->markManual($c, "No pharmacy product is linked to \"{$label}\". Link it in Service Links (Pharmacy) to compute this row.");
        }

        return false;
    }

    /* =====================================================================
     | Laboratory (resulted tests on services linked in the Service Linking window)
     * ===================================================================== */

    protected function resultedLabs(string $indicator, Carbon $from, Carbon $to, ?array $patientIds = null): Collection
    {
        $ids = NhmisServiceMapping::getServiceIds($indicator);
        if (!$ids || ($patientIds !== null && !$patientIds)) {
            return collect();
        }

        return LabServiceRequest::query()
            ->whereIn('service_id', $ids)
            ->when($patientIds !== null, fn ($q) => $q->whereIn('patient_id', $patientIds))
            ->whereRaw('COALESCE(sample_date, created_at) BETWEEN ? AND ?', [$from, $to])
            ->where(function ($q) {
                $q->where(fn ($q2) => $q2->whereNotNull('result')->where('result', '<>', ''))
                    ->orWhereNotNull('result_data')
                    ->orWhereNotNull('nhmis_outcome');
            })
            ->with('service:id,service_name')
            ->get(['id', 'patient_id', 'service_id', 'result', 'result_data', 'nhmis_outcome', 'nhmis_outcome_raw', 'sample_date', 'result_date', 'created_at']);
    }

    /** A test belongs to the day it was done (sample taken), not the day the result was typed in. */
    protected static function labDate($lr): Carbon
    {
        return Carbon::parse($lr->sample_date ?? $lr->created_at);
    }

    /**
     * Lab-based rows need at least one service linked to the indicator; otherwise they are manual.
     */
    protected function requireLinks(string $indicator, array $cells, string $label): bool
    {
        if (NhmisServiceMapping::getServiceIds($indicator)) {
            return true;
        }
        foreach ($cells as $c) {
            $this->markManual($c, "No {$label} service is linked to this indicator. Link it in Service Links to compute this row.");
        }

        return false;
    }

    /**
     * Positive / reactive result. Stored NHMIS outcome wins; otherwise the result is parsed.
     */
    public function isLabResultPositive($labRequest, string $testType): bool
    {
        if (!empty($labRequest->nhmis_outcome)) {
            return $labRequest->nhmis_outcome === 'positive';
        }

        $data = $labRequest->result_data;
        if (is_string($data)) {
            $data = json_decode($data, true);
        }
        if (is_array($data)) {
            foreach ($data['parameters'] ?? [] as $pData) {
                if (!is_array($pData)) {
                    continue;
                }
                $status = strtolower((string) ($pData['status'] ?? ''));
                $val = strtolower(trim((string) ($pData['value'] ?? '')));
                if (in_array($status, ['positive', 'reactive', 'abnormal'], true)) {
                    return true;
                }
                if (preg_match('/^(positive|reactive|seen|detected|present|\+{1,4})$/', $val)) {
                    return true;
                }
            }
        }

        $text = self::cleanText($labRequest->result ?? '');
        if ($text === '') {
            return false;
        }

        if (in_array($testType, ['malaria_microscopy', 'malaria_rdt'], true)) {
            if (preg_match('/(not\s*seen|\bneg(ative)?\b|\bnil\b|no\s*malaria|none\s*seen|absent|not\s*detected)/', $text)) {
                return false;
            }

            return (bool) preg_match('/(\+{1,4}|positive|\bpos\b|\bseen\b|present|detected)/', $text);
        }
        if ($testType === 'tb_afb') {
            if (preg_match('/(not\s*seen|negative|\bneg\b|\bnil\b|none\s*seen|absent|not\s*detected)/', $text)) {
                return false;
            }

            return (bool) preg_match('/(\bseen\b|positive|\bpos\b|detected|\b[1-3]\+)/', $text);
        }
        if (preg_match('/(\bnr\b|non[\s\-]*reactive|negative|\bneg\b|\bnil\b|not\s*detected)/', $text)) {
            return false;
        }

        return (bool) preg_match('/(reactive|positive|\bpos\b|detected)/', $text);
    }

    /** External referrals (and transfer-out discharges) for these patients in the period. */
    protected function externalReferralPatients(array $patientIds): array
    {
        if (!$patientIds) {
            return [];
        }
        $ids = DB::table('specialist_referrals')
            ->whereNull('deleted_at')
            ->where('referral_type', 'external')
            ->whereIn('patient_id', $patientIds)
            ->whereBetween('created_at', [$this->from, $this->to])
            ->pluck('patient_id')->all();

        return array_fill_keys($ids, true);
    }

    /* =====================================================================
     | Immunization classifiers (shared with Nursing outreach tallies)
     * ===================================================================== */

    protected static function has(string $text, string $term): bool
    {
        return (bool) preg_match('/(?<![a-z0-9])' . preg_quote($term, '/') . '(?![a-z0-9])/', $text);
    }

    public static function resolveAntigenRow(ImmunizationRecord $r): ?string
    {
        $vac = strtolower(trim($r->vaccine_name ?? ''));
        $dose = strtolower(trim((string) ($r->dose ?? '')));
        $doseNum = $r->dose_number !== null ? (int) $r->dose_number : null;
        if ($doseNum === null && preg_match('/(\d)/', $dose, $m)) {
            $doseNum = (int) $m[1];
        }
        $isBirth = $doseNum === 0 || str_contains($dose, 'birth');

        if (str_contains($vac, 'fully immuni')) {
            return 'row_83';
        }
        if (self::has($vac, 'hpv') || str_contains($vac, 'papilloma')) {
            return 'row_87';
        }
        if (self::has($vac, 'men-a') || self::has($vac, 'men a') || self::has($vac, 'mena') || str_contains($vac, 'meningitis')) {
            return 'row_86';
        }
        if (str_contains($vac, 'yellow fever') || self::has($vac, 'yf')) {
            return 'row_84';
        }
        if (self::has($vac, 'ipv') || str_contains($vac, 'inactivated polio')) {
            return 'row_80';
        }
        if (self::has($vac, 'bcg')) {
            return 'row_67';
        }
        if (str_contains($vac, 'vitamin a')) {
            return $doseNum !== null && $doseNum >= 2 ? null : 'row_81';
        }
        if (str_contains($vac, 'measles') || self::has($vac, 'mcv') || self::has($vac, 'mr')) {
            return $doseNum === 2 ? 'row_85' : 'row_82';
        }
        if (self::has($vac, 'hepb') || self::has($vac, 'hbv') || str_contains($vac, 'hepatitis b')) {
            return $isBirth || $doseNum === null ? 'row_66' : null;
        }
        $series = [
            'opv' => [0 => 'row_65', 1 => 'row_68', 2 => 'row_72', 3 => 'row_76'],
            'penta' => [1 => 'row_69', 2 => 'row_73', 3 => 'row_77'],
            'pcv' => [1 => 'row_70', 2 => 'row_74', 3 => 'row_78'],
            'rota' => [1 => 'row_71', 2 => 'row_75', 3 => 'row_79'],
        ];
        $family = null;
        if (self::has($vac, 'opv') || (str_contains($vac, 'polio') && !self::has($vac, 'ipv'))) {
            $family = 'opv';
        } elseif (str_contains($vac, 'penta') || str_contains($vac, 'dtp-hepb-hib')) {
            $family = 'penta';
        } elseif (self::has($vac, 'pcv') || str_contains($vac, 'pneumococcal')) {
            $family = 'pcv';
        } elseif (str_contains($vac, 'rota')) {
            $family = 'rota';
        }
        if ($family) {
            $n = $isBirth ? 0 : $doseNum;

            return $n !== null ? ($series[$family][$n] ?? null) : null;
        }

        return null;
    }

    /**
     * Td for women. Pregnancy status is taken from the maternity enrolment on the vaccination date.
     */
    public static function resolveTdRowAndDose(ImmunizationRecord $r, ?bool $pregnant = null): ?array
    {
        $vac = strtolower(trim($r->vaccine_name ?? ''));
        if (!(self::has($vac, 'td') || self::has($vac, 'tt') || str_contains($vac, 'tetanus'))) {
            return null;
        }
        if (str_contains($vac, 'penta') || self::has($vac, 'dtp')) {
            return null;
        }
        $doseNum = $r->dose_number !== null ? (int) $r->dose_number : null;
        if ($doseNum === null && preg_match('/(\d)/', (string) $r->dose, $m)) {
            $doseNum = (int) $m[1];
        }
        $doseIdx = ($doseNum !== null && $doseNum >= 1 && $doseNum <= 5) ? $doseNum : 1;

        if ($pregnant === null) {
            $tg = strtolower((string) $r->target_group);
            $notes = strtolower((string) $r->notes);
            $pregnant = !str_contains($tg . $notes, 'non-pregnant') && !str_contains($tg . $notes, 'non_pregnant')
                && (str_contains($tg, 'pregnant') || str_contains($notes, 'pregnant')
                    || ($r->patient_id && MaternityEnrollment::where('patient_id', $r->patient_id)->where('status', 'active')->exists()));
        }

        return ['row_id' => $pregnant ? 'row_63' : 'row_64', 'col_key' => "td{$doseIdx}"];
    }
}
