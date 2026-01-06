<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AdmissionRequest;
use App\Models\ProductOrServiceRequest;
use App\Helpers\HmoHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessDailyBedBills extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'beds:process-daily-bills';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process daily bed billing for all occupied beds';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Processing daily bed bills...');

        try {
            // Get all active admissions with assigned beds
            $admissions = AdmissionRequest::with(['patient.user', 'patient.hmo'])
                ->where('discharged', 0)
                ->where('status', 1)
                ->whereNotNull('bed_id')
                ->whereNotNull('bed_assign_date')
                ->whereNotNull('service_id')
                ->get();

            if ($admissions->isEmpty()) {
                $this->info('No occupied beds found.');
                return Command::SUCCESS;
            }

            $billsCreated = 0;
            $billsSkipped = 0;
            $errors = 0;

            foreach ($admissions as $admission) {
                try {
                    // Check if a bill has already been created for today
                    $today = Carbon::today();
                    $existingBill = ProductOrServiceRequest::where('user_id', $admission->patient->user->id)
                        ->where('service_id', $admission->service_id)
                        ->whereDate('created_at', $today)
                        ->whereHas('service', function($q) {
                            $q->where('category_id', function($sq) {
                                $sq->selectRaw('id')
                                    ->from('service_categories')
                                    ->where('category_name', 'LIKE', '%bed%')
                                    ->orWhere('category_name', 'LIKE', '%admission%')
                                    ->limit(1);
                            });
                        })
                        ->first();

                    if ($existingBill) {
                        $this->line("Skipped: Bill already exists for patient {$admission->patient->user->surname} (ID: {$admission->patient_id})");
                        $billsSkipped++;
                        continue;
                    }

                    // Create daily bed bill
                    DB::beginTransaction();

                    $bill_req = new ProductOrServiceRequest();
                    $bill_req->user_id = $admission->patient->user->id;
                    $bill_req->staff_user_id = 1; // System user
                    $bill_req->service_id = $admission->service_id;
                    $bill_req->qty = 1; // One day
                    $bill_req->created_at = Carbon::now();

                    // Apply HMO tariff if patient has HMO
                    if ($admission->patient->hmo_id) {
                        try {
                            $hmoData = HmoHelper::applyHmoTariff(
                                $admission->patient_id,
                                null,
                                $admission->service_id
                            );
                            if ($hmoData) {
                                $bill_req->payable_amount = $hmoData['payable_amount'];
                                $bill_req->claims_amount = $hmoData['claims_amount'];
                                $bill_req->coverage_mode = $hmoData['coverage_mode'];
                                $bill_req->validation_status = $hmoData['validation_status'];
                            }
                        } catch (\Exception $e) {
                            Log::warning("HMO tariff error for patient {$admission->patient_id}: " . $e->getMessage());
                            // Continue with cash pricing if HMO fails
                        }
                    }

                    $bill_req->save();

                    DB::commit();

                    $this->info("✓ Created bed bill for patient {$admission->patient->user->surname} (ID: {$admission->patient_id})");
                    $billsCreated++;

                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error("Error creating bed bill for admission {$admission->id}: " . $e->getMessage());
                    $this->error("✗ Error processing patient ID {$admission->patient_id}: " . $e->getMessage());
                    $errors++;
                }
            }

            $this->info("\n=== Summary ===");
            $this->info("Bills created: {$billsCreated}");
            $this->info("Bills skipped: {$billsSkipped}");
            $this->info("Errors: {$errors}");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            Log::error("Fatal error in ProcessDailyBedBills: " . $e->getMessage());
            $this->error("Fatal error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
