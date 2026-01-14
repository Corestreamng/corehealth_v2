<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Hmo;
use App\Models\Product;
use App\Models\service;
use App\Models\HmoTariff;

class GenerateHmoTariffs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hmo:generate-tariffs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate HMO tariffs for all existing HMO-Product and HMO-Service combinations';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting HMO tariff generation...');

        $hmos = Hmo::where('status', 1)->get();
        $products = Product::all();
        $services = service::all();

        if ($hmos->isEmpty()) {
            $this->warn('No active HMOs found.');
            return 0;
        }

        $totalTariffs = 0;
        $skipped = 0;

        $progressBar = $this->output->createProgressBar($hmos->count() * ($products->count() + $services->count()));
        $progressBar->start();

        foreach ($hmos as $hmo) {
            $tariffs = [];

            // Generate product tariffs
            foreach ($products as $product) {
                $progressBar->advance();

                $exists = HmoTariff::where('hmo_id', $hmo->id)
                    ->where('product_id', $product->id)
                    ->whereNull('service_id')
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                $price = $product->price ? $product->price->current_sale_price : 0;
                $tariffs[] = [
                    'hmo_id' => $hmo->id,
                    'product_id' => $product->id,
                    'service_id' => null,
                    'claims_amount' => 0,
                    'payable_amount' => $price,
                    'coverage_mode' => 'primary',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Generate service tariffs
            foreach ($services as $service) {
                $progressBar->advance();

                $exists = HmoTariff::where('hmo_id', $hmo->id)
                    ->where('service_id', $service->id)
                    ->whereNull('product_id')
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                $price = $service->price ? $service->price->sale_price : 0;
                $tariffs[] = [
                    'hmo_id' => $hmo->id,
                    'product_id' => null,
                    'service_id' => $service->id,
                    'claims_amount' => 0,
                    'payable_amount' => $price,
                    'coverage_mode' => 'primary',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Insert in chunks for better performance
            if (!empty($tariffs)) {
                foreach (array_chunk($tariffs, 500) as $chunk) {
                    HmoTariff::insert($chunk);
                    $totalTariffs += count($chunk);
                }
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("Tariff generation completed!");
        $this->info("Created: {$totalTariffs} tariffs");
        $this->info("Skipped (already exist): {$skipped}");

        return 0;
    }
}
