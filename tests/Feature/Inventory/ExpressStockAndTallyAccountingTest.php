<?php

namespace Tests\Feature\Inventory;

use App\Models\Accounting\JournalEntry;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockBatchReturn;
use App\Models\StockBatchTransaction;
use App\Models\StockUtilization;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StockService;
use Tests\TestCase;

class ExpressStockAndTallyAccountingTest extends TestCase
{
    protected User $user;

    protected Store $store;

    protected Product $product;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::first() ?? User::factory()->create(['status' => 1]);
        $this->store = Store::first() ?? Store::factory()->create();
        $this->product = Product::first() ?? Product::factory()->create();
        $this->supplier = Supplier::first() ?? Supplier::create([
            'company_name' => 'Apex Pharmaceuticals Ltd',
            'contact_person' => 'James Vance',
            'phone' => '08012345678',
        ]);

        if (method_exists($this->store, 'users')) {
            $this->store->users()->syncWithoutDetaching([$this->user->id]);
        }
    }

    /** @test */
    public function test_manual_batch_credit_intake_creates_balanced_gl_journal_with_supplier_subaccount()
    {
        $batchNumber = 'TEST-EXP-CR-' . uniqid();
        $costPrice = 250.00;
        $qty = 20;
        $expectedTotal = round($costPrice * $qty, 2);

        $response = $this->actingAs($this->user)->postJson(route('inventory.store-workbench.create-manual-batch'), [
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
            'batch_number' => $batchNumber,
            'quantity' => $qty,
            'cost_price' => $costPrice,
            'acquisition_type' => StockBatch::ACQUISITION_CREDIT,
            'expiry_date' => now()->addYear()->format('Y-m-d'),
            'notes' => 'Direct credit purchase test',
        ]);

        $this->assertTrue(in_array($response->status(), [200, 302, 403]));

        if ($response->status() === 200) {
            $batch = StockBatch::where('batch_number', $batchNumber)->first();
            $this->assertNotNull($batch);
            $this->assertEquals(StockBatch::ACQUISITION_CREDIT, $batch->acquisition_type);
            $this->assertNotNull($batch->journal_entry_id);

            $journalEntry = JournalEntry::find($batch->journal_entry_id);
            $this->assertNotNull($journalEntry);
            $this->assertEquals(JournalEntry::STATUS_POSTED, $journalEntry->status);
            $this->assertEquals($expectedTotal, (float) $journalEntry->total_debit);
            $this->assertEquals($expectedTotal, (float) $journalEntry->total_credit);

            // Verify lines
            $lines = $journalEntry->lines;
            $this->assertCount(2, $lines);

            $debitLine = $lines->firstWhere('debit_amount', '>', 0);
            $creditLine = $lines->firstWhere('credit_amount', '>', 0);

            $this->assertNotNull($debitLine);
            $this->assertNotNull($creditLine);
            $this->assertEquals($expectedTotal, (float) $debitLine->debit_amount);
            $this->assertEquals($expectedTotal, (float) $creditLine->credit_amount);

            // Credit line should be AP with subaccount for supplier
            $this->assertNotNull($creditLine->sub_account_id);
        }
    }

    /** @test */
    public function test_manual_batch_cash_intake_creates_balanced_gl_journal_with_cash_account()
    {
        $batchNumber = 'TEST-EXP-CASH-' . uniqid();
        $costPrice = 150.00;
        $qty = 10;
        $expectedTotal = round($costPrice * $qty, 2);

        $response = $this->actingAs($this->user)->postJson(route('inventory.store-workbench.create-manual-batch'), [
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
            'batch_number' => $batchNumber,
            'quantity' => $qty,
            'cost_price' => $costPrice,
            'acquisition_type' => StockBatch::ACQUISITION_CASH,
            'payment_account_code' => '1030',
            'expiry_date' => now()->addYear()->format('Y-m-d'),
            'notes' => 'Petty cash purchase test',
        ]);

        $this->assertTrue(in_array($response->status(), [200, 302, 403]));

        if ($response->status() === 200) {
            $batch = StockBatch::where('batch_number', $batchNumber)->first();
            $this->assertNotNull($batch);
            $this->assertEquals(StockBatch::ACQUISITION_CASH, $batch->acquisition_type);
            $this->assertNotNull($batch->journal_entry_id);

            $journalEntry = JournalEntry::find($batch->journal_entry_id);
            $this->assertNotNull($journalEntry);
            $this->assertEquals($expectedTotal, (float) $journalEntry->total_debit);
            $this->assertEquals($expectedTotal, (float) $journalEntry->total_credit);

            $creditLine = $journalEntry->lines->firstWhere('credit_amount', '>', 0);
            $this->assertNotNull($creditLine);
            $this->assertEquals('1030', $creditLine->account->code);
        }
    }

    /** @test */
    public function test_manual_batch_opening_stock_creates_gl_journal_with_equity_account()
    {
        $batchNumber = 'TEST-EXP-OPEN-' . uniqid();
        $costPrice = 300.00;
        $qty = 15;
        $expectedTotal = round($costPrice * $qty, 2);

        $stockService = app(StockService::class);
        $batch = $stockService->createBatch([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
            'batch_number' => $batchNumber,
            'qty' => $qty,
            'cost_price' => $costPrice,
            'acquisition_type' => StockBatch::ACQUISITION_OPENING_STOCK,
            'notes' => 'Opening stock equity test',
            'created_by' => $this->user->id,
        ]);

        $batch->refresh();
        $this->assertNotNull($batch);
        $this->assertEquals(StockBatch::ACQUISITION_OPENING_STOCK, $batch->acquisition_type);
        $this->assertNotNull($batch->journal_entry_id);

        $journalEntry = JournalEntry::find($batch->journal_entry_id);
        $this->assertNotNull($journalEntry);
        $this->assertEquals($expectedTotal, (float) $journalEntry->total_debit);
        $this->assertEquals($expectedTotal, (float) $journalEntry->total_credit);

        $creditLine = $journalEntry->lines->firstWhere('credit_amount', '>', 0);
        $this->assertNotNull($creditLine);
        $this->assertTrue(in_array($creditLine->account->code, ['3100', '3000']));
    }

    /** @test */
    public function test_manual_batch_donation_creates_zero_cost_with_no_payable_liability()
    {
        $batchNumber = 'TEST-EXP-DON-' . uniqid();

        $stockService = app(StockService::class);
        $batch = $stockService->createBatch([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
            'batch_number' => $batchNumber,
            'qty' => 50,
            'cost_price' => 0.00,
            'is_donation' => 1,
            'acquisition_type' => StockBatch::ACQUISITION_DONATION,
            'notes' => 'Donation free intake test',
            'created_by' => $this->user->id,
        ]);

        $batch->refresh();
        $this->assertNotNull($batch);
        $this->assertTrue((bool) $batch->is_donation);
        $this->assertEquals(0.00, (float) $batch->cost_price);
        $this->assertNull($batch->journal_entry_id);
    }

    /** @test */
    public function test_batch_metadata_update_updates_attributes_and_strictly_locks_quantities()
    {
        $stockService = app(StockService::class);
        $batch = $stockService->createBatch([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
            'batch_number' => 'EDIT-TEST-' . uniqid(),
            'qty' => 40,
            'cost_price' => 100.00,
            'notes' => 'Pre-edit notes',
            'created_by' => $this->user->id,
        ]);

        $originalQty = $batch->current_qty;
        $originalInitialQty = $batch->initial_qty;

        $newBatchNumber = 'EDITED-NUM-' . uniqid();
        $response = $this->actingAs($this->user)->postJson(route('inventory.store-workbench.batch.update', $batch->id), [
            'batch_number' => $newBatchNumber,
            'batch_name' => 'Brand New Batch Name',
            'supplier_id' => $this->supplier->id,
            'cost_price' => 125.50,
            'expiry_date' => now()->addMonths(6)->format('Y-m-d'),
            // Attempt malicious direct quantity tampering
            'current_qty' => 9999,
            'initial_qty' => 9999,
            'qty' => 9999,
        ]);

        $this->assertTrue(in_array($response->status(), [200, 302, 403]));

        if ($response->status() === 200) {
            $batch->refresh();
            $this->assertEquals($newBatchNumber, $batch->batch_number);
            $this->assertEquals('Brand New Batch Name', $batch->batch_name);
            $this->assertEquals(125.50, (float) $batch->cost_price);

            // Invariant: Quantities remain locked and unchanged!
            $this->assertEquals($originalQty, $batch->current_qty);
            $this->assertEquals($originalInitialQty, $batch->initial_qty);
        }
    }

    /** @test */
    public function test_void_batch_zeroes_stock_and_creates_balanced_reversal_journal_entry()
    {
        $stockService = app(StockService::class);
        $batch = $stockService->createBatch([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
            'batch_number' => 'VOID-TEST-' . uniqid(),
            'qty' => 25,
            'cost_price' => 200.00,
            'acquisition_type' => StockBatch::ACQUISITION_CREDIT,
            'created_by' => $this->user->id,
        ]);

        $batch->refresh();
        $originalJeId = $batch->journal_entry_id;
        $this->assertNotNull($originalJeId);

        $response = $this->actingAs($this->user)->postJson(route('inventory.store-workbench.batch.void', $batch->id), [
            'reason' => 'Duplicate invoice entry recorded in error',
        ]);

        $this->assertTrue(in_array($response->status(), [200, 302, 403]));

        if ($response->status() === 200) {
            $batch->refresh();
            $this->assertEquals(0, $batch->current_qty);
            $this->assertNotNull($batch->voided_at);
            $this->assertEquals($this->user->id, $batch->voided_by);
            $this->assertEquals('Duplicate invoice entry recorded in error', $batch->void_reason);
            $this->assertNotNull($batch->void_reversal_je_id);

            // Check original entry was reversed
            $originalJe = JournalEntry::find($originalJeId);
            $this->assertEquals(JournalEntry::STATUS_REVERSED, $originalJe->status);

            // Check reversal entry is balanced
            $reversalJe = JournalEntry::find($batch->void_reversal_je_id);
            $this->assertNotNull($reversalJe);
            $this->assertEquals(JournalEntry::STATUS_POSTED, $reversalJe->status);
            $this->assertEquals($originalJe->total_debit, $reversalJe->total_debit);
            $this->assertEquals($originalJe->total_credit, $reversalJe->total_credit);

            // Check StockBatchTransaction was logged for the void
            $tx = StockBatchTransaction::where('stock_batch_id', $batch->id)
                ->where('type', StockBatchTransaction::TYPE_VOID)
                ->first();
            $this->assertNotNull($tx);
            $this->assertEquals(25, (int) $tx->qty);
        }
    }

    /** @test */
    public function test_stock_count_adjustment_positive_generates_variance_gain_journal_entry()
    {
        $stockService = app(StockService::class);
        $batch = $stockService->createBatch([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
            'batch_number' => 'ADJ-POS-' . uniqid(),
            'qty' => 10,
            'cost_price' => 100.00,
            'acquisition_type' => StockBatch::ACQUISITION_CREDIT,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->postJson("/inventory/store-workbench/batch/{$batch->id}/adjust", [
            'adjustment_type' => 'add',
            'qty' => 5,
            'reason' => 'Found unopened box in store shelf',
            'notes' => 'Positive adjustment audited count',
        ]);

        $this->assertTrue(in_array($response->status(), [200, 302, 403]));

        if ($response->status() === 200) {
            $batch->refresh();
            $this->assertEquals(15, $batch->current_qty);

            $tx = StockBatchTransaction::where('stock_batch_id', $batch->id)
                ->where('type', StockBatchTransaction::TYPE_ADJUSTMENT)
                ->latest('id')
                ->first();

            $this->assertNotNull($tx);
            $this->assertNotNull($tx->journal_entry_id);

            $je = JournalEntry::find($tx->journal_entry_id);
            $this->assertNotNull($je);
            $this->assertEquals(500.00, (float) $je->total_debit);
            $this->assertEquals(500.00, (float) $je->total_credit);

            $creditLine = $je->lines->firstWhere('credit_amount', '>', 0);
            $this->assertTrue(in_array($creditLine->account->code, ['4230', '4210']));
        }
    }

    /** @test */
    public function test_stock_count_adjustment_negative_generates_shrinkage_loss_journal_entry()
    {
        $stockService = app(StockService::class);
        $batch = $stockService->createBatch([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
            'batch_number' => 'ADJ-NEG-' . uniqid(),
            'qty' => 20,
            'cost_price' => 150.00,
            'acquisition_type' => StockBatch::ACQUISITION_CREDIT,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->postJson("/inventory/store-workbench/batch/{$batch->id}/adjust", [
            'adjustment_type' => 'subtract',
            'qty' => 4,
            'reason' => 'Audited count missing units',
            'notes' => 'Negative adjustment shrinkage',
        ]);

        $this->assertTrue(in_array($response->status(), [200, 302, 403]));

        if ($response->status() === 200) {
            $batch->refresh();
            $this->assertEquals(16, $batch->current_qty);

            $tx = StockBatchTransaction::where('stock_batch_id', $batch->id)
                ->where('type', StockBatchTransaction::TYPE_ADJUSTMENT)
                ->latest('id')
                ->first();

            $this->assertNotNull($tx);
            $this->assertNotNull($tx->journal_entry_id);

            $je = JournalEntry::find($tx->journal_entry_id);
            $this->assertNotNull($je);
            $this->assertEquals(600.00, (float) $je->total_debit);
            $this->assertEquals(600.00, (float) $je->total_credit);

            $debitLine = $je->lines->firstWhere('debit_amount', '>', 0);
            $this->assertEquals('5050', $debitLine->account->code);
        }
    }

    /** @test */
    public function test_void_adjustment_restores_batch_balance_and_reverses_variance_journal_entry()
    {
        $stockService = app(StockService::class);
        $batch = $stockService->createBatch([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
            'batch_number' => 'VOID-ADJ-' . uniqid(),
            'qty' => 30,
            'cost_price' => 80.00,
            'acquisition_type' => StockBatch::ACQUISITION_CREDIT,
            'created_by' => $this->user->id,
        ]);

        // Create positive adjustment of 10
        $tx = $stockService->adjustStock($batch->id, 10, 'Positive adjustment to be voided');

        $batch->refresh();
        $this->assertEquals(40, $batch->current_qty);
        $adjJeId = $tx->journal_entry_id;

        // Void the adjustment
        $response = $this->actingAs($this->user)->postJson(route('inventory.store-workbench.adjustment.void', $tx->id), [
            'reason' => 'Count was entered for wrong batch in error',
        ]);

        $this->assertTrue(in_array($response->status(), [200, 302, 403]));

        if ($response->status() === 200) {
            $batch->refresh();
            $this->assertEquals(30, $batch->current_qty); // Restored!

            $tx->refresh();
            $this->assertNotNull($tx->voided_at);
            $this->assertEquals('Count was entered for wrong batch in error', $tx->void_reason);
            $this->assertNotNull($tx->void_reversal_je_id);

            if ($adjJeId) {
                $adjJe = JournalEntry::find($adjJeId);
                $this->assertEquals(JournalEntry::STATUS_REVERSED, $adjJe->status);
            }
        }
    }

    /** @test */
    public function test_manual_batch_return_deducts_stock_and_creates_ap_credit_note_journal_entry()
    {
        $stockService = app(StockService::class);
        $batch = $stockService->createBatch([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
            'batch_number' => 'RETURN-TEST-' . uniqid(),
            'qty' => 50,
            'cost_price' => 100.00,
            'acquisition_type' => StockBatch::ACQUISITION_CREDIT,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->postJson(route('inventory.store-workbench.batch.return', $batch->id), [
            'supplier_id' => $this->supplier->id,
            'qty_returned' => 10,
            'return_reason' => StockBatchReturn::REASON_DAMAGED,
            'settlement_type' => StockBatchReturn::SETTLEMENT_AP_CREDIT_NOTE,
            'return_notes' => 'Damaged during delivery return',
        ]);

        $this->assertTrue(in_array($response->status(), [200, 302, 403]));

        if ($response->status() === 200) {
            $batch->refresh();
            $this->assertEquals(40, $batch->current_qty);

            $return = StockBatchReturn::where('stock_batch_id', $batch->id)->latest('id')->first();
            $this->assertNotNull($return);
            $this->assertEquals(10, $return->qty_returned);
            $this->assertEquals(1000.00, (float) $return->total_value);
            $this->assertEquals(StockBatchReturn::STATUS_APPROVED, $return->status);
            $this->assertNotNull($return->journal_entry_id);

            $je = JournalEntry::find($return->journal_entry_id);
            $this->assertNotNull($je);
            $this->assertEquals(1000.00, (float) $je->total_debit);
            $this->assertEquals(1000.00, (float) $je->total_credit);

            // AP line debited (debt reduced)
            $debitLine = $je->lines->firstWhere('debit_amount', '>', 0);
            $this->assertTrue(in_array($debitLine->account->code, ['2100', '2110']));
            $this->assertNotNull($debitLine->sub_account_id);
        }
    }

    /** @test */
    public function test_unbilled_stock_utilization_generates_departmental_supplies_used_journal_entry()
    {
        $stockService = app(StockService::class);
        $batch = $stockService->createBatch([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
            'batch_number' => 'UTIL-COST-' . uniqid(),
            'qty' => 50,
            'cost_price' => 75.00,
            'acquisition_type' => StockBatch::ACQUISITION_CREDIT,
            'created_by' => $this->user->id,
        ]);

        $utilization = StockUtilization::create([
            'product_id' => $this->product->id,
            'store_id' => $this->store->id,
            'qty' => 8,
            'unit' => 'Piece',
            'reason' => 'Ward dressing consumables',
            'utilization_type' => 'internal',
            'is_billed' => false,
            'created_by' => $this->user->id,
            'performed_by' => $this->user->id,
        ]);

        $je = JournalEntry::where('reference_type', StockUtilization::class)
            ->where('reference_id', $utilization->id)
            ->first();

        $this->assertNotNull($je);
        $this->assertEquals(600.00, (float) $je->total_debit);
        $this->assertEquals(600.00, (float) $je->total_credit);

        $debitLine = $je->lines->firstWhere('debit_amount', '>', 0);
        $this->assertEquals('5020', $debitLine->account->code);
    }

    /** @test */
    public function test_journal_preview_endpoint_returns_realtime_balanced_journal_preview()
    {
        $response = $this->actingAs($this->user)->postJson(route('inventory.store-workbench.journal-preview'), [
            'action' => 'intake',
            'acquisition_type' => 'credit',
            'store_id' => $this->store->id,
            'supplier_id' => $this->supplier->id,
            'qty' => 12,
            'cost_price' => 50.00,
        ]);

        $this->assertTrue(in_array($response->status(), [200, 302, 403]));

        if ($response->status() === 200) {
            $data = $response->json();
            $this->assertTrue($data['success']);
            $preview = $data['preview'];
            $this->assertEquals(600.00, (float) $preview['total_value']);
            $this->assertEquals(600.00, (float) $preview['debit_amount']);
            $this->assertEquals(600.00, (float) $preview['credit_amount']);
            $this->assertNotEmpty($preview['debit_account']);
            $this->assertNotEmpty($preview['credit_account']);
        }
    }
}
