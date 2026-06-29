<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;

class MigrateLegacyFamilyFolders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'family:migrate-legacy';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate legacy file_no based family groups to explicit principal/beneficiary relationships.';

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
        $this->info('Starting legacy family folder migration...');

        // Find all file_nos that have more than 1 patient associated
        $fileNos = Patient::select('file_no')
            ->whereNotNull('file_no')
            ->where('file_no', '!=', '')
            ->groupBy('file_no')
            ->havingRaw('COUNT(id) > 1')
            ->pluck('file_no');

        $this->info('Found ' . $fileNos->count() . ' family groups to migrate.');

        DB::beginTransaction();
        try {
            foreach ($fileNos as $fileNo) {
                // Get all patients with this file_no, ordered by creation (oldest is likely the principal)
                $patients = Patient::where('file_no', $fileNo)->orderBy('id', 'asc')->get();

                if ($patients->isEmpty()) continue;

                $principal = $patients->first();
                $principal->is_family_principal = true;
                $principal->principal_id = null;
                $principal->save();

                $beneficiaries = $patients->slice(1);
                foreach ($beneficiaries as $beneficiary) {
                    $beneficiary->is_family_principal = false;
                    $beneficiary->principal_id = $principal->id;
                    $beneficiary->save();
                }

                $this->info("Migrated file_no: {$fileNo} with 1 principal and " . $beneficiaries->count() . " beneficiaries.");
            }
            DB::commit();
            $this->info('Migration completed successfully.');
            return 0;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Migration failed: ' . $e->getMessage());
            return 1;
        }
    }
}
