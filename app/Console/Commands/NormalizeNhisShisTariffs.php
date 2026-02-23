<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Hmo;
use App\Models\HmoScheme;
use App\Models\HmoTariff;
use App\Models\Product;
use App\Models\service;
use App\Models\ServiceCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NormalizeNhisShisTariffs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hmo:normalize-nhis-shis
                            {--dry-run : Preview changes without applying them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Normalize NHIS and SHIS tariffs: drugs 10% payable / 90% claims, services 100% claims, General Consultation express, other consultations secondary';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('*** DRY RUN MODE — no changes will be saved ***');
        }

        $this->info('Normalizing NHIS and SHIS tariffs...');

        // 1. Find NHIS/NHIA and SHIS/PLASCHEMA schemes
        $schemes = HmoScheme::where(function ($q) {
            $q->whereIn('code', ['NHIS', 'SHIS', 'NHIA', 'PLASCHEMA'])
              ->orWhere('name', 'LIKE', '%NHIS%')
              ->orWhere('name', 'LIKE', '%NHIA%')
              ->orWhere('name', 'LIKE', '%SHIS%')
              ->orWhere('name', 'LIKE', '%PLASCHEMA%');
        })->get();

        if ($schemes->isEmpty()) {
            $this->error('No NHIS/NHIA or SHIS/PLASCHEMA schemes found. Run the HmoSchemeSeeder first.');
            return 1;
        }

        $this->info('Found schemes: ' . $schemes->pluck('name')->implode(', '));

        // 2. Get all HMOs under those schemes
        $hmos = Hmo::whereIn('hmo_scheme_id', $schemes->pluck('id'))
            ->where('status', 1)
            ->get();

        if ($hmos->isEmpty()) {
            $this->warn('No active HMOs found under NHIS/SHIS schemes.');
            return 0;
        }

        $this->info("Found {$hmos->count()} active HMO(s): " . $hmos->pluck('name')->implode(', '));

        // 3. Load all products and services
        $products = Product::all();
        $services = service::with('price', 'category')->get();

        // 4. Identify "General Consultation" services (case-insensitive match)
        $generalConsultationIds = $services->filter(function ($svc) {
            return preg_match('/^general\s+consultation$/i', trim($svc->service_name));
        })->pluck('id')->toArray();

        // 5. Identify all other consultation services (service name contains "consultation" but is NOT general consultation)
        $otherConsultationIds = $services->filter(function ($svc) use ($generalConsultationIds) {
            $isConsultation = stripos($svc->service_name, 'consultation') !== false;
            $isGeneral = in_array($svc->id, $generalConsultationIds);
            return $isConsultation && !$isGeneral;
        })->pluck('id')->toArray();

        $this->info("General Consultation services: " . count($generalConsultationIds));
        $this->info("Other Consultation services: " . count($otherConsultationIds));
        $this->info("Total products (drugs): " . $products->count());
        $this->info("Total services: " . $services->count());
        $this->newLine();

        $stats = [
            'drugs_created' => 0,
            'drugs_updated' => 0,
            'services_created' => 0,
            'services_updated' => 0,
            'general_consult_created' => 0,
            'general_consult_updated' => 0,
            'other_consult_created' => 0,
            'other_consult_updated' => 0,
        ];

        $progressTotal = $hmos->count() * ($products->count() + $services->count());
        $bar = $this->output->createProgressBar($progressTotal);
        $bar->start();

        foreach ($hmos as $hmo) {
            // ─── PRODUCTS (DRUGS): 10% payable, 90% claims ───
            foreach ($products as $product) {
                $bar->advance();

                $price = $product->price ? (float) $product->price->current_sale_price : 0;
                $payable = round($price * 0.10, 2);
                $claims  = round($price * 0.90, 2);

                $this->upsertTariff(
                    $hmo->id,
                    $product->id,
                    null,
                    $payable,
                    $claims,
                    'primary',
                    $dryRun,
                    $stats,
                    'drugs'
                );
            }

            // ─── SERVICES ───
            foreach ($services as $service) {
                $bar->advance();

                $price = $service->price ? (float) $service->price->sale_price : 0;

                if (in_array($service->id, $generalConsultationIds)) {
                    // General Consultation: 100% claims, coverage mode = express
                    $this->upsertTariff(
                        $hmo->id,
                        null,
                        $service->id,
                        0,           // payable
                        $price,      // claims = 100%
                        'express',
                        $dryRun,
                        $stats,
                        'general_consult'
                    );
                } elseif (in_array($service->id, $otherConsultationIds)) {
                    // Other Consultations: 100% claims, coverage mode = secondary
                    $this->upsertTariff(
                        $hmo->id,
                        null,
                        $service->id,
                        0,           // payable
                        $price,      // claims = 100%
                        'secondary',
                        $dryRun,
                        $stats,
                        'other_consult'
                    );
                } else {
                    // All other services: 100% claims, coverage mode = primary
                    $this->upsertTariff(
                        $hmo->id,
                        null,
                        $service->id,
                        0,           // payable
                        $price,      // claims = 100%
                        'primary',
                        $dryRun,
                        $stats,
                        'services'
                    );
                }
            }
        }

        $bar->finish();
        $this->newLine(2);

        // ─── Summary ───
        $this->info('═══════════════════════════════════════');
        $this->info('  NHIS/SHIS Tariff Normalization Summary');
        $this->info('═══════════════════════════════════════');
        $this->table(
            ['Category', 'Created', 'Updated'],
            [
                ['Drugs (10% payable / 90% claims)',       $stats['drugs_created'],           $stats['drugs_updated']],
                ['Services (100% claims)',                  $stats['services_created'],        $stats['services_updated']],
                ['General Consultation (express)',          $stats['general_consult_created'], $stats['general_consult_updated']],
                ['Other Consultations (secondary)',         $stats['other_consult_created'],   $stats['other_consult_updated']],
            ]
        );

        $totalCreated = $stats['drugs_created'] + $stats['services_created'] + $stats['general_consult_created'] + $stats['other_consult_created'];
        $totalUpdated = $stats['drugs_updated'] + $stats['services_updated'] + $stats['general_consult_updated'] + $stats['other_consult_updated'];

        $this->info("Total created: {$totalCreated} | Total updated: {$totalUpdated}");

        if ($dryRun) {
            $this->warn('DRY RUN — nothing was saved. Remove --dry-run to apply changes.');
        }

        Log::info("NHIS/SHIS tariff normalization completed. Created: {$totalCreated}, Updated: {$totalUpdated}" . ($dryRun ? ' (dry run)' : ''));

        return 0;
    }

    /**
     * Create or update a tariff row.
     *
     * Uses direct query to avoid HmoTariff boot validation when doing updateOrCreate
     * (the saving event validates product_id XOR service_id).
     */
    private function upsertTariff(
        int $hmoId,
        ?int $productId,
        ?int $serviceId,
        float $payableAmount,
        float $claimsAmount,
        string $coverageMode,
        bool $dryRun,
        array &$stats,
        string $category
    ): void {
        $existing = HmoTariff::where('hmo_id', $hmoId)
            ->where(function ($q) use ($productId) {
                $productId ? $q->where('product_id', $productId) : $q->whereNull('product_id');
            })
            ->where(function ($q) use ($serviceId) {
                $serviceId ? $q->where('service_id', $serviceId) : $q->whereNull('service_id');
            })
            ->first();

        if ($existing) {
            // Update existing tariff
            if (!$dryRun) {
                $existing->update([
                    'payable_amount' => $payableAmount,
                    'claims_amount'  => $claimsAmount,
                    'coverage_mode'  => $coverageMode,
                ]);
            }
            $stats[$category . '_updated']++;
        } else {
            // Create new tariff
            if (!$dryRun) {
                HmoTariff::create([
                    'hmo_id'         => $hmoId,
                    'product_id'     => $productId,
                    'service_id'     => $serviceId,
                    'payable_amount' => $payableAmount,
                    'claims_amount'  => $claimsAmount,
                    'coverage_mode'  => $coverageMode,
                ]);
            }
            $stats[$category . '_created']++;
        }
    }
}
