<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Product;
use App\Models\service;
use App\Models\Hmo;
use App\Models\AdmissionRequest;
use App\Models\ProductOrServiceRequest;
use App\Models\ChatConversation;
use App\Models\ChatParticipant;
use App\Models\User;
use App\Observers\ProductObserver;
use App\Observers\ServiceObserver;
use App\Observers\HmoObserver;
use App\Helpers\HmoHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Register HMO tariff auto-generation observers
        Product::observe(ProductObserver::class);
        service::observe(ServiceObserver::class);
        Hmo::observe(HmoObserver::class);

        // Process daily bed bills - runs once per day automatically
        $this->processDailyBedBills();

        // Sync HMO executives to messenger group - runs once per hour
        $this->syncHmoExecutivesGroup();
    }

    /**
     * Process daily bed billing for all occupied beds
     * Uses cache to ensure it only runs once per day
     */
    protected function processDailyBedBills()
    {
        try {
            // Check if already processed today using cache
            $cacheKey = 'bed_billing_processed_' . Carbon::today()->format('Y-m-d');

            if (Cache::has($cacheKey)) {
                return; // Already processed today
            }

            // Get all active admissions with assigned beds
            $admissions = AdmissionRequest::with(['patient.user', 'patient.hmo'])
                ->where('discharged', 0)
                ->where('status', 1)
                ->whereNotNull('bed_id')
                ->whereNotNull('bed_assign_date')
                ->whereNotNull('service_id')
                ->get();

            if ($admissions->isEmpty()) {
                // Mark as processed even if no beds to process
                Cache::put($cacheKey, true, Carbon::tomorrow());
                return;
            }

            $billsCreated = 0;

            foreach ($admissions as $admission) {
                try {
                    // Check if a bill has already been created for today
                    $today = Carbon::today();
                    $existingBill = ProductOrServiceRequest::where('user_id', $admission->patient->user->id)
                        ->where('service_id', $admission->service_id)
                        ->whereDate('created_at', $today)
                        ->first();

                    if ($existingBill) {
                        continue; // Skip if bill already exists
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
                    $billsCreated++;

                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error("Error creating bed bill for admission {$admission->id}: " . $e->getMessage());
                }
            }

            // Mark as processed for today (cache expires at midnight tomorrow)
            Cache::put($cacheKey, true, Carbon::tomorrow());

            if ($billsCreated > 0) {
                Log::info("Bed billing: Created {$billsCreated} daily bed bills");
            }

        } catch (\Exception $e) {
            Log::error("Fatal error in processDailyBedBills: " . $e->getMessage());
        }
    }

    /**
     * Sync HMO executives to a messenger group for notifications
     * Uses cache to ensure it only runs once per hour
     */
    protected function syncHmoExecutivesGroup()
    {
        try {
            // Check if already synced within the last hour
            $cacheKey = 'hmo_executives_sync_' . Carbon::now()->format('Y-m-d-H');

            if (Cache::has($cacheKey)) {
                return; // Already synced this hour
            }

            // Find or create the HMO Executives group conversation
            $conversation = ChatConversation::firstOrCreate(
                ['title' => 'HMO Executives'],
                [
                    'is_group' => true,
                ]
            );

            // Get all HMO executives (users with HMO Executive role)
            $hmoExecutives = User::whereHas('roles', function($query) {
                $query->where('name', 'HMO Executive');
            })->pluck('id');

            // Also add SUPERADMIN and ADMIN for oversight
            $admins = User::whereHas('roles', function($query) {
                $query->whereIn('name', ['SUPERADMIN', 'ADMIN']);
            })->pluck('id');

            // Combine all user IDs
            $allUserIds = $hmoExecutives->merge($admins)->unique();

            if ($allUserIds->isEmpty()) {
                Cache::put($cacheKey, true, 3600); // Cache for 1 hour
                return;
            }

            // Get existing participant IDs
            $existingParticipants = ChatParticipant::where('conversation_id', $conversation->id)
                ->pluck('user_id');

            // Find users who need to be added
            $usersToAdd = $allUserIds->diff($existingParticipants);

            $addedCount = 0;
            foreach ($usersToAdd as $userId) {
                try {
                    ChatParticipant::create([
                        'conversation_id' => $conversation->id,
                        'user_id' => $userId,
                        'joined_at' => now()
                    ]);
                    $addedCount++;
                } catch (\Exception $e) {
                    // Skip if participant already exists (race condition)
                    continue;
                }
            }

            // Cache the sync status for 1 hour
            Cache::put($cacheKey, true, 3600);

            if ($addedCount > 0) {
                Log::info("HMO Executives Group: Added {$addedCount} new participants");
            }

        } catch (\Exception $e) {
            Log::error("Error syncing HMO executives group: " . $e->getMessage());
        }
    }
}
