<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Product;
use App\Models\service;
use App\Models\ServicePrice;
use App\Models\Price;
use App\Models\Hmo;
use App\Models\AdmissionRequest;
use App\Models\ProductOrServiceRequest;
use App\Models\ChatConversation;
use App\Models\ChatParticipant;
use App\Models\User;
use App\Models\payment;
use App\Models\Expense;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderPayment;
use App\Models\HmoRemittance;
use App\Models\HR\PayrollBatch;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\CreditNote;
use App\Models\Accounting\JournalEntryEdit;
use App\Observers\ProductObserver;
use App\Observers\ServiceObserver;
use App\Observers\ServicePriceObserver;
use App\Observers\PriceObserver;
use App\Observers\HmoObserver;
use App\Observers\Accounting\JournalEntryObserver;
use App\Observers\Accounting\CreditNoteObserver;
use App\Observers\Accounting\JournalEntryEditObserver;
use App\Observers\Accounting\PaymentObserver;
use App\Observers\Accounting\ExpenseObserver;
use App\Observers\Accounting\PurchaseOrderObserver;
use App\Observers\Accounting\PayrollBatchObserver;
use App\Observers\Accounting\ProductOrServiceRequestObserver;
use App\Observers\Accounting\HmoRemittanceObserver;
use App\Observers\Accounting\PurchaseOrderPaymentObserver;
use App\Helpers\HmoHelper;
use App\Services\DepartmentNotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Relations\Relation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Register the DepartmentNotificationService as a singleton
        $this->app->singleton(DepartmentNotificationService::class, function ($app) {
            return new DepartmentNotificationService();
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Configure polymorphic morph map for expenses and other models
        Relation::morphMap([
            'payroll_batch' => \App\Models\HR\PayrollBatch::class,
            'purchase_order' => \App\Models\PurchaseOrder::class,
        ]);

        // Register HMO tariff auto-generation observers
        Product::observe(ProductObserver::class);
        service::observe(ServiceObserver::class);
        ServicePrice::observe(ServicePriceObserver::class);
        Price::observe(PriceObserver::class);
        Hmo::observe(HmoObserver::class);

        // Register Accounting observers for automated journal entries and notifications
        JournalEntry::observe(JournalEntryObserver::class);
        CreditNote::observe(CreditNoteObserver::class);
        JournalEntryEdit::observe(JournalEntryEditObserver::class);
        payment::observe(PaymentObserver::class);
        Expense::observe(ExpenseObserver::class);
        PurchaseOrder::observe(PurchaseOrderObserver::class);
        PayrollBatch::observe(PayrollBatchObserver::class);

        // NEW: Revenue and AR observers for HMO billing
        ProductOrServiceRequest::observe(ProductOrServiceRequestObserver::class);
        HmoRemittance::observe(HmoRemittanceObserver::class);
        PurchaseOrderPayment::observe(PurchaseOrderPaymentObserver::class);

        // Process daily bed bills - runs once per day automatically
        $this->processDailyBedBills();

        // Run department notification checks - runs once per hour
        $this->runDepartmentNotificationChecks();
    }

    /**
     * Run department notification checks
     * Runs on every request but uses caching to prevent duplicate notifications
     */
    protected function runDepartmentNotificationChecks()
    {
        try {
            $notificationService = app(DepartmentNotificationService::class);
            $notificationService->runChecks();
        } catch (\Exception $e) {
            Log::error("Error running department notification checks: " . $e->getMessage());
        }
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
}
