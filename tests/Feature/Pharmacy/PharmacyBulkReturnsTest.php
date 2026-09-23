<?php

namespace Tests\Feature\Pharmacy;

use App\Models\Patient;
use App\Models\PatientAccount;
use App\Models\PharmacyDamage;
use App\Models\PharmacyReturn;
use App\Models\Product;
use App\Models\ProductOrServiceRequest;
use App\Models\ProductRequest;
use App\Models\StockBatch;
use App\Models\Store;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

class PharmacyBulkReturnsTest extends TestCase
{
    protected $user;

    protected $store;

    protected $product1;

    protected $product2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['status' => 1]);
        $this->store = Store::firstOrCreate(['store_name' => 'Main Pharmacy Store']);

        $this->product1 = Product::create([
            'product_name' => 'Amoxicillin 500mg ' . uniqid(),
            'user_id' => $this->user->id,
            'category_id' => 1,
            'status' => 1,
        ]);

        $this->product2 = Product::create([
            'product_name' => 'Paracetamol 500mg ' . uniqid(),
            'user_id' => $this->user->id,
            'category_id' => 1,
            'status' => 1,
        ]);
    }

    /**
     * Helper to create a dispensed item with billing and batch.
     */
    protected function createDispensedItem($patient, $product, $qty, $payable, $claims, $dispenseDate = null)
    {
        $batch = StockBatch::create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'batch_name' => 'BATCH-' . uniqid(),
            'batch_number' => 'BATCH-' . uniqid(),
            'initial_qty' => 100,
            'current_qty' => 50,
            'cost_price' => 15.00,
            'created_by' => $this->user->id,
            'is_active' => 1,
        ]);

        $total = $payable + $claims;

        $billReq = ProductOrServiceRequest::create([
            'user_id' => $patient->user_id,
            'staff_user_id' => $this->user->id,
            'product_id' => $product->id,
            'qty' => $qty,
            'amount' => $total,
            'payable_amount' => $payable,
            'claims_amount' => $claims,
            'validation_status' => 'approved',
            'dispensed_from_store_id' => $this->store->id,
        ]);

        $prodReq = ProductRequest::create([
            'product_request_id' => $billReq->id,
            'patient_id' => $patient->id,
            'product_id' => $product->id,
            'qty' => $qty,
            'status' => 3, // Dispensed
            'dispensed_by' => $this->user->id,
            'dispensed_from_store_id' => $this->store->id,
            'dispensed_from_batch_id' => $batch->id,
            'dispense_date' => $dispenseDate ?? now(),
        ]);

        return [$prodReq, $billReq, $batch];
    }

    /** @test */
    public function test_search_dispensed_items_filters_by_specific_date()
    {
        $patient = Patient::factory()->create();

        [$itemToday] = $this->createDispensedItem($patient, $this->product1, 2, 200, 0, Carbon::parse('2026-09-22 10:00:00'));
        [$itemPast] = $this->createDispensedItem($patient, $this->product2, 1, 100, 0, Carbon::parse('2026-09-15 10:00:00'));

        $response = $this->actingAs($this->user)->getJson(route('pharmacy.returns.search-dispensed', [
            'date' => '2026-09-22',
        ]));

        $response->assertStatus(200);
        $data = $response->json('data');

        $ids = array_map(function ($row) {
            preg_match("/data-id='(\d+)'/", $row['checkbox'], $matches);

            return isset($matches[1]) ? (int)$matches[1] : null;
        }, $data);

        $this->assertContains($itemToday->id, $ids);
        $this->assertNotContains($itemPast->id, $ids);
    }

    /** @test */
    public function test_search_dispensed_items_filters_by_date_range()
    {
        $patient = Patient::factory()->create();

        [$item1] = $this->createDispensedItem($patient, $this->product1, 2, 200, 0, Carbon::parse('2026-09-18 10:00:00'));
        [$item2] = $this->createDispensedItem($patient, $this->product2, 1, 100, 0, Carbon::parse('2026-09-20 14:00:00'));
        [$itemOut] = $this->createDispensedItem($patient, $this->product1, 3, 300, 0, Carbon::parse('2026-09-22 10:00:00'));

        $response = $this->actingAs($this->user)->getJson(route('pharmacy.returns.search-dispensed', [
            'start_date' => '2026-09-17',
            'end_date' => '2026-09-21',
        ]));

        $response->assertStatus(200);
        $data = $response->json('data');

        $ids = array_map(function ($row) {
            preg_match("/data-id='(\d+)'/", $row['checkbox'], $matches);

            return isset($matches[1]) ? (int)$matches[1] : null;
        }, $data);

        $this->assertContains($item1->id, $ids);
        $this->assertContains($item2->id, $ids);
        $this->assertNotContains($itemOut->id, $ids);
    }

    /** @test */
    public function test_search_dispensed_items_filters_by_patient()
    {
        $uniqueFileNo = 'PAT-TEST-' . uniqid();
        $patient1 = Patient::factory()->create(['file_no' => $uniqueFileNo]);
        $patient2 = Patient::factory()->create(['file_no' => 'PAT-OTHER-' . uniqid()]);

        [$item1] = $this->createDispensedItem($patient1, $this->product1, 2, 200, 0);
        [$item2] = $this->createDispensedItem($patient2, $this->product2, 1, 100, 0);

        $response = $this->actingAs($this->user)->getJson(route('pharmacy.returns.search-dispensed', [
            'patient_search' => $uniqueFileNo,
        ]));

        $response->assertStatus(200);
        $data = $response->json('data');

        $ids = array_map(function ($row) {
            preg_match("/data-id='(\d+)'/", $row['checkbox'], $matches);

            return isset($matches[1]) ? (int)$matches[1] : null;
        }, $data);

        $this->assertContains($item1->id, $ids);
        $this->assertNotContains($item2->id, $ids);
    }

    /** @test */
    public function test_bulk_store_creates_multiple_returns_atomically()
    {
        $patient = Patient::factory()->create();

        // 10 units dispensed: ₦300 patient payable, ₦200 HMO claim (Total ₦500)
        [$item1] = $this->createDispensedItem($patient, $this->product1, 10, 300, 200);
        // 5 units dispensed: ₦500 patient payable (Total ₦500)
        [$item2] = $this->createDispensedItem($patient, $this->product2, 5, 500, 0);

        $payload = [
            'items' => [
                [
                    'product_request_id' => $item1->id,
                    'qty_returned' => 4,
                    'return_condition' => 'good',
                    'return_reason' => 'Patient was discharged early and medications unneeded.',
                ],
                [
                    'product_request_id' => $item2->id,
                    'qty_returned' => 2,
                    'return_condition' => 'wrong_item',
                    'return_reason' => 'Wrong packaging prescribed by doctor.',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->postJson(route('pharmacy.returns.bulk-store'), $payload);

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'count' => 2]);

        // Verify status and returned_qty on product requests: partial returns maintain status 3
        $item1->refresh();
        $item2->refresh();
        $this->assertEquals(3, $item1->status);
        $this->assertEquals(4.0, (float)$item1->returned_qty);
        $this->assertEquals(3, $item2->status);
        $this->assertEquals(2.0, (float)$item2->returned_qty);

        // Verify PharmacyReturn records
        $return1 = PharmacyReturn::where('product_request_id', $item1->id)->first();
        $this->assertNotNull($return1);
        $this->assertEquals(4, $return1->qty_returned);
        $this->assertEquals('good', $return1->return_condition);
        $this->assertTrue((bool)$return1->restock);
        // Proportional refund for 4 of 10 items (40% of ₦500 = ₦200):
        // Patient payable portion: 40% of ₦300 = ₦120
        // HMO claims portion: 40% of ₦200 = ₦80
        $this->assertEquals(200.0, (float)$return1->refund_amount);
        $this->assertEquals(120.0, (float)$return1->refund_to_patient);
        $this->assertEquals(80.0, (float)$return1->refund_to_hmo);
        $this->assertEquals('pending', $return1->status);

        $return2 = PharmacyReturn::where('product_request_id', $item2->id)->first();
        $this->assertNotNull($return2);
        $this->assertEquals(2, $return2->qty_returned);
        $this->assertEquals(200.0, (float)$return2->refund_amount);
        $this->assertEquals(200.0, (float)$return2->refund_to_patient);
        $this->assertEquals(0.0, (float)$return2->refund_to_hmo);
    }

    /** @test */
    public function test_bulk_store_validates_quantity_not_exceeding_dispensed()
    {
        $patient = Patient::factory()->create();
        [$item] = $this->createDispensedItem($patient, $this->product1, 3, 300, 0);

        $payload = [
            'items' => [
                [
                    'product_request_id' => $item->id,
                    'qty_returned' => 5, // Exceeds 3
                    'return_condition' => 'good',
                    'return_reason' => 'Returning more than dispensed should be rejected.',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->postJson(route('pharmacy.returns.bulk-store'), $payload);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('pharmacy_returns', ['product_request_id' => $item->id]);
    }

    /** @test */
    public function test_partial_returns_flow_and_remaining_quantity_tracking()
    {
        $patient = Patient::factory()->create();
        [$item] = $this->createDispensedItem($patient, $this->product1, 5, 500, 0, now());

        // 1. First partial return: 2 of 5 units
        $this->actingAs($this->user)->postJson(route('pharmacy.returns.bulk-store'), [
            'items' => [
                [
                    'product_request_id' => $item->id,
                    'qty_returned' => 2,
                    'return_condition' => 'good',
                    'return_reason' => 'First partial return of unneeded units.',
                ],
            ],
        ])->assertStatus(200);

        $item->refresh();
        $this->assertEquals(3, $item->status); // Still dispensed/partially returned
        $this->assertEquals(2.0, (float)$item->returned_qty);

        // 2. Search dispensed items: item must still appear and show remaining quantity (3 units)
        $searchRes = $this->actingAs($this->user)->getJson(route('pharmacy.returns.search-dispensed', [
            'patient_id' => $patient->id,
        ]));
        $searchRes->assertStatus(200);
        $searchData = $searchRes->json('data');
        $this->assertCount(1, $searchData);

        $rowHtml = $searchData[0]['checkbox'];
        $this->assertStringContainsString("data-id='{$item->id}'", $rowHtml);
        $this->assertStringContainsString("data-qty='3'", $rowHtml);
        $this->assertStringContainsString("data-dispensed-qty='5'", $rowHtml);
        $this->assertStringContainsString("data-already-returned='2'", $rowHtml);

        // 3. Return quantity exceeding remaining quantity (4 > 3) must be rejected
        $overReturnRes = $this->actingAs($this->user)->postJson(route('pharmacy.returns.bulk-store'), [
            'items' => [
                [
                    'product_request_id' => $item->id,
                    'qty_returned' => 4,
                    'return_condition' => 'good',
                    'return_reason' => 'Attempting to return more than remaining.',
                ],
            ],
        ]);
        $overReturnRes->assertStatus(422);

        // 4. Return remaining 3 units: succeeds and transitions item to status 4 (fully returned)
        $secondReturnRes = $this->actingAs($this->user)->postJson(route('pharmacy.returns.bulk-store'), [
            'items' => [
                [
                    'product_request_id' => $item->id,
                    'qty_returned' => 3,
                    'return_condition' => 'good',
                    'return_reason' => 'Second return of remaining 3 units.',
                ],
            ],
        ]);
        $secondReturnRes->assertStatus(200);

        $item->refresh();
        $this->assertEquals(4, $item->status); // Fully returned
        $this->assertEquals(5.0, (float)$item->returned_qty);

        // 5. Search dispensed items again: fully returned item must no longer appear
        $searchRes2 = $this->actingAs($this->user)->getJson(route('pharmacy.returns.search-dispensed', [
            'patient_id' => $patient->id,
        ]));
        $searchRes2->assertStatus(200);
        $this->assertCount(0, $searchRes2->json('data'));

        // 6. Any further return attempt on the fully returned item must be rejected
        $finalReturnRes = $this->actingAs($this->user)->postJson(route('pharmacy.returns.bulk-store'), [
            'items' => [
                [
                    'product_request_id' => $item->id,
                    'qty_returned' => 1,
                    'return_condition' => 'good',
                    'return_reason' => 'Third return on fully returned item should fail.',
                ],
            ],
        ]);
        $finalReturnRes->assertStatus(422);
    }

    /** @test */
    public function test_legacy_partial_returns_with_status_4_show_remaining_balance_in_search()
    {
        $patient = Patient::factory()->create();
        [$item] = $this->createDispensedItem($patient, $this->product1, 10, 1000, 0, Carbon::parse('2026-09-23 08:00:00'));

        // Simulate an earlier return made today under previous code (status was set to 4, returned_qty was 3)
        $item->update([
            'status' => 4,
            'returned_qty' => 3,
        ]);

        PharmacyReturn::create([
            'product_request_id' => $item->id,
            'product_or_service_request_id' => $item->product_request_id,
            'patient_id' => $patient->id,
            'product_id' => $this->product1->id,
            'store_id' => $this->store->id,
            'qty_returned' => 3,
            'original_qty' => 10,
            'refund_amount' => 300,
            'original_amount' => 1000,
            'return_condition' => 'good',
            'return_reason' => 'Partial return made earlier today under previous code.',
            'restock' => true,
            'status' => 'pending',
            'created_by' => $this->user->id,
            'created_at' => Carbon::parse('2026-09-23 09:00:00'),
        ]);

        // When searching today's date:
        $response = $this->actingAs($this->user)->getJson(route('pharmacy.returns.search-dispensed', [
            'date' => '2026-09-23',
            'patient_id' => $patient->id,
        ]));

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);

        $rowHtml = $data[0]['checkbox'];
        $this->assertStringContainsString("data-id='{$item->id}'", $rowHtml);
        $this->assertStringContainsString("data-qty='7'", $rowHtml);
        $this->assertStringContainsString("data-dispensed-qty='10'", $rowHtml);
        $this->assertStringContainsString("data-already-returned='3'", $rowHtml);

        // Submitting another return of 7 units succeeds
        $secondReturnRes = $this->actingAs($this->user)->postJson(route('pharmacy.returns.bulk-store'), [
            'items' => [
                [
                    'product_request_id' => $item->id,
                    'qty_returned' => 7,
                    'return_condition' => 'good',
                    'return_reason' => 'Returning remaining balance.',
                ],
            ],
        ]);
        $secondReturnRes->assertStatus(200);
    }

    /** @test */
    public function test_bulk_approve_processes_multiple_returns()
    {
        $patient = Patient::factory()->create();

        [$item1, , $batch1] = $this->createDispensedItem($patient, $this->product1, 5, 500, 0);
        [$item2, , $batch2] = $this->createDispensedItem($patient, $this->product2, 4, 400, 0);

        $batch1InitialQty = $batch1->current_qty; // 50
        $batch2InitialQty = $batch2->current_qty; // 50

        // Create returns: item1 is 'good' (restock = true), item2 is 'damaged' (restock = false)
        $this->actingAs($this->user)->postJson(route('pharmacy.returns.bulk-store'), [
            'items' => [
                [
                    'product_request_id' => $item1->id,
                    'qty_returned' => 3,
                    'return_condition' => 'good',
                    'return_reason' => 'Patient switched to intravenous medication.',
                ],
                [
                    'product_request_id' => $item2->id,
                    'qty_returned' => 2,
                    'return_condition' => 'damaged',
                    'return_reason' => 'Broken vial noticed during patient discharge.',
                ],
            ],
        ])->assertStatus(200);

        $return1 = PharmacyReturn::where('product_request_id', $item1->id)->first();
        $return2 = PharmacyReturn::where('product_request_id', $item2->id)->first();

        // Perform bulk approval
        $response = $this->actingAs($this->user)->postJson(route('pharmacy.returns.bulk-approve'), [
            'return_ids' => [$return1->id, $return2->id],
            'approval_notes' => 'Batch approval verified by pharmacy manager.',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'approved_count' => 2,
            'total_restocked' => 1,
        ]);

        // Verify batch restock: batch1 should increase by 3; batch2 should not change
        $batch1->refresh();
        $batch2->refresh();
        $this->assertEquals($batch1InitialQty + 3, $batch1->current_qty);
        $this->assertEquals($batch2InitialQty, $batch2->current_qty);

        // Verify patient wallet credited
        $account = PatientAccount::where('patient_id', $patient->id)->first();
        $this->assertNotNull($account);
        // Total refund to patient: ₦300 (from return1) + ₦200 (from return2) = ₦500
        $this->assertGreaterThanOrEqual(500, (float)$account->balance);

        // Verify returns are approved/completed
        $return1->refresh();
        $return2->refresh();
        $this->assertEquals('completed', $return1->status);
        $this->assertEquals('completed', $return2->status);
    }

    /** @test */
    public function test_bulk_reject_reverts_product_request_to_dispensed()
    {
        $patient = Patient::factory()->create();

        [$item1] = $this->createDispensedItem($patient, $this->product1, 5, 500, 0);
        [$item2] = $this->createDispensedItem($patient, $this->product2, 4, 400, 0);

        $this->actingAs($this->user)->postJson(route('pharmacy.returns.bulk-store'), [
            'items' => [
                [
                    'product_request_id' => $item1->id,
                    'qty_returned' => 5,
                    'return_condition' => 'good',
                    'return_reason' => 'Patient requested return without original receipt.',
                ],
                [
                    'product_request_id' => $item2->id,
                    'qty_returned' => 4,
                    'return_condition' => 'good',
                    'return_reason' => 'Medicine box seal was broken on inspection.',
                ],
            ],
        ])->assertStatus(200);

        $return1 = PharmacyReturn::where('product_request_id', $item1->id)->first();
        $return2 = PharmacyReturn::where('product_request_id', $item2->id)->first();

        $this->assertEquals(4, $item1->fresh()->status);
        $this->assertEquals(4, $item2->fresh()->status);

        // Bulk reject
        $response = $this->actingAs($this->user)->postJson(route('pharmacy.returns.bulk-reject'), [
            'return_ids' => [$return1->id, $return2->id],
            'rejection_reason' => 'Hospital return policy prohibits returns past 24 hours of dispense.',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'rejected_count' => 2,
        ]);

        // Verify status rejected
        $return1->refresh();
        $return2->refresh();
        $this->assertEquals('rejected', $return1->status);
        $this->assertEquals('rejected', $return2->status);

        // Verify ProductRequests reverted back to status 3 (dispensed)
        $this->assertEquals(3, $item1->fresh()->status);
        $this->assertEquals(3, $item2->fresh()->status);
    }

    /** @test */
    public function test_returns_and_damages_datatables_order_latest_first()
    {
        $patient = Patient::factory()->create();
        [$item1] = $this->createDispensedItem($patient, $this->product1, 5, 500, 0);
        [$item2] = $this->createDispensedItem($patient, $this->product2, 5, 500, 0);

        // Create Return 1 earlier
        $returnEarlier = PharmacyReturn::create([
            'product_request_id' => $item1->id,
            'product_or_service_request_id' => $item1->product_request_id,
            'patient_id' => $patient->id,
            'product_id' => $this->product1->id,
            'store_id' => $this->store->id,
            'qty_returned' => 1,
            'original_qty' => 5,
            'refund_amount' => 100,
            'original_amount' => 500,
            'return_condition' => 'good',
            'return_reason' => 'Earlier return created for test ordering.',
            'restock' => true,
            'status' => 'pending',
            'created_by' => $this->user->id,
            'created_at' => Carbon::now()->subHours(2),
        ]);

        // Create Return 2 later
        $returnLater = PharmacyReturn::create([
            'product_request_id' => $item2->id,
            'product_or_service_request_id' => $item2->product_request_id,
            'patient_id' => $patient->id,
            'product_id' => $this->product2->id,
            'store_id' => $this->store->id,
            'qty_returned' => 1,
            'original_qty' => 5,
            'refund_amount' => 100,
            'original_amount' => 500,
            'return_condition' => 'good',
            'return_reason' => 'Later return created for test ordering.',
            'restock' => true,
            'status' => 'pending',
            'created_by' => $this->user->id,
            'created_at' => Carbon::now(),
        ]);

        $returnsRes = $this->actingAs($this->user)->getJson(route('pharmacy.returns.datatables'));
        $returnsRes->assertStatus(200);
        $returnsData = $returnsRes->json('data');

        // Find positions of returnLater and returnEarlier in the datatable list
        $laterIndex = null;
        $earlierIndex = null;
        foreach ($returnsData as $idx => $row) {
            if (isset($row['checkbox'])) {
                if (str_contains($row['checkbox'], "data-id=\"{$returnLater->id}\"")) {
                    $laterIndex = $idx;
                }
                if (str_contains($row['checkbox'], "data-id=\"{$returnEarlier->id}\"")) {
                    $earlierIndex = $idx;
                }
            }
        }

        $this->assertNotNull($laterIndex, 'Return created later should appear in datatables');
        $this->assertNotNull($earlierIndex, 'Return created earlier should appear in datatables');
        $this->assertLessThan($earlierIndex, $laterIndex, 'Latest return must appear before earlier return');

        // Create Damage 1 earlier
        $damageEarlier = PharmacyDamage::create([
            'damage_number' => 'DMG-' . uniqid(),
            'product_id' => $this->product1->id,
            'store_id' => $this->store->id,
            'qty_damaged' => 1,
            'unit_cost' => 15.00,
            'total_value' => 15.00,
            'damage_type' => 'expired',
            'damage_reason' => 'Earlier damage report.',
            'status' => 'pending',
            'discovered_date' => Carbon::today(),
            'created_by' => $this->user->id,
            'created_at' => Carbon::now()->subHours(2),
        ]);

        // Create Damage 2 later
        $damageLater = PharmacyDamage::create([
            'damage_number' => 'DMG-' . uniqid(),
            'product_id' => $this->product2->id,
            'store_id' => $this->store->id,
            'qty_damaged' => 2,
            'unit_cost' => 15.00,
            'total_value' => 30.00,
            'damage_type' => 'broken',
            'damage_reason' => 'Later damage report.',
            'status' => 'pending',
            'discovered_date' => Carbon::today(),
            'created_by' => $this->user->id,
            'created_at' => Carbon::now(),
        ]);

        $damagesRes = $this->actingAs($this->user)->getJson(route('pharmacy.damages.datatables'));
        $damagesRes->assertStatus(200);
        $damagesData = $damagesRes->json('data');

        $damageLaterIndex = null;
        $damageEarlierIndex = null;
        foreach ($damagesData as $idx => $row) {
            if (isset($row['actions'])) {
                if (str_contains($row['actions'], (string)$damageLater->id)) {
                    $damageLaterIndex = $idx;
                }
                if (str_contains($row['actions'], (string)$damageEarlier->id)) {
                    $damageEarlierIndex = $idx;
                }
            }
        }

        $this->assertNotNull($damageLaterIndex, 'Damage created later should appear in datatables');
        $this->assertNotNull($damageEarlierIndex, 'Damage created earlier should appear in datatables');
        $this->assertLessThan($damageEarlierIndex, $damageLaterIndex, 'Latest damage must appear before earlier damage');
    }
}
