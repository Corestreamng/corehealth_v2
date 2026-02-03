<?php

namespace App\Services\Accounting;

use App\Models\Accounting\FixedAsset;
use App\Models\Accounting\FixedAssetCategory;
use App\Models\Accounting\FixedAssetDepreciation;
use App\Models\Accounting\FixedAssetDisposal;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingPeriod;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Carbon\Carbon;

/**
 * Fixed Asset Service
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 4.1B, 6.6
 * Reference: ACCOUNTING_IMPLEMENTATION_CHECKLIST.md - Phase 5
 *
 * Manages fixed assets, depreciation, and disposals with JE-centric tracking.
 */
class FixedAssetService
{
    /**
     * Create a fixed asset from manual entry.
     */
    public function createAsset(array $data): FixedAsset
    {
        DB::beginTransaction();
        try {
            $category = FixedAssetCategory::findOrFail($data['category_id']);

            // Generate asset number if not provided
            if (empty($data['asset_number'])) {
                $data['asset_number'] = FixedAsset::generateAssetNumber($category->code);
            }

            // Calculate costs
            $acquisitionCost = (float) $data['acquisition_cost'];
            $additionalCosts = (float) ($data['additional_costs'] ?? 0);
            $totalCost = $acquisitionCost + $additionalCosts;

            // Calculate salvage value if not provided
            $salvageValue = $data['salvage_value'] ?? $category->calculateDefaultSalvageValue($totalCost);

            // Calculate depreciable amount
            $depreciableAmount = $totalCost - $salvageValue;

            // Set depreciation settings from category if not provided
            $usefulLifeYears = $data['useful_life_years'] ?? $category->default_useful_life_years;
            $depreciationMethod = $data['depreciation_method'] ?? $category->default_depreciation_method;

            // Create asset
            $asset = FixedAsset::create([
                'asset_number' => $data['asset_number'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'category_id' => $category->id,
                'account_id' => $category->asset_account_id,
                'source_type' => $data['source_type'] ?? 'manual',
                'source_id' => $data['source_id'] ?? null,
                'acquisition_cost' => $acquisitionCost,
                'additional_costs' => $additionalCosts,
                'total_cost' => $totalCost,
                'salvage_value' => $salvageValue,
                'depreciable_amount' => $depreciableAmount,
                'accumulated_depreciation' => 0,
                'book_value' => $totalCost,
                'depreciation_method' => $depreciationMethod,
                'useful_life_years' => $usefulLifeYears,
                'useful_life_months' => $usefulLifeYears * 12,
                'monthly_depreciation' => $category->is_depreciable
                    ? round($depreciableAmount / ($usefulLifeYears * 12), 2)
                    : 0,
                'acquisition_date' => $data['acquisition_date'],
                'in_service_date' => $data['in_service_date'] ?? $data['acquisition_date'],
                'serial_number' => $data['serial_number'] ?? null,
                'model_number' => $data['model_number'] ?? null,
                'manufacturer' => $data['manufacturer'] ?? null,
                'location' => $data['location'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'custodian_user_id' => $data['custodian_user_id'] ?? null,
                'warranty_expiry_date' => $data['warranty_expiry_date'] ?? null,
                'warranty_provider' => $data['warranty_provider'] ?? null,
                'insurance_policy_number' => $data['insurance_policy_number'] ?? null,
                'insurance_expiry_date' => $data['insurance_expiry_date'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'invoice_number' => $data['invoice_number'] ?? null,
                'status' => FixedAsset::STATUS_ACTIVE,
                'notes' => $data['notes'] ?? null,
            ]);

            // Observer will create acquisition journal entry automatically

            DB::commit();

            Log::info('FixedAssetService: Asset created', [
                'asset_id' => $asset->id,
                'asset_number' => $asset->asset_number,
                'total_cost' => $asset->total_cost,
            ]);

            return $asset->fresh(['category', 'journalEntry', 'department']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('FixedAssetService: Failed to create asset', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * Create asset from purchase order item.
     */
    public function createAssetFromPO(PurchaseOrderItem $item, PurchaseOrder $po): FixedAsset
    {
        if ($item->item_type !== 'fixed_asset') {
            throw new \InvalidArgumentException('Item is not a fixed asset');
        }

        $category = $item->fixedAssetCategory;
        if (!$category) {
            throw new \InvalidArgumentException('Fixed asset category not set on PO item');
        }

        return $this->createAsset([
            'category_id' => $category->id,
            'name' => $item->asset_name ?? $item->product->name ?? 'Asset from PO',
            'description' => $item->product->description ?? null,
            'source_type' => PurchaseOrder::class,
            'source_id' => $po->id,
            'acquisition_cost' => $item->total_price,
            'acquisition_date' => $po->received_at ?? $po->updated_at,
            'serial_number' => $item->asset_serial_number,
            'supplier_id' => $po->supplier_id,
            'invoice_number' => $po->invoice_number ?? $po->po_number,
        ]);

        // Observer will create acquisition journal entry automatically
    }

    /**
     * Run monthly depreciation for all active assets.
     */
    public function runMonthlyDepreciation(
        ?\DateTime $depreciationDate = null,
        ?int $categoryId = null,
        ?int $processedBy = null
    ): array {
        $results = [
            'processed' => 0,
            'skipped' => 0,
            'errors' => 0,
            'total_depreciation' => 0,
            'details' => [],
        ];

        // Build query
        $query = FixedAsset::depreciable()->with('category');

        // Filter by category if specified
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $assets = $query->get();

        foreach ($assets as $asset) {
            if (!$asset->needsDepreciation($depreciationDate)) {
                $results['skipped']++;
                $results['details'][] = [
                    'asset_id' => $asset->id,
                    'asset_number' => $asset->asset_number,
                    'status' => 'skipped',
                    'reason' => 'Already depreciated or not eligible',
                ];
                continue;
            }

            try {
                $depreciationAmount = $asset->calculateMonthlyDepreciation();

                if ($depreciationAmount <= 0) {
                    $results['skipped']++;
                    $results['details'][] = [
                        'asset_id' => $asset->id,
                        'asset_number' => $asset->asset_number,
                        'status' => 'skipped',
                        'reason' => 'Zero depreciation amount',
                    ];
                    continue;
                }

                // Record depreciation with optional date (observer creates JE)
                $depreciation = $asset->recordDepreciation(
                    $depreciationAmount,
                    $processedBy,
                    $depreciationDate
                );

                $results['processed']++;
                $results['total_depreciation'] += $depreciationAmount;
                $results['details'][] = [
                    'asset_id' => $asset->id,
                    'asset_number' => $asset->asset_number,
                    'status' => 'processed',
                    'amount' => $depreciationAmount,
                    'new_book_value' => $asset->book_value,
                    'depreciation_id' => $depreciation->id,
                    'journal_entry_id' => $depreciation->journal_entry_id,
                ];

            } catch (\Exception $e) {
                $results['errors']++;
                $results['details'][] = [
                    'asset_id' => $asset->id,
                    'asset_number' => $asset->asset_number,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
                Log::error('FixedAssetService: Depreciation error', [
                    'asset_id' => $asset->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('FixedAssetService: Monthly depreciation complete', $results);

        return $results;
    }

    /**
     * Dispose of a fixed asset.
     */
    public function disposeAsset(
        FixedAsset $asset,
        array $disposalData
    ): FixedAssetDisposal {
        if (!$asset->canBeDisposed()) {
            throw new \InvalidArgumentException('Asset cannot be disposed');
        }

        DB::beginTransaction();
        try {
            // Calculate gain/loss
            $proceeds = (float) ($disposalData['disposal_proceeds'] ?? 0);
            $costs = (float) ($disposalData['disposal_costs'] ?? 0);
            $gainLoss = ($proceeds - $costs) - $asset->book_value;

            $disposal = FixedAssetDisposal::create([
                'fixed_asset_id' => $asset->id,
                'disposal_date' => $disposalData['disposal_date'],
                'disposal_type' => $disposalData['disposal_type'],
                'disposal_proceeds' => $proceeds,
                'book_value_at_disposal' => $asset->book_value,
                'gain_loss_on_disposal' => $gainLoss,
                'disposal_costs' => $costs,
                'buyer_name' => $disposalData['buyer_name'] ?? null,
                'invoice_number' => $disposalData['invoice_number'] ?? null,
                'reason' => $disposalData['reason'] ?? null,
                'payment_method' => $disposalData['payment_method'] ?? null,
                'bank_id' => $disposalData['bank_id'] ?? null,
                'status' => FixedAssetDisposal::STATUS_COMPLETED, // Auto-complete the disposal
            ]);

            // Update asset status to disposed
            $asset->status = FixedAsset::STATUS_DISPOSED;
            $asset->save();

            DB::commit();

            Log::info('FixedAssetService: Disposal created and asset disposed', [
                'asset_id' => $asset->id,
                'disposal_id' => $disposal->id,
                'gain_loss' => $gainLoss,
            ]);

            return $disposal;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('FixedAssetService: Disposal failed', [
                'asset_id' => $asset->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Complete a disposal (after approval).
     * This triggers the observer to create JE.
     */
    public function completeDisposal(FixedAssetDisposal $disposal): FixedAssetDisposal
    {
        if (!$disposal->isApproved()) {
            throw new \InvalidArgumentException('Disposal must be approved first');
        }

        $disposal->status = FixedAssetDisposal::STATUS_COMPLETED;
        $disposal->save();  // Observer creates JE

        return $disposal->fresh(['journalEntry', 'fixedAsset']);
    }

    /**
     * Get fixed assets register.
     */
    public function getAssetsRegister(array $filters = []): Collection
    {
        $query = FixedAsset::with(['category', 'department', 'custodian']);

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['from_date'])) {
            $query->where('acquisition_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->where('acquisition_date', '<=', $filters['to_date']);
        }

        return $query->orderBy('asset_number')->get();
    }

    /**
     * Get depreciation schedule for an asset.
     */
    public function getDepreciationSchedule(FixedAsset $asset): array
    {
        $schedule = [];
        $currentBookValue = $asset->total_cost;
        $accumulatedDepreciation = 0;
        $monthlyDepreciation = $asset->monthly_depreciation;
        $totalMonths = $asset->useful_life_months ?? ($asset->useful_life_years * 12);

        for ($month = 1; $month <= $totalMonths; $month++) {
            $year = ceil($month / 12);
            $monthInYear = (($month - 1) % 12) + 1;

            // Don't depreciate below salvage value
            $depreciationThisMonth = min($monthlyDepreciation, $currentBookValue - $asset->salvage_value);
            $depreciationThisMonth = max(0, $depreciationThisMonth);

            $accumulatedDepreciation += $depreciationThisMonth;
            $currentBookValue -= $depreciationThisMonth;

            $schedule[] = [
                'month' => $month,
                'year' => $year,
                'month_in_year' => $monthInYear,
                'depreciation_amount' => round($depreciationThisMonth, 2),
                'accumulated_depreciation' => round($accumulatedDepreciation, 2),
                'book_value' => round($currentBookValue, 2),
            ];
        }

        return $schedule;
    }

    /**
     * Get summary by category.
     */
    public function getSummaryByCategory(): Collection
    {
        return FixedAssetCategory::with(['fixedAssets' => function ($q) {
            $q->whereNotIn('status', [FixedAsset::STATUS_DISPOSED]);
        }])->get()->map(function ($category) {
            $assets = $category->fixedAssets;
            return [
                'category' => $category,
                'asset_count' => $assets->count(),
                'total_cost' => $assets->sum('total_cost'),
                'total_accumulated_depreciation' => $assets->sum('accumulated_depreciation'),
                'total_book_value' => $assets->sum('book_value'),
            ];
        });
    }

    /**
     * Get assets requiring maintenance.
     */
    public function getMaintenanceDueAssets(): Collection
    {
        return FixedAsset::maintenanceDue()
            ->with(['maintenanceSchedules' => function ($q) {
                $q->where('status', 'scheduled')
                  ->where('scheduled_date', '<=', now())
                  ->orderBy('scheduled_date');
            }])
            ->get();
    }

    /**
     * Get assets with expiring warranties.
     */
    public function getExpiringWarranties(int $daysAhead = 30): Collection
    {
        return FixedAsset::warrantyExpiring($daysAhead)
            ->with('category')
            ->orderBy('warranty_expiry_date')
            ->get();
    }

    /**
     * Void a fixed asset by reversing its acquisition journal entry.
     * Can only void assets with no depreciation.
     */
    public function voidAsset(FixedAsset $asset, string $reason, int $voidedBy): bool
    {
        if (!$asset->canBeVoided()) {
            throw new \Exception('Asset cannot be voided. It may have depreciation or be in an invalid status.');
        }

        DB::beginTransaction();
        try {
            // Reverse the acquisition journal entry if it exists
            if ($asset->journalEntry) {
                $originalJE = $asset->journalEntry;

                // Create reversal entry
                $reversalJE = JournalEntry::create([
                    'entry_number' => JournalEntry::generateEntryNumber(),
                    'entry_date' => now(),
                    'accounting_period_id' => AccountingPeriod::current()->id,
                    'description' => "REVERSAL: Voiding fixed asset {$asset->asset_number} - {$reason}",
                    'reference_type' => FixedAsset::class,
                    'reference_id' => $asset->id,
                    'entry_type' => JournalEntry::TYPE_REVERSAL,
                    'status' => JournalEntry::STATUS_POSTED,
                    'reversal_of_id' => $originalJE->id,
                    'created_by' => $voidedBy,
                    'posted_by' => $voidedBy,
                    'posted_at' => now(),
                ]);

                // Create reversed lines (swap debit/credit)
                foreach ($originalJE->lines as $line) {
                    JournalEntryLine::create([
                        'journal_entry_id' => $reversalJE->id,
                        'line_number' => $line->line_number,
                        'account_id' => $line->account_id,
                        'debit' => $line->credit,  // Swap: original credit becomes debit
                        'credit' => $line->debit,  // Swap: original debit becomes credit
                        'narration' => "Reversal: " . $line->narration,
                    ]);
                }

                // Mark original entry as reversed
                $originalJE->status = JournalEntry::STATUS_REVERSED;
                $originalJE->reversed_by_id = $reversalJE->id;
                $originalJE->save();
            }

            // Update asset status
            $asset->status = FixedAsset::STATUS_VOIDED;
            $asset->notes = ($asset->notes ? $asset->notes . "\n\n" : '') .
                           "VOIDED: " . now()->format('Y-m-d H:i:s') . " - {$reason}";
            $asset->save();

            DB::commit();

            Log::info('FixedAssetService: Asset voided', [
                'asset_id' => $asset->id,
                'asset_number' => $asset->asset_number,
                'voided_by' => $voidedBy,
                'reason' => $reason,
            ]);

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('FixedAssetService: Failed to void asset', [
                'asset_id' => $asset->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
