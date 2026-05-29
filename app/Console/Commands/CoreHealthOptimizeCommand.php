<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class CoreHealthOptimizeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'corehealth:optimize';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Runs all performance optimization commands for production deployment';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting CoreHealth Performance Optimizations...');

        // 1. Clear existing caches
        $this->line('Clearing caches...');
        Artisan::call('cache:clear');
        Artisan::call('view:clear');
        Artisan::call('route:clear');
        Artisan::call('config:clear');

        // 2. Cache configuration
        $this->line('Caching configuration...');
        Artisan::call('config:cache');
        
        // 3. Cache routes
        $this->line('Caching routes...');
        Artisan::call('route:cache');

        // 4. Cache views
        $this->line('Caching views...');
        Artisan::call('view:cache');

        // 5. Run standard Laravel optimize
        $this->line('Running Laravel optimize...');
        Artisan::call('optimize');

        $this->info('CoreHealth optimization complete! Your application is now ready for high traffic.');
        
        // Output hint about indexes
        $this->warn('Note: Do not forget to run "php artisan migrate" to apply the new performance indexes if you have not already.');

        return 0;
    }
}
