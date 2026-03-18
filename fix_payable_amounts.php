<?php
/**
 * Fix payable_amount for:
 * 1) ProductOrServiceRequest records where claims_amount = 0 (cash patients)
 *    Sets payable_amount = product->price->current_sale_price * qty
 *
 * 2) HmoTariff entries where claims_amount = 0
 *    Sets payable_amount = product->price->current_sale_price or service->price->sale_price
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ProductOrServiceRequest;
use App\Models\HmoTariff;

// =============================================
// PART 1: Fix ProductOrServiceRequest records
// =============================================
echo "=== PART 1: ProductOrServiceRequest (claims_amount=0, payable_amount mismatch) ===\n\n";

$query = ProductOrServiceRequest::where('type', 'product')
    ->whereNotNull('product_id')
    ->where('claims_amount', 0);

$total = $query->count();
echo "Found {$total} product requests with claims_amount=0 to check\n\n";

$updated = 0;
$skipped = 0;
$correct = 0;

if ($total > 0) {
    $query->with('product.price')->chunk(100, function ($records) use (&$updated, &$skipped, &$correct) {
        foreach ($records as $posr) {
            $salePrice = optional(optional($posr->product)->price)->current_sale_price;

            if (!$salePrice || $salePrice <= 0) {
                echo "  SKIP ID {$posr->id} - product #{$posr->product_id}: no valid sale price\n";
                $skipped++;
                continue;
            }

            $expectedPayable = round($salePrice * $posr->qty, 2);

            if (round((float) $posr->payable_amount, 2) === $expectedPayable) {
                $correct++;
                continue;
            }

            echo "  FIX ID {$posr->id} - product #{$posr->product_id}: "
               . "qty={$posr->qty} x price={$salePrice} = {$expectedPayable} "
               . "(was {$posr->payable_amount})\n";

            $posr->payable_amount = $expectedPayable;
            $posr->save();
            $updated++;
        }
    });
}

echo "\n--- POSR Summary ---\n";
echo "Updated: {$updated}\n";
echo "Correct: {$correct}\n";
echo "Skipped: {$skipped}\n\n";

// =============================================
// PART 2: Fix HmoTariff entries
// =============================================
echo "=== PART 2: HmoTariff entries (claims_amount=0, payable_amount mismatch) ===\n\n";

$tariffQuery = HmoTariff::where('claims_amount', 0);

$tariffTotal = $tariffQuery->count();
echo "Found {$tariffTotal} tariff entries with claims_amount=0 to check\n\n";

$tUpdated = 0;
$tSkipped = 0;
$tCorrect = 0;

if ($tariffTotal > 0) {
    $tariffQuery->with(['product.price', 'service.price'])->chunk(100, function ($tariffs) use (&$tUpdated, &$tSkipped, &$tCorrect) {
        foreach ($tariffs as $tariff) {
            $price = null;
            $label = '';

            if ($tariff->product_id) {
                $price = optional(optional($tariff->product)->price)->current_sale_price;
                $label = "product #{$tariff->product_id}";
            } elseif ($tariff->service_id) {
                $price = optional(optional($tariff->service)->price)->sale_price;
                $label = "service #{$tariff->service_id}";
            }

            if (!$price || $price <= 0) {
                echo "  SKIP Tariff #{$tariff->id} - {$label} (HMO #{$tariff->hmo_id}): no valid price\n";
                $tSkipped++;
                continue;
            }

            $expectedPayable = round((float) $price, 2);

            if (round((float) $tariff->payable_amount, 2) === $expectedPayable) {
                $tCorrect++;
                continue;
            }

            echo "  FIX Tariff #{$tariff->id} - {$label} (HMO #{$tariff->hmo_id}): "
               . "payable_amount={$expectedPayable} (was {$tariff->payable_amount})\n";

            HmoTariff::where('id', $tariff->id)->update(['payable_amount' => $expectedPayable]);
            $tUpdated++;
        }
    });
}

echo "\n--- Tariff Summary ---\n";
echo "Updated: {$tUpdated}\n";
echo "Correct: {$tCorrect}\n";
echo "Skipped: {$tSkipped}\n\n";

echo "=== DONE ===\n";
echo "POSR: {$updated} updated, {$correct} correct, {$skipped} skipped\n";
echo "Tariffs: {$tUpdated} updated, {$tCorrect} correct, {$tSkipped} skipped\n";
