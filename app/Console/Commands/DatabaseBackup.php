<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DatabaseBackup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:database
                            {--no-prune : Skip pruning old backups}
                            {--no-replicate : Skip replicating to external drives}
                            {--retention=7 : Number of days to retain backups}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a database backup, prune old backups, and replicate to mounted drives';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $service = new BackupService();

        $this->info('╔══════════════════════════════════════╗');
        $this->info('║   CoreHealth Database Backup Tool    ║');
        $this->info('╚══════════════════════════════════════╝');
        $this->newLine();

        // Step 1: Create backup
        $this->info('📦 Creating database backup...');
        $result = $service->createBackup();

        if (!$result['success']) {
            $this->error('✗ Backup failed: ' . $result['message']);
            Log::channel('backup')->error('Scheduled backup failed: ' . $result['message']);
            return 1;
        }

        $this->info('✓ ' . $result['message']);

        // Step 2: Prune old backups
        if (!$this->option('no-prune')) {
            $retention = (int) $this->option('retention');
            $this->newLine();
            $this->info("🗑  Pruning backups older than {$retention} days...");
            $pruneResult = $service->pruneOldBackups($retention);
            $this->info("✓ Deleted {$pruneResult['deleted']} old backups, kept {$pruneResult['kept']}");

            if (!empty($pruneResult['files'])) {
                foreach ($pruneResult['files'] as $file) {
                    $this->line("  - Removed: {$file}");
                }
            }
        }

        // Step 3: Replicate to external drives
        if (!$this->option('no-replicate')) {
            $this->newLine();
            $this->info('💾 Replicating to mounted drives...');
            $replicateResult = $service->replicateToExternalDrives($result['filename']);

            if ($replicateResult['total'] === 0) {
                $this->line('  No external drives found for replication');
            } else {
                $this->info("✓ Replicated to {$replicateResult['success']}/{$replicateResult['total']} drives");

                foreach ($replicateResult['results'] as $r) {
                    $icon = $r['success'] ? '  ✓' : '  ✗';
                    $this->line("{$icon} [{$r['drive']}] {$r['mount']}: {$r['message']}");
                }
            }
        }

        $this->newLine();
        $this->info('═══════════════════════════════════════');
        $this->info('  Backup complete: ' . $result['filename']);
        $this->info('═══════════════════════════════════════');

        Log::channel('backup')->info('Scheduled backup completed successfully: ' . $result['filename']);

        return 0;
    }
}
