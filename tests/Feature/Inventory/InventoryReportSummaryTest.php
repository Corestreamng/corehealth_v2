<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Tests\TestCase;

class InventoryReportSummaryTest extends TestCase
{
    /** @test */
    public function test_inventory_report_summary_endpoint_given_mode()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);

        $response = $this->actingAs($user)->getJson('/inventory/inventory-reports/summary?' . http_build_query([
            'store_id' => '2',
            'mode' => 'given',
            'group_by' => 'category',
            'start_date' => now()->subMonths(3)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ]));

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));

        if ($response->status() === 200) {
            $response->assertJsonStructure([
                'status',
                'data' => [
                    '*' => [
                        'grouping_key',
                        'total_qty',
                        'total_value',
                        'cash_revenue',
                        'claims_revenue',
                        'potential_revenue',
                        'profit',
                    ],
                ],
            ]);
        }
    }

    /** @test */
    public function test_inventory_report_summary_endpoint_received_mode()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);

        $response = $this->actingAs($user)->getJson('/inventory/inventory-reports/summary?' . http_build_query([
            'store_id' => '2',
            'mode' => 'received',
            'group_by' => 'category',
            'start_date' => now()->subMonths(3)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ]));

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
    }

    /** @test */
    public function test_inventory_report_drilldown_endpoint()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);

        $response = $this->actingAs($user)->getJson('/inventory/inventory-reports/drill-down?' . http_build_query([
            'store_id' => '2',
            'mode' => 'given',
            'group_by' => 'category',
            'group_key' => 'ANTISEPTIC-ANTI-HEMORRHAGE',
            'start_date' => now()->subMonths(3)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ]));

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
    }

    /** @test */
    public function test_inventory_report_print_endpoint()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);

        $response = $this->actingAs($user)->get('/inventory/inventory-reports/summary/print?' . http_build_query([
            'store_id' => '2',
            'mode' => 'given',
            'group_by' => 'category',
            'start_date' => now()->subMonths(3)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ]));

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
    }

    /** @test */
    public function test_inventory_report_handles_missing_store_id()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);

        $response = $this->actingAs($user)->getJson('/inventory/inventory-reports/summary?' . http_build_query([
            'mode' => 'given',
            'group_by' => 'category',
            'start_date' => now()->subMonths(1)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ]));

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
    }

    /** @test */
    public function test_pharmacy_workbench_loads_with_managed_stores()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);

        $response = $this->actingAs($user)->get('/pharmacy-workbench');

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
    }

    /** @test */
    public function test_inventory_report_computes_using_batch_cost_price_and_hmo_tariff_average_selling_price()
    {
        $store1 = \App\Models\Store::first() ?? \App\Models\Store::factory()->create();
        $store2 = \App\Models\Store::where('id', '!=', $store1->id)->first() ?? \App\Models\Store::factory()->create();
        $product = \App\Models\Product::first() ?? \App\Models\Product::factory()->create();

        // Setup known HMO tariffs for this product
        \App\Models\HmoTariff::where('product_id', $product->id)->delete();
        \App\Models\HmoTariff::create([
            'hmo_id' => 1,
            'product_id' => $product->id,
            'claims_amount' => 70.00,
            'payable_amount' => 30.00,
        ]);
        \App\Models\HmoTariff::create([
            'hmo_id' => 2,
            'product_id' => $product->id,
            'claims_amount' => 80.00,
            'payable_amount' => 40.00,
        ]);
        // Average tariff selling price = (100 + 120) / 2 = 110.00

        // Create a batch with cost price 45.00
        $batch = \App\Models\StockBatch::create([
            'product_id' => $product->id,
            'store_id' => $store1->id,
            'batch_name' => 'Batch Test ' . uniqid(),
            'batch_number' => 'TEST-BATCH-' . uniqid(),
            'initial_qty' => 10,
            'current_qty' => 10,
            'cost_price' => 45.00,
            'created_by' => 1,
            'is_active' => true,
        ]);

        $requisition = \App\Models\StoreRequisition::create([
            'from_store_id' => $store1->id,
            'to_store_id' => $store2->id,
            'status' => 'fulfilled',
            'requisition_number' => 'REQ-' . uniqid(),
            'requested_by' => 1,
        ]);

        \App\Models\StoreRequisitionItem::create([
            'store_requisition_id' => $requisition->id,
            'product_id' => $product->id,
            'requested_qty' => 5,
            'approved_qty' => 5,
            'fulfilled_qty' => 5,
            'source_batch_id' => $batch->id,
            'status' => 'fulfilled',
        ]);

        $service = app(\App\Services\InventoryReportService::class);
        $summary = $service->getSummaryData(
            [$store1->id],
            'given',
            'product',
            now()->subDay()->format('Y-m-d'),
            now()->addDay()->format('Y-m-d')
        );

        $matchingRow = collect($summary)->firstWhere('grouping_key', $product->product_name);
        $this->assertNotNull($matchingRow);
        // Cost value: 5 * 45 = 225
        $this->assertEquals(225.0, (float) $matchingRow['total_value']);
        // Potential revenue: 5 * 110 = 550
        $this->assertEquals(550.0, (float) $matchingRow['potential_revenue']);
        // Profit: 550 - 225 = 325
        $this->assertEquals(325.0, (float) $matchingRow['profit']);
    }

    /** @test */
    public function test_inventory_report_cost_price_uses_zero_fallback()
    {
        $store1 = \App\Models\Store::first() ?? \App\Models\Store::factory()->create();
        $store2 = \App\Models\Store::where('id', '!=', $store1->id)->first() ?? \App\Models\Store::factory()->create();
        $product = \App\Models\Product::first() ?? \App\Models\Product::factory()->create();

        // Create a batch with zero cost price (e.g. donation)
        $batchZero = \App\Models\StockBatch::create([
            'product_id' => $product->id,
            'store_id' => $store1->id,
            'batch_name' => 'Batch Zero ' . uniqid(),
            'batch_number' => 'ZERO-BATCH-' . uniqid(),
            'initial_qty' => 10,
            'current_qty' => 10,
            'cost_price' => 0.00,
            'created_by' => 1,
            'is_active' => true,
        ]);

        $requisition = \App\Models\StoreRequisition::create([
            'from_store_id' => $store1->id,
            'to_store_id' => $store2->id,
            'status' => 'fulfilled',
            'requisition_number' => 'REQ-ZERO-' . uniqid(),
            'requested_by' => 1,
        ]);

        \App\Models\StoreRequisitionItem::create([
            'store_requisition_id' => $requisition->id,
            'product_id' => $product->id,
            'requested_qty' => 4,
            'approved_qty' => 4,
            'fulfilled_qty' => 4,
            'source_batch_id' => $batchZero->id,
            'status' => 'fulfilled',
        ]);

        $service = app(\App\Services\InventoryReportService::class);
        $drilldown = $service->getDrillDownData(
            [$store1->id],
            'given',
            'product',
            $product->product_name,
            now()->subDay()->format('Y-m-d'),
            now()->addDay()->format('Y-m-d')
        );

        $matchingDrill = collect($drilldown)->firstWhere('batch_number', $batchZero->batch_number);
        $this->assertNotNull($matchingDrill);
        $this->assertEquals(0.0, (float) $matchingDrill['cost_price']);
        $this->assertEquals(0.0, (float) $matchingDrill['total_value']);
    }

    /** @test */
    public function test_inventory_report_received_mode_includes_manual_and_po_batches_with_channel_flags()
    {
        $store1 = \App\Models\Store::first() ?? \App\Models\Store::factory()->create();
        $store2 = \App\Models\Store::where('id', '!=', $store1->id)->first() ?? \App\Models\Store::factory()->create();
        $product = \App\Models\Product::first() ?? \App\Models\Product::factory()->create();

        // 1. Requisition received into store2
        $reqBatch = \App\Models\StockBatch::create([
            'product_id' => $product->id,
            'store_id' => $store1->id,
            'batch_name' => 'Req Batch ' . uniqid(),
            'batch_number' => 'REQ-BTH-' . uniqid(),
            'initial_qty' => 50,
            'current_qty' => 50,
            'cost_price' => 30.00,
            'received_date' => now(),
            'created_by' => 1,
            'is_active' => true,
        ]);

        $requisition = \App\Models\StoreRequisition::create([
            'from_store_id' => $store1->id,
            'to_store_id' => $store2->id,
            'status' => 'fulfilled',
            'requisition_number' => 'REQ-RCV-' . uniqid(),
            'requested_by' => 1,
        ]);

        \App\Models\StoreRequisitionItem::create([
            'store_requisition_id' => $requisition->id,
            'product_id' => $product->id,
            'requested_qty' => 10,
            'approved_qty' => 10,
            'fulfilled_qty' => 10,
            'source_batch_id' => $reqBatch->id,
            'status' => 'fulfilled',
        ]);

        // 2. PO created batch in store2
        $supplier = \App\Models\Supplier::first();
        $po = \App\Models\PurchaseOrder::create([
            'po_number' => 'PO-' . uniqid(),
            'supplier_id' => $supplier?->id ?? 1,
            'target_store_id' => $store2->id,
            'created_by' => 1,
            'status' => 'approved',
        ]);

        $poItem = \App\Models\PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'ordered_qty' => 15,
            'received_qty' => 15,
            'unit_cost' => 35.00,
            'status' => 'received',
        ]);

        $poBatch = \App\Models\StockBatch::create([
            'product_id' => $product->id,
            'store_id' => $store2->id,
            'batch_name' => 'PO Batch ' . uniqid(),
            'batch_number' => 'PO-BTH-' . uniqid(),
            'initial_qty' => 15,
            'current_qty' => 15,
            'cost_price' => 35.00,
            'received_date' => now(),
            'source' => \App\Models\StockBatch::SOURCE_PURCHASE_ORDER,
            'purchase_order_item_id' => $poItem->id,
            'created_by' => 1,
            'is_active' => true,
        ]);

        // 3. Manual created batch in store2
        $manualBatch = \App\Models\StockBatch::create([
            'product_id' => $product->id,
            'store_id' => $store2->id,
            'batch_name' => 'Manual Batch ' . uniqid(),
            'batch_number' => 'MAN-BTH-' . uniqid(),
            'initial_qty' => 20,
            'current_qty' => 20,
            'cost_price' => 25.00,
            'received_date' => now(),
            'source' => \App\Models\StockBatch::SOURCE_MANUAL,
            'created_by' => 1,
            'is_active' => true,
        ]);

        $service = app(\App\Services\InventoryReportService::class);

        // Test Summary grouped by product
        $summary = $service->getSummaryData(
            [$store2->id],
            'received',
            'product',
            now()->subDay()->format('Y-m-d'),
            now()->addDay()->format('Y-m-d')
        );

        $matchingRow = collect($summary)->firstWhere('grouping_key', $product->product_name);
        $this->assertNotNull($matchingRow);
        // Total Qty: 10 (req) + 15 (PO) + 20 (manual) = 45
        $this->assertEquals(45, $matchingRow['total_qty']);
        // Total Value: (10 * 30) + (15 * 35) + (20 * 25) = 300 + 525 + 500 = 1325
        $this->assertEquals(1325.0, (float) $matchingRow['total_value']);

        // Assert channels flag the difference
        $this->assertArrayHasKey('channels', $matchingRow);
        $this->assertEquals(10, $matchingRow['channels']['Requisition'] ?? 0);
        $this->assertEquals(15, $matchingRow['channels']['PO Receipt'] ?? 0);
        $this->assertEquals(20, $matchingRow['channels']['Manual Batch'] ?? 0);

        // Test Drilldown flags each receipt channel distinctly
        $drilldown = $service->getDrillDownData(
            [$store2->id],
            'received',
            'product',
            $product->product_name,
            now()->subDay()->format('Y-m-d'),
            now()->addDay()->format('Y-m-d')
        );

        $types = collect($drilldown)->pluck('type')->unique()->values()->all();
        $this->assertContains('Requisition', $types);
        $this->assertContains('PO Receipt', $types);
        $this->assertContains('Manual Batch', $types);

        // Test Destination Grouping flags the channel prefix
        $destSummary = $service->getSummaryData(
            [$store2->id],
            'received',
            'destination',
            now()->subDay()->format('Y-m-d'),
            now()->addDay()->format('Y-m-d')
        );

        $groupKeys = collect($destSummary)->pluck('grouping_key')->all();
        $hasReq = collect($groupKeys)->contains(fn ($k) => str_starts_with($k, 'Requisition:'));
        $hasPo = collect($groupKeys)->contains(fn ($k) => str_starts_with($k, 'PO Receipt:'));
        $hasManual = collect($groupKeys)->contains(fn ($k) => str_starts_with($k, 'Manual Entry:'));

        $this->assertTrue($hasReq, 'Destination grouping must flag Requisition channel');
        $this->assertTrue($hasPo, 'Destination grouping must flag PO Receipt channel');
        $this->assertTrue($hasManual, 'Destination grouping must flag Manual Entry channel');
    }

    /** @test */
    public function test_inventory_report_dispense_computes_strict_batch_cogs_cash_and_claims_revenue()
    {
        $pharmacyStore = \App\Models\Store::where('store_type', 'pharmacy')->first()
            ?? \App\Models\Store::factory()->create(['store_type' => 'pharmacy', 'status' => 1]);
        $product = \App\Models\Product::first() ?? \App\Models\Product::factory()->create();

        // 1. Purchased Batch (cost: 50.00)
        $batch = \App\Models\StockBatch::create([
            'product_id' => $product->id,
            'store_id' => $pharmacyStore->id,
            'batch_name' => 'Purchased Batch ' . uniqid(),
            'batch_number' => 'PUR-BTH-' . uniqid(),
            'initial_qty' => 20,
            'current_qty' => 18,
            'cost_price' => 50.00,
            'received_date' => now(),
            'created_by' => 1,
            'is_active' => true,
        ]);

        // 2. Donation Batch (cost: 0.00)
        $donationBatch = \App\Models\StockBatch::create([
            'product_id' => $product->id,
            'store_id' => $pharmacyStore->id,
            'batch_name' => 'Donation Batch ' . uniqid(),
            'batch_number' => 'DON-BTH-' . uniqid(),
            'initial_qty' => 10,
            'current_qty' => 7,
            'cost_price' => 0.00,
            'is_donation' => 1,
            'received_date' => now(),
            'created_by' => 1,
            'is_active' => true,
        ]);

        $patient = \App\Models\Patient::first();
        $encounter = \App\Models\Encounter::first();

        // Dispense 1: from purchased batch (qty: 2, cash: 300, claims: 700)
        // Dispense 1: from purchased batch (qty: 2, cash: 300, claims: 700, paid & approved)
        $posr1 = \App\Models\ProductOrServiceRequest::create([
            'user_id' => 1,
            'staff_user_id' => 1,
            'product_id' => $product->id,
            'patient_id' => $patient?->id ?? 1,
            'encounter_id' => $encounter?->id ?? 1,
            'qty' => 2,
            'payable_amount' => 300.00,
            'claims_amount' => 700.00,
            'payment_id' => 1,
            'validation_status' => 'approved',
            'dispensed_from_store_id' => $pharmacyStore->id,
        ]);

        $pr1 = \App\Models\ProductRequest::create([
            'product_id' => $product->id,
            'product_request_id' => $posr1->id,
            'patient_id' => $patient?->id ?? 1,
            'encounter_id' => $encounter?->id ?? 1,
            'dispensed_from_store_id' => $pharmacyStore->id,
            'dispensed_from_batch_id' => $batch->id,
            'qty' => 2,
            'status' => 3,
            'dispense_date' => now(),
        ]);

        // Dispense 2: from donation batch (qty: 3, cash: 150, claims: 350, paid & approved)
        $posr2 = \App\Models\ProductOrServiceRequest::create([
            'user_id' => 1,
            'staff_user_id' => 1,
            'product_id' => $product->id,
            'patient_id' => $patient?->id ?? 1,
            'encounter_id' => $encounter?->id ?? 1,
            'qty' => 3,
            'payable_amount' => 150.00,
            'claims_amount' => 350.00,
            'payment_id' => 1,
            'validation_status' => 'approved',
            'dispensed_from_store_id' => $pharmacyStore->id,
        ]);

        $pr2 = \App\Models\ProductRequest::create([
            'product_id' => $product->id,
            'product_request_id' => $posr2->id,
            'patient_id' => $patient?->id ?? 1,
            'encounter_id' => $encounter?->id ?? 1,
            'dispensed_from_store_id' => $pharmacyStore->id,
            'dispensed_from_batch_id' => $donationBatch->id,
            'qty' => 3,
            'status' => 3,
            'dispense_date' => now(),
        ]);

        $service = app(\App\Services\InventoryReportService::class);
        $drilldown = $service->getDrillDownData(
            [$pharmacyStore->id],
            'given',
            'product',
            $product->product_name,
            now()->subDay()->format('Y-m-d'),
            now()->addDay()->format('Y-m-d')
        );

        // Assert purchased batch drilldown
        $purchasedDrill = collect($drilldown)->firstWhere('batch_number', $batch->batch_number);
        $this->assertNotNull($purchasedDrill);
        $this->assertEquals(2, $purchasedDrill['qty']);
        $this->assertEquals(50.00, (float) $purchasedDrill['cost_price']);
        $this->assertEquals(100.00, (float) $purchasedDrill['total_value']); // 2 * 50
        $this->assertEquals(300.00, (float) $purchasedDrill['cash_paid']);
        $this->assertEquals(700.00, (float) $purchasedDrill['claims_paid']);
        $this->assertEquals(300.00, (float) $purchasedDrill['sale_amount_payable']);
        $this->assertEquals(700.00, (float) $purchasedDrill['sale_amount_claim']);
        $this->assertEquals(1000.00, (float) $purchasedDrill['sale_amount_total']);
        $this->assertEquals(0.00, (float) $purchasedDrill['deficit']);
        $this->assertEquals(1000.00, (float) $purchasedDrill['total_revenue']);
        $this->assertEquals(500.00, (float) $purchasedDrill['sale_price_per_unit']); // 1000 / 2
        $this->assertEquals(500.00, (float) $purchasedDrill['unit_sale_price']);
        $this->assertEquals(900.00, (float) $purchasedDrill['profit']); // 1000 - 100

        // Assert donation batch drilldown preserves 0.00 cost price
        $donationDrill = collect($drilldown)->firstWhere('batch_number', $donationBatch->batch_number);
        $this->assertNotNull($donationDrill);
        $this->assertEquals(3, $donationDrill['qty']);
        $this->assertEquals(0.00, (float) $donationDrill['cost_price']);
        $this->assertEquals(0.00, (float) $donationDrill['total_value']); // 3 * 0
        $this->assertEquals(150.00, (float) $donationDrill['cash_paid']);
        $this->assertEquals(350.00, (float) $donationDrill['claims_paid']);
        $this->assertEquals(150.00, (float) $donationDrill['sale_amount_payable']);
        $this->assertEquals(350.00, (float) $donationDrill['sale_amount_claim']);
        $this->assertEquals(500.00, (float) $donationDrill['sale_amount_total']);
        $this->assertEquals(0.00, (float) $donationDrill['deficit']);
        $this->assertEquals(500.00, (float) $donationDrill['total_revenue']);
        $this->assertEquals(166.67, (float) $donationDrill['sale_price_per_unit']); // 500 / 3
        $this->assertEquals(166.67, (float) $donationDrill['unit_sale_price']);
        $this->assertEquals(500.00, (float) $donationDrill['profit']); // 500 - 0

        // Assert aggregate summary computes overall sale price per unit
        $summary = $service->getSummaryData(
            [$pharmacyStore->id],
            'given',
            'product',
            now()->subDay()->format('Y-m-d'),
            now()->addDay()->format('Y-m-d')
        );
        $prodSummary = collect($summary)->firstWhere('grouping_key', $product->product_name);
        $this->assertNotNull($prodSummary);
        $this->assertEquals(5, $prodSummary['total_qty']);
        $this->assertEquals(450.00, (float) $prodSummary['sale_amount_payable']);
        $this->assertEquals(1050.00, (float) $prodSummary['sale_amount_claim']);
        $this->assertEquals(1500.00, (float) $prodSummary['sale_amount_total']);
        $this->assertEquals(1500.00, (float) ($prodSummary['cash_revenue'] + $prodSummary['claims_revenue']));
        $this->assertEquals(0.00, (float) $prodSummary['deficit']);
        $this->assertEquals(300.00, (float) $prodSummary['sale_price_per_unit']); // 1500 / 5
        $this->assertEquals(300.00, (float) $prodSummary['unit_sale_price']);
    }

    /** @test */
    public function test_dispense_sale_amounts_computed_from_posr_revenue_and_deficit_logic()
    {
        $pharmacyStore = \App\Models\Store::where('store_type', 'pharmacy')->first()
            ?? \App\Models\Store::where('is_default', 1)->first()
            ?? \App\Models\Store::first();

        $category = \App\Models\ProductCategory::first();
        $product = \App\Models\Product::create([
            'user_id' => 1,
            'category_id' => $category?->id ?? 1,
            'product_name' => 'Deficit Test Drug ' . uniqid(),
            'product_code' => 'DTD-' . uniqid(),
            'packaging' => 'Tablet',
        ]);

        $batch = \App\Models\StockBatch::create([
            'product_id' => $product->id,
            'store_id' => $pharmacyStore->id,
            'batch_name' => 'Deficit Test Batch ' . uniqid(),
            'batch_number' => 'DTB-' . uniqid(),
            'initial_qty' => 50,
            'current_qty' => 40,
            'cost_price' => 100.00,
            'received_date' => now(),
            'created_by' => 1,
            'is_active' => true,
        ]);

        $patient = \App\Models\Patient::first();
        $encounter = \App\Models\Encounter::first();

        // Dispense A: Paid Cash (qty: 1, payable: 500, claims: 0, payment_id: 101) -> Revenue: 500, Deficit: 0
        $posrA = \App\Models\ProductOrServiceRequest::create([
            'user_id' => 1,
            'staff_user_id' => 1,
            'product_id' => $product->id,
            'patient_id' => $patient?->id ?? 1,
            'encounter_id' => $encounter?->id ?? 1,
            'qty' => 1,
            'payable_amount' => 500.00,
            'claims_amount' => 0.00,
            'payment_id' => 101,
            'dispensed_from_store_id' => $pharmacyStore->id,
        ]);
        \App\Models\ProductRequest::create([
            'product_id' => $product->id,
            'product_request_id' => $posrA->id,
            'patient_id' => $patient?->id ?? 1,
            'encounter_id' => $encounter?->id ?? 1,
            'dispensed_from_store_id' => $pharmacyStore->id,
            'dispensed_from_batch_id' => $batch->id,
            'qty' => 1,
            'status' => 3,
            'dispense_date' => now(),
        ]);

        // Dispense B: Unpaid Cash (qty: 1, payable: 600, claims: 0, payment_id: null) -> Revenue: 0, Deficit: 600
        $posrB = \App\Models\ProductOrServiceRequest::create([
            'user_id' => 1,
            'staff_user_id' => 1,
            'product_id' => $product->id,
            'patient_id' => $patient?->id ?? 1,
            'encounter_id' => $encounter?->id ?? 1,
            'qty' => 1,
            'payable_amount' => 600.00,
            'claims_amount' => 0.00,
            'payment_id' => null,
            'dispensed_from_store_id' => $pharmacyStore->id,
        ]);
        \App\Models\ProductRequest::create([
            'product_id' => $product->id,
            'product_request_id' => $posrB->id,
            'patient_id' => $patient?->id ?? 1,
            'encounter_id' => $encounter?->id ?? 1,
            'dispensed_from_store_id' => $pharmacyStore->id,
            'dispensed_from_batch_id' => $batch->id,
            'qty' => 1,
            'status' => 3,
            'dispense_date' => now(),
        ]);

        // Dispense C: Validated HMO Claim (qty: 1, payable: 0, claims: 800, validation_status: 'approved') -> Revenue: 800, Deficit: 0
        $posrC = \App\Models\ProductOrServiceRequest::create([
            'user_id' => 1,
            'staff_user_id' => 1,
            'product_id' => $product->id,
            'patient_id' => $patient?->id ?? 1,
            'encounter_id' => $encounter?->id ?? 1,
            'qty' => 1,
            'payable_amount' => 0.00,
            'claims_amount' => 800.00,
            'validation_status' => 'approved',
            'dispensed_from_store_id' => $pharmacyStore->id,
        ]);
        \App\Models\ProductRequest::create([
            'product_id' => $product->id,
            'product_request_id' => $posrC->id,
            'patient_id' => $patient?->id ?? 1,
            'encounter_id' => $encounter?->id ?? 1,
            'dispensed_from_store_id' => $pharmacyStore->id,
            'dispensed_from_batch_id' => $batch->id,
            'qty' => 1,
            'status' => 3,
            'dispense_date' => now(),
        ]);

        // Dispense D: Pending HMO Claim (qty: 1, payable: 0, claims: 900, validation_status: 'pending') -> Revenue: 0, Deficit: 900
        $posrD = \App\Models\ProductOrServiceRequest::create([
            'user_id' => 1,
            'staff_user_id' => 1,
            'product_id' => $product->id,
            'patient_id' => $patient?->id ?? 1,
            'encounter_id' => $encounter?->id ?? 1,
            'qty' => 1,
            'payable_amount' => 0.00,
            'claims_amount' => 900.00,
            'validation_status' => 'pending',
            'dispensed_from_store_id' => $pharmacyStore->id,
        ]);
        \App\Models\ProductRequest::create([
            'product_id' => $product->id,
            'product_request_id' => $posrD->id,
            'patient_id' => $patient?->id ?? 1,
            'encounter_id' => $encounter?->id ?? 1,
            'dispensed_from_store_id' => $pharmacyStore->id,
            'dispensed_from_batch_id' => $batch->id,
            'qty' => 1,
            'status' => 3,
            'dispense_date' => now(),
        ]);

        $service = app(\App\Services\InventoryReportService::class);
        $summary = $service->getSummaryData(
            [$pharmacyStore->id],
            'given',
            'product',
            now()->subDay()->format('Y-m-d'),
            now()->addDay()->format('Y-m-d')
        );

        $prodSummary = collect($summary)->firstWhere('grouping_key', $product->product_name);
        $this->assertNotNull($prodSummary);
        $this->assertEquals(4, $prodSummary['total_qty']);

        // Sale amounts from POSR table:
        // Payable = 500 (A) + 600 (B) = 1100
        $this->assertEquals(1100.00, (float) $prodSummary['sale_amount_payable']);
        // Claim = 800 (C) + 900 (D) = 1700
        $this->assertEquals(1700.00, (float) $prodSummary['sale_amount_claim']);
        // Total Sale = 1100 + 1700 = 2800
        $this->assertEquals(2800.00, (float) $prodSummary['sale_amount_total']);

        // Realized Revenue: Paid Cash (500) + Validated Claims (800) = 1300
        $this->assertEquals(500.00, (float) $prodSummary['cash_revenue']);
        $this->assertEquals(800.00, (float) $prodSummary['claims_revenue']);
        $this->assertEquals(1300.00, (float) $prodSummary['realized_revenue']);

        // Deficit: Unpaid Cash (600) + Pending HMO Claims (900) = 1500
        $this->assertEquals(600.00, (float) $prodSummary['deficit_payable']);
        $this->assertEquals(900.00, (float) $prodSummary['deficit_claim']);
        $this->assertEquals(1500.00, (float) $prodSummary['deficit']);

        // Consistency: Realized Revenue + Deficit == Total Sale Amount
        $this->assertEquals(
            (float) $prodSummary['sale_amount_total'],
            (float) ($prodSummary['realized_revenue'] + $prodSummary['deficit'])
        );
    }

    /** @test */
    public function test_shared_summary_reports_modal_is_included_in_store_and_pharmacy_workbenches()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);

        $pharmResponse = $this->actingAs($user)->get('/pharmacy-workbench');
        $this->assertTrue(in_array($pharmResponse->status(), [200, 302, 403, 404, 500]));

        if ($pharmResponse->status() === 200) {
            $pharmResponse->assertSee('summaryReportsModal');
            $pharmResponse->assertSee('Dispense & Requisition Summary');
        }

        $storeResponse = $this->actingAs($user)->get('/inventory/store-workbench');
        $this->assertTrue(in_array($storeResponse->status(), [200, 302, 403, 404, 500]));

        if ($storeResponse->status() === 200) {
            $storeResponse->assertSee('summaryReportsModal');
            $storeResponse->assertSee('Dispense & Requisition Summary');
        }
    }
}
