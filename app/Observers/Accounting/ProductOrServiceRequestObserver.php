<?php

namespace App\Observers\Accounting;

use App\Models\ProductOrServiceRequest;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountSubAccount;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\SubAccountService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * ProductOrServiceRequest Observer
 *
 * Reference: BANK_CASH_STATEMENT_IMPLEMENTATION.md - Part 7.2.1
 *
 * ACCRUAL ACCOUNTING: Creates journal entry when HMO claims are validated.
 * This recognizes revenue and creates AR-HMO that will be offset when
 * HMO remits payment.
 *
 * On Validation Approved (HMO patient only):
 * DEBIT:  Accounts Receivable - HMO (1110) + HMO Sub-Account
 * CREDIT: Revenue by service type (4xxx)
 *
 * On Validation Rejected (reverses any previous approved entry):
 * Calls AccountingService::reverseEntry() to create a reversal JE
 *
 * METADATA CAPTURED:
 * - product_id, service_id (the item/service rendered)
 * - product_category_id, service_category_id (for category filtering)
 * - hmo_id (for HMO-specific reports)
 * - patient_id (for patient transaction history)
 * - category (lab, pharmacy, imaging, etc.)
 */
class ProductOrServiceRequestObserver
{
    protected SubAccountService $subAccountService;

    public function __construct(SubAccountService $subAccountService)
    {
        $this->subAccountService = $subAccountService;
    }

    /**
     * Handle the ProductOrServiceRequest "updated" event.
     */
    public function updated(ProductOrServiceRequest $request): void
    {
        // Only process HMO patients
        if (!$request->hmo_id) {
            return;
        }

        // When validation_status changes to approved - create revenue entry
        if ($request->isDirty('validation_status') && $request->validation_status === 'approved') {
            try {
                $this->createHmoRevenueEntry($request);
            } catch (\Exception $e) {
                Log::error('ProductOrServiceRequestObserver: Failed to create HMO revenue journal entry', [
                    'request_id' => $request->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        // When validation_status changes to rejected - reverse any existing entry
        if ($request->isDirty('validation_status') && $request->validation_status === 'rejected') {
            try {
                $this->reverseHmoRevenueEntry($request);
            } catch (\Exception $e) {
                Log::error('ProductOrServiceRequestObserver: Failed to reverse HMO revenue journal entry', [
                    'request_id' => $request->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
    }

    /**
     * Reverse any existing HMO revenue journal entry when claim is rejected.
     *
     * This handles the case where an HMO approves a claim, then later rejects it.
     * The reversal entry ensures the GL is corrected:
     * - AR-HMO is credited back (reducing receivable)
     * - Revenue is debited back (reducing recognized revenue)
     */
    protected function reverseHmoRevenueEntry(ProductOrServiceRequest $request): void
    {
        // Find existing journal entries for this request
        $journalEntries = JournalEntry::where('source_type', ProductOrServiceRequest::class)
            ->where('source_id', $request->id)
            ->whereIn('status', [JournalEntry::STATUS_POSTED, JournalEntry::STATUS_APPROVED])
            ->get();

        if ($journalEntries->isEmpty()) {
            Log::info('ProductOrServiceRequestObserver: No journal entries to reverse for rejected claim', [
                'request_id' => $request->id
            ]);
            return;
        }

        $accountingService = App::make(AccountingService::class);

        foreach ($journalEntries as $journalEntry) {
            // Skip if already reversed
            if (!$journalEntry->canReverse()) {
                Log::info('ProductOrServiceRequestObserver: Journal entry cannot be reversed', [
                    'request_id' => $request->id,
                    'journal_entry_id' => $journalEntry->id,
                    'status' => $journalEntry->status
                ]);
                continue;
            }

            // Reverse the entry
            $reversalEntry = $accountingService->reverseEntry(
                $journalEntry,
                auth()->id() ?? 1,
                "HMO Claim Rejected - Reversal | Request #{$request->id} | HMO: {$request->hmo?->name}"
            );

            Log::info('ProductOrServiceRequestObserver: Journal entry reversed for rejected HMO claim', [
                'request_id' => $request->id,
                'original_je_id' => $journalEntry->id,
                'reversal_je_id' => $reversalEntry->id,
                'amount' => $request->claims_amount,
                'hmo_id' => $request->hmo_id
            ]);
        }
    }

    /**
     * Create the HMO revenue recognition journal entry.
     */
    protected function createHmoRevenueEntry(ProductOrServiceRequest $request): void
    {
        // Only create entry if there's a claims_amount
        if (!$request->claims_amount || $request->claims_amount <= 0) {
            Log::info('ProductOrServiceRequestObserver: Skipped - no claims amount', [
                'request_id' => $request->id
            ]);
            return;
        }

        $accountingService = App::make(AccountingService::class);

        $arHmo = Account::where('code', '1110')->first(); // AR - HMO
        $revenueAccount = Account::where('code', $this->getRevenueCode($request))->first();

        if (!$arHmo || !$revenueAccount) {
            Log::warning('ProductOrServiceRequestObserver: Skipped - accounts not configured', [
                'request_id' => $request->id,
                'ar_hmo_found' => !is_null($arHmo),
                'revenue_found' => !is_null($revenueAccount),
            ]);
            return;
        }

        // Get or create HMO sub-account for AR tracking
        $hmoSubAccount = $this->subAccountService->getOrCreateHmoSubAccount($request->hmo);

        // Get category for filtering
        $category = $this->getCategory($request);

        // Get category IDs
        $productCategoryId = $request->product?->product_category_id ?? $request->product?->category_id ?? null;
        $serviceCategoryId = $request->service?->service_category_id ?? $request->service?->category_id ?? null;

        $description = $this->buildDescription($request);

        // Build lines WITH METADATA for granular filtering
        $lines = [
            [
                'account_id' => $arHmo->id,
                'sub_account_id' => $hmoSubAccount?->id,
                'debit_amount' => $request->claims_amount,
                'credit_amount' => 0,
                'description' => "HMO Claim: {$request->hmo->name} - Patient: {$request->patient?->fullname}",
                // METADATA for drill-down
                'product_id' => $request->product_id,
                'service_id' => $request->service_id,
                'product_category_id' => $productCategoryId,
                'service_category_id' => $serviceCategoryId,
                'hmo_id' => $request->hmo_id,
                'patient_id' => $request->patient_id,
                'category' => $category,
            ],
            [
                'account_id' => $revenueAccount->id,
                'sub_account_id' => null,
                'debit_amount' => 0,
                'credit_amount' => $request->claims_amount,
                'description' => $this->getServiceDescription($request),
                // SAME METADATA on revenue line
                'product_id' => $request->product_id,
                'service_id' => $request->service_id,
                'product_category_id' => $productCategoryId,
                'service_category_id' => $serviceCategoryId,
                'hmo_id' => $request->hmo_id,
                'patient_id' => $request->patient_id,
                'category' => $category,
            ]
        ];

        $accountingService->createAndPostAutomatedEntry(
            ProductOrServiceRequest::class,
            $request->id,
            $description,
            $lines
        );

        Log::info('ProductOrServiceRequestObserver: Journal entry created', [
            'request_id' => $request->id,
            'hmo_id' => $request->hmo_id,
            'amount' => $request->claims_amount,
        ]);
    }

    /**
     * Get category for filtering (lab, pharmacy, imaging, etc.)
     */
    protected function getCategory(ProductOrServiceRequest $request): string
    {
        if ($request->product_id) {
            return 'pharmacy';
        }

        // Try to get category from service or type field
        $type = $request->type ?? $request->service?->category ?? $request->service?->service_category?->name ?? 'general';

        return match (strtolower($type)) {
            'pharmacy', 'drug', 'drugs', 'medication' => 'pharmacy',
            'lab', 'laboratory', 'investigation' => 'lab',
            'imaging', 'radiology', 'xray', 'scan' => 'imaging',
            'consultation', 'consult' => 'consultation',
            'procedure', 'surgery' => 'procedure',
            'admission', 'ward', 'bed' => 'admission',
            default => 'general'
        };
    }

    /**
     * Get revenue account code based on request type.
     */
    protected function getRevenueCode(ProductOrServiceRequest $request): string
    {
        if ($request->product_id) {
            return '4020'; // Pharmacy Revenue
        }

        // Map service type to revenue account
        $type = $request->type ?? $request->service?->category ?? $request->service?->service_category?->name ?? 'general';

        return match (strtolower($type)) {
            'pharmacy', 'drug', 'drugs', 'medication' => '4020',
            'lab', 'laboratory', 'investigation' => '4030',
            'imaging', 'radiology', 'xray', 'scan' => '4040',
            'consultation', 'consult' => '4010',
            'procedure', 'surgery' => '4050',
            'admission', 'ward', 'bed' => '4060',
            default => '4000' // General Revenue
        };
    }

    /**
     * Build description for journal entry.
     */
    protected function buildDescription(ProductOrServiceRequest $request): string
    {
        $parts = [
            "HMO Claim Validated",
            "HMO: " . ($request->hmo?->name ?? 'Unknown'),
            "Patient: " . ($request->patient?->fullname ?? 'Unknown'),
            "Amount: " . number_format($request->claims_amount, 2),
        ];

        if ($request->auth_code) {
            $parts[] = "Auth Code: {$request->auth_code}";
        }

        return implode(' | ', $parts);
    }

    /**
     * Get service description for revenue line.
     */
    protected function getServiceDescription(ProductOrServiceRequest $request): string
    {
        if ($request->product) {
            return "Pharmacy: {$request->product->name} x " . ($request->qty ?? 1);
        }
        if ($request->service) {
            return "Service: " . ($request->service->service_name ?? $request->service->name ?? 'Unknown');
        }
        return "Service rendered";
    }
}
