<?php

namespace App\Console\Commands;

use App\Models\NhmisServiceMapping;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NhmisClassifyHistoricalLabRecords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nhmis:backfill-classifications {--force : Re-classify records that already have an nhmis_outcome} {--limit= : Limit the number of records to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Classify historical laboratory records against active NHMIS indicator outcome rules';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('===============================================================');
        $this->info('NHMIS HISTORICAL LAB RESULT CLASSIFICATION & BACKFILL ENGINE');
        $this->info('===============================================================');

        $mappings = NhmisServiceMapping::all()->groupBy('indicator_code');
        if ($mappings->isEmpty()) {
            $this->warn('No NHMIS service mappings found. Populating default facility delegations first...');
            $added = NhmisServiceMapping::autoPopulateDefaults();
            $this->info("Populated {$added} default delegations.");
            $mappings = NhmisServiceMapping::all()->groupBy('indicator_code');
        }

        $allServiceIds = NhmisServiceMapping::where('service_type', 'investigation')
            ->orWhereNull('service_type')
            ->pluck('service_id')
            ->unique()
            ->toArray();

        if (empty($allServiceIds)) {
            $this->error('No mapped investigation services found.');

            return 1;
        }

        $this->info('Active mapped service IDs: ' . implode(', ', $allServiceIds));

        $query = DB::table('lab_service_requests')
            ->whereIn('service_id', $allServiceIds)
            ->whereNotNull('result')
            ->where('result', '!=', '');

        if (!$this->option('force')) {
            $query->whereNull('nhmis_outcome');
        }

        $total = $query->count();
        $this->info("Found {$total} laboratory records to evaluate.");

        if ($total === 0) {
            $this->info('All records are already classified. Use --force to re-evaluate.');

            return 0;
        }

        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        if ($limit) {
            $query->limit($limit);
            $total = min($total, $limit);
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $stats = [
            'positive' => 0,
            'negative' => 0,
            'indeterminate' => 0,
        ];

        // Process in chunks of 500
        $processed = 0;
        $mappingsByServiceId = NhmisServiceMapping::all()->keyBy('service_id');

        DB::table('lab_service_requests')
            ->whereIn('service_id', $allServiceIds)
            ->whereNotNull('result')
            ->where('result', '!=', '')
            ->when(!$this->option('force'), fn ($q) => $q->whereNull('nhmis_outcome'))
            ->when($limit, fn ($q) => $q->limit($limit))
            ->orderBy('id')
            ->chunk(500, function ($records) use ($mappingsByServiceId, $bar, &$stats, &$processed) {
                $now = now();
                foreach ($records as $r) {
                    $mapping = $mappingsByServiceId[$r->service_id] ?? null;
                    $indicatorCode = $mapping ? $mapping->indicator_code : 'unknown';

                    $classification = self::classifyRecord($r->result, $r->result_data, $indicatorCode);
                    $outcome = $classification['outcome'];
                    $raw = $classification['raw'];

                    DB::table('lab_service_requests')
                        ->where('id', $r->id)
                        ->update([
                            'nhmis_outcome' => $outcome,
                            'nhmis_outcome_raw' => $raw,
                            'nhmis_classified_at' => $now,
                        ]);

                    $stats[$outcome] = ($stats[$outcome] ?? 0) + 1;
                    $processed++;
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);

        $this->info('Classification Summary:');
        $this->table(
            ['Outcome Status', 'Record Count', 'Percentage'],
            [
                ['Positive', $stats['positive'], $processed > 0 ? round(($stats['positive'] / $processed) * 100, 1) . '%' : '0%'],
                ['Negative', $stats['negative'], $processed > 0 ? round(($stats['negative'] / $processed) * 100, 1) . '%' : '0%'],
                ['Indeterminate / Unclear', $stats['indeterminate'], $processed > 0 ? round(($stats['indeterminate'] / $processed) * 100, 1) . '%' : '0%'],
                ['Total Processed', $processed, '100%'],
            ]
        );

        return 0;
    }

    /**
     * Deterministic, clinically validated classifier for lab records
     */
    public static function classifyRecord(?string $rawResult, $resultData, string $indicatorCode): array
    {
        // 1. Evaluate V2 Structured Template first if present
        if (!empty($resultData)) {
            $parsed = is_string($resultData) ? json_decode($resultData, true) : $resultData;
            if (is_array($parsed)) {
                $params = $parsed['parameters'] ?? $parsed;
                foreach ($params as $p) {
                    if (!is_array($p)) {
                        continue;
                    }
                    $status = strtolower($p['status'] ?? '');
                    $val = strtolower((string) ($p['value'] ?? ''));

                    // Indeterminate
                    if (str_contains($val, 'indeterminate') || str_contains($val, 'inconclusive')) {
                        return ['outcome' => 'indeterminate', 'raw' => 'Indeterminate'];
                    }

                    // Malaria specific V2 structured values
                    if (str_contains($indicatorCode, 'malaria')) {
                        if (str_contains($val, '++++') || str_contains($val, '4+')) {
                            return ['outcome' => 'positive', 'raw' => 'Positive (++++)'];
                        }
                        if (str_contains($val, '+++') || str_contains($val, '3+')) {
                            return ['outcome' => 'positive', 'raw' => 'Positive (+++)'];
                        }
                        if (str_contains($val, '++') || str_contains($val, '2+')) {
                            return ['outcome' => 'positive', 'raw' => 'Positive (++)'];
                        }
                        if (str_contains($val, '+') || str_contains($val, '1+') || $val === 'positive' || $status === 'abnormal' || $status === 'positive' || $status === 'high') {
                            return ['outcome' => 'positive', 'raw' => 'Positive (+)'];
                        }
                        if ($val === 'false' || $val === '0' || $val === 'negative' || $val === 'nps' || str_contains($val, 'not seen') || $status === 'normal' || $status === 'negative') {
                            return ['outcome' => 'negative', 'raw' => 'Negative'];
                        }
                    }

                    if ($val === 'true' || $val === '1' || $val === 'positive' || $val === 'reactive' || $status === 'abnormal' || $status === 'high' || $status === 'positive') {
                        return ['outcome' => 'positive', 'raw' => $p['value'] ?? 'Positive'];
                    }

                    if ($val === 'false' || $val === '0' || $val === 'negative' || $val === 'non-reactive' || $status === 'normal' || $status === 'negative') {
                        return ['outcome' => 'negative', 'raw' => $p['value'] ?? 'Negative'];
                    }
                }
            }
        }

        // 2. Evaluate V1 HTML / Free-text
        if (empty($rawResult)) {
            return ['outcome' => 'indeterminate', 'raw' => 'Empty'];
        }

        $text = strtolower(strip_tags(html_entity_decode($rawResult, ENT_QUOTES, 'UTF-8')));
        $text = preg_replace('/\s+/', ' ', trim($text));

        // Malaria (Microscopy & mRDT)
        if (str_contains($indicatorCode, 'malaria')) {
            // Negative precedence (covers NPS, no malaria parasites seen, no parasite seen, negative, nil, not seen, not detected, absent)
            if (preg_match('/(not\s*seen|neg|nil|no\s*malaria|no\s*parasite|none\s*seen|absent|not\s*detected|\bnps\b)/i', $text)) {
                return ['outcome' => 'negative', 'raw' => 'Negative'];
            }

            // Indeterminate
            if (preg_match('/(indeterminate|inconclusive|doubtful|repeat\s*test)/i', $text)) {
                return ['outcome' => 'indeterminate', 'raw' => 'Indeterminate'];
            }

            // Positive grades from 4+ down to 1+
            if (preg_match('/(\+{4}|4\s*\+|positive\s*\(\+{4}\)|positive\s*4\+)/i', $text)) {
                return ['outcome' => 'positive', 'raw' => 'Positive (++++)'];
            }
            if (preg_match('/(\+{3}|3\s*\+|positive\s*\(\+{3}\)|positive\s*3\+)/i', $text)) {
                return ['outcome' => 'positive', 'raw' => 'Positive (+++)'];
            }
            if (preg_match('/(\+{2}|2\s*\+|positive\s*\(\+{2}\)|positive\s*2\+)/i', $text)) {
                return ['outcome' => 'positive', 'raw' => 'Positive (++)'];
            }
            if (preg_match('/(\+{1}|1\s*\+|\[\+\]|\(\+\)|positive|\bpos\b|\bseen\b|present|detected)/i', $text)) {
                return ['outcome' => 'positive', 'raw' => 'Positive (+)'];
            }

            return ['outcome' => 'indeterminate', 'raw' => 'Indeterminate'];
        }

        // Serology (HIV, Syphilis, Hep B, Hep C)
        if (str_contains($indicatorCode, 'hiv') || str_contains($indicatorCode, 'syphilis') || str_contains($indicatorCode, 'hepatitis')) {
            if (preg_match('/(indeterminate|inconclusive|doubtful)/i', $text)) {
                return ['outcome' => 'indeterminate', 'raw' => 'Indeterminate'];
            }
            if (preg_match('/(\bnr\b|non[\s\-]*re?a?c?tive|negative|\bneg\b|nil|not\s*seen|absent|not\s*detected)/i', $text)) {
                return ['outcome' => 'negative', 'raw' => 'Non-Reactive'];
            }
            if (preg_match('/(reactive|positive|\bpos\b|detected|present)/i', $text)) {
                return ['outcome' => 'positive', 'raw' => 'Reactive'];
            }

            return ['outcome' => 'indeterminate', 'raw' => 'Indeterminate'];
        }

        // Chest X-Ray (Presumptive TB Screening) - evaluate before sputum TB to avoid indicator code collision
        if (str_contains($indicatorCode, 'chest_xray') || str_contains($indicatorCode, 'cxr')) {
            if (preg_match('/(tb|infiltrate|cavitary|consolidation|apical|tuberculosis|presumptive)/i', $text)) {
                return ['outcome' => 'positive', 'raw' => 'Abnormal (TB Presumptive)'];
            }
            if (preg_match('/(cardiomegaly|pleural\s*effusion|mass|opacity|other\s*abnormal)/i', $text)) {
                return ['outcome' => 'indeterminate', 'raw' => 'Other Abnormalities'];
            }
            if (preg_match('/(clear|normal|unremarkable|no\s*active|clear\s*lung)/i', $text)) {
                return ['outcome' => 'negative', 'raw' => 'Normal / Clear'];
            }

            return ['outcome' => 'indeterminate', 'raw' => 'Other Abnormalities'];
        }

        // TB Sputum AFB / GeneXpert
        if (str_contains($indicatorCode, 'tb_sputum') || str_contains($indicatorCode, 'sputum') || $indicatorCode === 'tb') {
            if (preg_match('/(indeterminate|inconclusive|invalid|error)/i', $text)) {
                return ['outcome' => 'indeterminate', 'raw' => 'Indeterminate'];
            }
            if (preg_match('/(mtb\s*not\s*detected|genexpert\s*negative)/i', $text)) {
                return ['outcome' => 'negative', 'raw' => 'MTB Not Detected'];
            }
            if (preg_match('/(not\s*seen|negative|\bneg\b|nil|none\s*seen|absent|not\s*detected|no\s*afb)/i', $text)) {
                return ['outcome' => 'negative', 'raw' => 'Negative (Not Seen)'];
            }
            if (preg_match('/(mtb\s*detected|genexpert\s*positive)/i', $text)) {
                return ['outcome' => 'positive', 'raw' => 'MTB Detected'];
            }
            if (preg_match('/(\b3\s*\+|\+{3}|positive\s*3\+|positive\s*\(\+{3}\))/i', $text)) {
                return ['outcome' => 'positive', 'raw' => 'Positive (3+)'];
            }
            if (preg_match('/(\b2\s*\+|\+{2}|positive\s*2\+|positive\s*\(\+{2}\))/i', $text)) {
                return ['outcome' => 'positive', 'raw' => 'Positive (2+)'];
            }
            if (preg_match('/(\b1\s*\+|\+{1}|positive\s*1\+|positive\s*\(\+{1}\)|positive|\bpos\b|afb\s*seen|\bseen\b)/i', $text)) {
                return ['outcome' => 'positive', 'raw' => 'Positive (1+)'];
            }

            return ['outcome' => 'indeterminate', 'raw' => 'Indeterminate'];
        }

        // Obstetric Ultrasound Scan
        if (str_contains($indicatorCode, 'ultrasound') || str_contains($indicatorCode, 'scan')) {
            if (preg_match('/(abnormal|complication|miscarriage|ectopic|dead|non-viable|previa|praevia|oligohydramnios|polyhydramnios)/i', $text)) {
                return ['outcome' => 'positive', 'raw' => 'Abnormal / Complications'];
            }
            if (preg_match('/(viable|normal|single\s*intrauterine|good\s*liquor|cardiac\s*activity|unremarkable)/i', $text)) {
                return ['outcome' => 'negative', 'raw' => 'Normal / Viable'];
            }
        }

        // Pregnancy Test
        if (str_contains($indicatorCode, 'pregnancy') || str_contains($indicatorCode, 'pt')) {
            if (preg_match('/(negative|\bneg\b|not\s*pregnant|nil)/i', $text)) {
                return ['outcome' => 'negative', 'raw' => 'Negative'];
            }
            if (preg_match('/(positive|\bpos\b|pregnant)/i', $text)) {
                return ['outcome' => 'positive', 'raw' => 'Positive'];
            }
        }

        // Qualitative / Lab Chemistry / Hematology
        if (str_contains($indicatorCode, 'protein') || str_contains($indicatorCode, 'glucose') || str_contains($indicatorCode, 'pcv') || str_contains($indicatorCode, 'urinalysis')) {
            if (preg_match('/(\+{4}|4\s*\+)/i', $text)) {
                return ['outcome' => 'positive', 'raw' => '4+'];
            }
            if (preg_match('/(\+{3}|3\s*\+)/i', $text)) {
                return ['outcome' => 'positive', 'raw' => '3+'];
            }
            if (preg_match('/(\+{2}|2\s*\+)/i', $text)) {
                return ['outcome' => 'positive', 'raw' => '2+'];
            }
            if (preg_match('/(\+{1}|1\s*\+|positive|\bpos\b)/i', $text)) {
                return ['outcome' => 'positive', 'raw' => '1+'];
            }
            if (preg_match('/(trace)/i', $text)) {
                return ['outcome' => 'negative', 'raw' => 'Trace'];
            }
            if (preg_match('/(negative|\bneg\b|nil|zero|normal)/i', $text)) {
                return ['outcome' => 'negative', 'raw' => 'Negative'];
            }
        }

        // Procedures
        if (str_contains($indicatorCode, 'caesarean') || str_contains($indicatorCode, 'mva') || str_contains($indicatorCode, 'tubal') || str_contains($indicatorCode, 'vasectomy') || str_contains($indicatorCode, 'fistula')) {
            if (preg_match('/(abort)/i', $text)) {
                return ['outcome' => 'negative', 'raw' => 'Aborted'];
            }
            if (preg_match('/(convert)/i', $text)) {
                return ['outcome' => 'negative', 'raw' => 'Converted'];
            }
            if (preg_match('/(no\s*complications?|without\s*complications?|success|uneventful)/i', $text)) {
                return ['outcome' => 'positive', 'raw' => 'Successful'];
            }
            if (preg_match('/(complication)/i', $text)) {
                return ['outcome' => 'negative', 'raw' => 'Complications'];
            }
            if (preg_match('/(complete|done)/i', $text)) {
                return ['outcome' => 'positive', 'raw' => 'Successful'];
            }
        }

        // General fallback
        if (preg_match('/(negative|\bneg\b|non[\s\-]*reactive|not\s*seen|nil)/i', $text)) {
            return ['outcome' => 'negative', 'raw' => 'Negative'];
        }
        if (preg_match('/(positive|\bpos\b|reactive|\+{1,4}|detected)/i', $text)) {
            return ['outcome' => 'positive', 'raw' => 'Positive'];
        }

        return ['outcome' => 'indeterminate', 'raw' => 'Indeterminate'];
    }
}
