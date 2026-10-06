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

                    if ($val === 'true' || $val === '1' || $val === 'positive' || $val === 'reactive' || $status === 'abnormal' || $status === 'high' || $status === 'positive') {
                        return ['outcome' => 'positive', 'raw' => $p['value'] ?? 'Positive (Template V2)'];
                    }

                    if ($val === 'false' || $val === '0' || $val === 'negative' || $val === 'non-reactive' || $status === 'normal' || $status === 'negative') {
                        return ['outcome' => 'negative', 'raw' => $p['value'] ?? 'Negative (Template V2)'];
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

        // Malaria
        if (str_contains($indicatorCode, 'malaria')) {
            // Negative precedence
            if (preg_match('/(not\s*seen|neg|nil|no\s*malaria|none\s*seen|absent|not\s*detected)/i', $text)) {
                return ['outcome' => 'negative', 'raw' => 'Negative / Not Seen'];
            }
            if (preg_match('/(\+{1,4}|\[\+\]|\(\+\)|positive|\bpos\b|\bseen\b|present|detected)/i', $text)) {
                // Determine grade if possible
                if (str_contains($text, '++++')) {
                    $grade = 'Positive (++++)';
                } elseif (str_contains($text, '+++')) {
                    $grade = 'Positive (+++)';
                } elseif (str_contains($text, '++')) {
                    $grade = 'Positive (++)';
                } else {
                    $grade = 'Positive (+)';
                }

                return ['outcome' => 'positive', 'raw' => $grade];
            }

            return ['outcome' => 'indeterminate', 'raw' => substr($text, 0, 40)];
        }

        // Serology (HIV, Syphilis, Hep B, Hep C)
        if (str_contains($indicatorCode, 'hiv') || str_contains($indicatorCode, 'syphilis') || str_contains($indicatorCode, 'hepatitis')) {
            if (preg_match('/(\bnr\b|non[\s\-]*re?a?c?tive|negative|\bneg\b|nil|not\s*seen|absent|not\s*detected)/i', $text)) {
                return ['outcome' => 'negative', 'raw' => 'Non-Reactive'];
            }
            if (preg_match('/(reactive|positive|\bpos\b|detected|present)/i', $text)) {
                return ['outcome' => 'positive', 'raw' => 'Reactive'];
            }

            return ['outcome' => 'indeterminate', 'raw' => substr($text, 0, 40)];
        }

        // TB Sputum AFB / GeneXpert
        if (str_contains($indicatorCode, 'tb') || str_contains($indicatorCode, 'sputum')) {
            if (preg_match('/(not\s*seen|negative|\bneg\b|nil|none\s*seen|absent|not\s*detected)/i', $text)) {
                return ['outcome' => 'negative', 'raw' => 'Negative / Not Seen'];
            }
            if (preg_match('/(\bseen\b|positive|\bpos\b|afb\s*positive|\bdetected\b|\b1\+\b|\b2\+\b|\b3\+\b)/i', $text)) {
                return ['outcome' => 'positive', 'raw' => 'Positive / Detected'];
            }

            return ['outcome' => 'indeterminate', 'raw' => substr($text, 0, 40)];
        }

        // General fallback
        if (preg_match('/(negative|\bneg\b|non[\s\-]*reactive|not\s*seen|nil)/i', $text)) {
            return ['outcome' => 'negative', 'raw' => 'Negative'];
        }
        if (preg_match('/(positive|\bpos\b|reactive|\+{1,4}|detected)/i', $text)) {
            return ['outcome' => 'positive', 'raw' => 'Positive'];
        }

        return ['outcome' => 'indeterminate', 'raw' => substr($text, 0, 40)];
    }
}
