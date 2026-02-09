<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ProductRequest;
use App\Models\ProductOrServiceRequest;
use Illuminate\Support\Facades\DB;

echo "===========================================\n";
echo "Checking for Quantity/Billing Mismatches\n";
echo "===========================================\n\n";

// Find all product requests that:
// 1. Have been billed (status >= 2)
// 2. Have a productOrServiceRequest
// 3. Qty doesn't match between ProductRequest and ProductOrServiceRequest
$mismatches = ProductRequest::with(['product', 'productOrServiceRequest', 'patient.user'])
    ->where('status', '>=', 2)
    ->whereHas('productOrServiceRequest')
    ->get()
    ->filter(function($pr) {
        $posr = $pr->productOrServiceRequest;
        return $posr && $pr->qty != $posr->qty;
    });

if ($mismatches->isEmpty()) {
    echo "✓ No mismatches found! All billing records match their product request quantities.\n";
    exit(0);
}

echo "Found " . $mismatches->count() . " mismatch(es):\n\n";

$issues = [];

foreach ($mismatches as $pr) {
    $posr = $pr->productOrServiceRequest;
    $product = $pr->product;
    $patient = $pr->patient;
    
    $patientName = $patient && $patient->user ? userfullname($patient->user_id) : 'Unknown';
    $productName = $product ? $product->product_name : 'Unknown Product';
    
    $issue = [
        'product_request_id' => $pr->id,
        'posr_id' => $posr->id,
        'patient_name' => $patientName,
        'product_name' => $productName,
        'product_request_qty' => $pr->qty,
        'posr_qty' => $posr->qty,
        'was_adjusted' => $pr->qty_adjusted_from !== null,
        'adjusted_from' => $pr->qty_adjusted_from,
        'adjustment_reason' => $pr->qty_adjustment_reason,
        'unit_price' => $product && $product->price ? $product->price->current_sale_price : 0,
        'current_posr_payable' => $posr->payable_amount,
        'current_posr_claims' => $posr->claims_amount,
        'expected_total' => ($product && $product->price ? $product->price->current_sale_price : 0) * $pr->qty,
        'created_at' => $pr->created_at->format('Y-m-d H:i:s'),
    ];
    
    $issues[] = $issue;
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Issue #{$pr->id}\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Patient: {$patientName}\n";
    echo "Product: {$productName}\n";
    echo "Created: {$issue['created_at']}\n";
    echo "\n";
    echo "Product Request (Pharmacy Record):\n";
    echo "  - Qty: {$pr->qty}\n";
    if ($pr->qty_adjusted_from !== null) {
        echo "  - Adjusted from: {$pr->qty_adjusted_from}\n";
        echo "  - Adjustment reason: {$pr->qty_adjustment_reason}\n";
        echo "  - Adjusted at: " . ($pr->qty_adjusted_at ? $pr->qty_adjusted_at->format('Y-m-d H:i:s') : 'N/A') . "\n";
    }
    echo "\n";
    echo "ProductOrServiceRequest (Billing Record):\n";
    echo "  - Qty: {$posr->qty} ❌ MISMATCH\n";
    echo "  - Payable Amount: ₦" . number_format($posr->payable_amount, 2) . "\n";
    echo "  - Claims Amount: ₦" . number_format($posr->claims_amount, 2) . "\n";
    echo "  - Current Total: ₦" . number_format($posr->payable_amount + $posr->claims_amount, 2) . "\n";
    echo "\n";
    echo "Expected Billing (with correct qty):\n";
    echo "  - Unit Price: ₦" . number_format($issue['unit_price'], 2) . "\n";
    echo "  - Expected Total: ₦" . number_format($issue['expected_total'], 2) . "\n";
    echo "  - Difference: ₦" . number_format($issue['expected_total'] - ($posr->payable_amount + $posr->claims_amount), 2) . "\n";
    echo "\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Summary\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Total mismatches: " . $mismatches->count() . "\n";
echo "Items adjusted before billing: " . $mismatches->filter(fn($pr) => $pr->qty_adjusted_from !== null)->count() . "\n";
echo "\n";

// Ask if user wants to fix
echo "Do you want to fix these mismatches? (yes/no): ";
$handle = fopen ("php://stdin","r");
$line = trim(fgets($handle));

if(strtolower($line) !== 'yes') {
    echo "No changes made.\n";
    exit(0);
}

echo "\nFixing mismatches...\n\n";

$fixed = 0;
$failed = 0;

foreach ($issues as $issue) {
    try {
        DB::beginTransaction();
        
        $pr = ProductRequest::with(['product.price', 'productOrServiceRequest'])->find($issue['product_request_id']);
        $posr = $pr->productOrServiceRequest;
        
        if (!$posr) {
            echo "❌ ProductRequest #{$pr->id}: No billing record found\n";
            $failed++;
            DB::rollBack();
            continue;
        }
        
        $unitPrice = $pr->product && $pr->product->price ? $pr->product->price->current_sale_price : 0;
        $correctQty = $pr->qty;
        
        // Calculate new total
        $newTotal = $unitPrice * $correctQty;
        
        // Maintain coverage ratio if HMO
        $oldTotal = $posr->payable_amount + $posr->claims_amount;
        $newPayableAmount = $newTotal;
        $newClaimsAmount = 0;
        
        if ($oldTotal > 0 && $posr->claims_amount > 0) {
            $payableRatio = $posr->payable_amount / $oldTotal;
            $claimsRatio = $posr->claims_amount / $oldTotal;
            
            $newPayableAmount = $newTotal * $payableRatio;
            $newClaimsAmount = $newTotal * $claimsRatio;
        }
        
        $posr->update([
            'qty' => $correctQty,
            'payable_amount' => $newPayableAmount,
            'claims_amount' => $newClaimsAmount,
        ]);
        
        DB::commit();
        
        echo "✓ Fixed ProductRequest #{$pr->id}: Updated billing qty from {$issue['posr_qty']} to {$correctQty}, ";
        echo "amount from ₦" . number_format($oldTotal, 2) . " to ₦" . number_format($newTotal, 2) . "\n";
        
        $fixed++;
        
    } catch (\Exception $e) {
        DB::rollBack();
        echo "❌ Failed to fix ProductRequest #{$issue['product_request_id']}: " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Fix Summary\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Fixed: $fixed\n";
echo "Failed: $failed\n";
echo "\n";
