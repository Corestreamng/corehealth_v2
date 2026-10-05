<?php

namespace Tests\Feature\Nhmis;

use App\Models\ImmunizationRecord;
use App\Models\NhmisMonthlyReport;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockBatch;
use App\Models\Store;
use App\Models\User;
use App\Services\Nhmis\NhmisDataAggregatorService;
use Tests\TestCase;

class CommunityOutreachImmunizationTest extends TestCase
{
    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::where('is_admin', '>', 0)->first() ?? User::factory()->create(['status' => 1]);
        $this->actingAs($this->staff);
    }

    /** @test */
    public function test_nursing_workbench_can_save_rapid_outreach_tallies_without_patient_files(): void
    {
        // Broad demographics matching WHO, DHIS2, GHIS, and NHMIS
        $payload = [
            'session_date' => '2026-07-16',
            'location_settlement' => 'Sabon Gari Community Market',
            'strategy' => 'Mobile Outreach Team',
            'notes' => 'July 2026 EPI Outreach Drive',
            'tallies' => [
                // Infants < 1y (0-11m)
                [
                    'vaccine_name' => 'OPV',
                    'dose' => 'Dose 1',
                    'dose_number' => 1,
                    'age_group' => '<1y',
                    'target_group' => 'infants',
                    'gender' => 'All',
                    'headcount' => 25,
                    'batch_number' => 'OPV-OUT-01',
                ],
                [
                    'vaccine_name' => 'Pentavalent',
                    'dose' => 'Dose 1',
                    'dose_number' => 1,
                    'age_group' => '<1y',
                    'target_group' => 'infants',
                    'gender' => 'All',
                    'headcount' => 25,
                ],
                // Children ≥ 1y (12-59m)
                [
                    'vaccine_name' => 'Measles',
                    'dose' => 'Dose 2 (15 Months)',
                    'dose_number' => 2,
                    'age_group' => '12-59m',
                    'target_group' => 'children_1_4',
                    'gender' => 'All',
                    'headcount' => 15,
                ],
                // Adolescent Girls (HPV 9-14y)
                [
                    'vaccine_name' => 'HPV',
                    'dose' => 'Dose 1',
                    'dose_number' => 1,
                    'age_group' => '9-14y',
                    'target_group' => 'adolescent_girls',
                    'gender' => 'Female',
                    'headcount' => 30,
                ],
                // Pregnant Women (Maternal Td)
                [
                    'vaccine_name' => 'Td',
                    'dose' => 'Td 1',
                    'dose_number' => 1,
                    'age_group' => '15-49y',
                    'target_group' => 'pregnant_women',
                    'gender' => 'Female',
                    'headcount' => 12,
                ],
                // Non-Pregnant Women of Reproductive Age (WRA 15-49y)
                [
                    'vaccine_name' => 'Td',
                    'dose' => 'Td 1 (WRA)',
                    'dose_number' => 1,
                    'age_group' => '15-49y',
                    'target_group' => 'non_pregnant_women',
                    'gender' => 'Female',
                    'headcount' => 8,
                ],
                // Adult Males 15-49y (Tetanus trauma / occupational)
                [
                    'vaccine_name' => 'Td',
                    'dose' => 'Td 1 (Adult Male)',
                    'dose_number' => 1,
                    'age_group' => '15-49y',
                    'target_group' => 'adult_males',
                    'gender' => 'Male',
                    'headcount' => 5,
                ],
            ],
        ];

        $res = $this->postJson(route('nursing-workbench.outreach-tally'), $payload);
        $res->assertStatus(200);
        $res->assertJsonPath('success', true);
        $res->assertJsonPath('total_doses', 120);
        $res->assertJsonPath('tallies_count', 7);

        $sessionId = $res->json('outreach_session_id');
        $this->assertNotEmpty($sessionId);

        // Verify database records were created with patient_id = NULL
        $createdRecords = ImmunizationRecord::where('outreach_session_id', $sessionId)->get();
        $this->assertCount(7, $createdRecords);
        foreach ($createdRecords as $record) {
            $this->assertNull($record->patient_id, 'Outreach record must not require patient EMR accounts');
            $this->assertEquals('outreach', $record->session_type);
            $this->assertEquals('Sabon Gari Community Market', $record->location_settlement);
        }
    }

    /** @test */
    public function test_maternity_workbench_can_save_rapid_outreach_tallies(): void
    {
        $payload = [
            'session_date' => '2026-07-18',
            'location_settlement' => 'Angwan Rogo Primary School',
            'strategy' => 'School-Based Drive',
            'notes' => 'Maternity unit community drive',
            'tallies' => [
                [
                    'vaccine_name' => 'HPV',
                    'dose' => 'Dose 1',
                    'dose_number' => 1,
                    'age_group' => '9-14y',
                    'target_group' => 'adolescent_girls',
                    'gender' => 'Female',
                    'headcount' => 45,
                ],
                [
                    'vaccine_name' => 'Td',
                    'dose' => 'Td 2',
                    'dose_number' => 2,
                    'age_group' => '15-49y',
                    'target_group' => 'pregnant_women',
                    'gender' => 'Female',
                    'headcount' => 14,
                ],
            ],
        ];

        $res = $this->postJson(route('maternity-workbench.outreach-tally'), $payload);
        $res->assertStatus(200);
        $res->assertJsonPath('success', true);
        $res->assertJsonPath('total_doses', 59);
        $res->assertJsonPath('tallies_count', 2);
    }

    /** @test */
    public function test_outreach_reports_and_session_details_endpoints_work_for_both_workbenches(): void
    {
        // 1. Seed a session
        $this->postJson(route('nursing-workbench.outreach-tally'), [
            'session_date' => '2026-07-20',
            'location_settlement' => 'Kupari Clinic Post',
            'strategy' => 'Community Fixed Post',
            'notes' => 'EPI Supervision Drive',
            'tallies' => [
                [
                    'vaccine_name' => 'BCG',
                    'dose' => 'Birth Dose',
                    'dose_number' => 1,
                    'age_group' => '<1y',
                    'target_group' => 'infants',
                    'gender' => 'All',
                    'headcount' => 18,
                ],
            ],
        ]);

        // 2. Test Nursing report endpoint
        $resNursing = $this->getJson(route('nursing-workbench.outreach-reports', ['from' => '2026-07-01', 'to' => '2026-07-31']));
        $resNursing->assertStatus(200);
        $resNursing->assertJsonPath('success', true);
        $this->assertGreaterThanOrEqual(18, $resNursing->json('kpis.total_doses'));
        $this->assertNotEmpty($resNursing->json('sessions'));

        $sessionId = $resNursing->json('sessions.0.session_id');

        // 3. Test Session Details endpoint
        $resDetails = $this->getJson(route('nursing-workbench.outreach-session.details', ['sessionId' => $sessionId]));
        $resDetails->assertStatus(200);
        $resDetails->assertJsonPath('success', true);
        $this->assertNotEmpty($resDetails->json('records'));

        // 4. Test Maternity report endpoint
        $resMaternity = $this->getJson(route('maternity-workbench.outreach-reports', ['from' => '2026-07-01', 'to' => '2026-07-31']));
        $resMaternity->assertStatus(200);
        $resMaternity->assertJsonPath('success', true);

        // 5. Test Print Endpoint
        $resPrint = $this->get(route('nursing-workbench.outreach-report.print', ['from' => '2026-07-01', 'to' => '2026-07-31']));
        $resPrint->assertStatus(200);
        $resPrint->assertSee('COMMUNITY OUTREACH IMMUNIZATION SUMMARY');
    }

    /** @test */
    public function test_nhmis_monthly_summary_aggregates_outreach_tallies_correctly(): void
    {
        // Create an outreach session for July 2026:
        // 20 infants <1y for OPV 1
        // 10 pregnant women for Td 1
        // 15 adolescent girls for HPV 1
        $this->postJson(route('nursing-workbench.outreach-tally'), [
            'session_date' => '2026-07-14',
            'location_settlement' => 'Central Community Drive',
            'tallies' => [
                [
                    'vaccine_name' => 'OPV',
                    'dose' => 'Dose 1',
                    'dose_number' => 1,
                    'age_group' => '<1y',
                    'target_group' => 'infants',
                    'gender' => 'All',
                    'headcount' => 20,
                ],
                [
                    'vaccine_name' => 'Td',
                    'dose' => 'Td 1',
                    'dose_number' => 1,
                    'age_group' => '15-49y',
                    'target_group' => 'pregnant_women',
                    'gender' => 'Female',
                    'headcount' => 10,
                ],
                [
                    'vaccine_name' => 'HPV',
                    'dose' => 'Dose 1',
                    'dose_number' => 1,
                    'age_group' => '9-14y',
                    'target_group' => 'adolescent_girls',
                    'gender' => 'Female',
                    'headcount' => 15,
                ],
            ],
        ]);

        $report = NhmisMonthlyReport::firstOrCreate(
            ['year' => 2026, 'month' => 7],
            ['status' => 'draft', 'created_by' => $this->staff->id]
        );

        $aggregator = app(NhmisDataAggregatorService::class);
        $values = $aggregator->compileReport($report);

        // Row 68 (OPV 1): outreach_lt_1y must include the +20
        $this->assertGreaterThanOrEqual(20, $values['row_68:outreach_lt_1y']);

        // Row 63 (Pregnant Women Td 1): must include the +10
        $this->assertGreaterThanOrEqual(10, $values['row_63:td1']);

        // Row 87 (HPV ≥1y): outreach_ge_1y must include the +15
        $this->assertGreaterThanOrEqual(15, $values['row_87:outreach_ge_1y']);
    }

    /** @test */
    public function test_outreach_handles_stock_sources_and_cold_chain_matrix(): void
    {
        // 1. Record session with Hospital Store and cold chain details
        $resHosp = $this->postJson(route('nursing-workbench.outreach-tally'), [
            'session_date' => '2026-07-20',
            'location_settlement' => 'Ungwan Dosa Outreach Centre',
            'stock_source' => 'hospital_store',
            'cold_chain_carrier' => 'Cold Box #4 (Giostyle)',
            'vvm_stage' => 'Stage 1',
            'doses_wasted' => 2,
            'tallies' => [
                [
                    'vaccine_name' => 'Pentavalent',
                    'dose' => 'Dose 1',
                    'dose_number' => 1,
                    'age_group' => '<1y',
                    'target_group' => 'infants',
                    'gender' => 'All',
                    'headcount' => 30,
                    'doses_wasted' => 1,
                ],
                [
                    'vaccine_name' => 'PCV',
                    'dose' => 'Dose 1',
                    'dose_number' => 1,
                    'age_group' => '<1y',
                    'target_group' => 'infants',
                    'gender' => 'All',
                    'headcount' => 30,
                    'doses_wasted' => 1,
                ],
            ],
        ]);

        $resHosp->assertStatus(200);
        $resHosp->assertJsonPath('success', true);
        $resHosp->assertJsonPath('total_doses', 60);
        $resHosp->assertJsonPath('stock_source', 'hospital_store');

        // 2. Record session with Partner / Donor Supply
        $resDonor = $this->postJson(route('nursing-workbench.outreach-tally'), [
            'session_date' => '2026-07-22',
            'location_settlement' => 'Kupari Primary School',
            'stock_source' => 'donor_partner',
            'cold_chain_carrier' => 'UNICEF Vaccine Carrier #2',
            'vvm_stage' => 'Stage 2',
            'tallies' => [
                [
                    'vaccine_name' => 'HPV',
                    'dose' => 'Dose 1',
                    'dose_number' => 1,
                    'age_group' => '9-14y',
                    'target_group' => 'adolescent_girls',
                    'gender' => 'Female',
                    'headcount' => 45,
                ],
            ],
        ]);

        $resDonor->assertStatus(200);
        $resDonor->assertJsonPath('stock_source', 'donor_partner');

        // 3. Query reports with stock source filter
        $resFiltered = $this->getJson(route('nursing-workbench.outreach-reports', [
            'from' => '2026-07-01',
            'to' => '2026-07-31',
            'stock_source' => 'hospital_store',
        ]));

        $resFiltered->assertStatus(200);
        $resFiltered->assertJsonPath('success', true);
        $this->assertGreaterThanOrEqual(60, $resFiltered->json('kpis.stock_sources.hospital_store'));
        $this->assertNotEmpty($resFiltered->json('antigen_matrix'));

        // 4. Verify detail endpoint returns stock source and cold chain
        $sessionId = $resHosp->json('outreach_session_id');
        $resDetails = $this->getJson(route('nursing-workbench.outreach-session.details', ['sessionId' => $sessionId]));
        $resDetails->assertStatus(200);
        $this->assertEquals('hospital_store', $resDetails->json('records.0.stock_source'));
        $this->assertEquals('Cold Box #4 (Giostyle)', $resDetails->json('records.0.cold_chain_carrier'));
        $this->assertEquals('Stage 1', $resDetails->json('records.0.vvm_stage'));
    }

    /** @test */
    public function test_outreach_alias_and_root_endpoints_resolve_successfully(): void
    {
        $payload = [
            'session_date' => '2026-07-25',
            'location_settlement' => 'Central Market Square',
            'strategy' => 'Fixed Outreach Post',
            'stock_source' => 'govt_epi',
            'tallies' => [
                [
                    'vaccine_name' => 'BCG',
                    'dose' => 'Birth Dose',
                    'dose_number' => 1,
                    'age_group' => '<1y',
                    'target_group' => 'infants',
                    'gender' => 'All',
                    'headcount' => 10,
                ],
            ],
        ];

        // 1. Direct root POST /save-outreach-tally (reproduces user scenario)
        $resRoot = $this->postJson('/save-outreach-tally', $payload);
        $resRoot->assertStatus(200);
        $resRoot->assertJsonPath('success', true);

        // 2. Prefixed alias POST /nursing-workbench/save-outreach-tally
        $resNursingAlias = $this->postJson('/nursing-workbench/save-outreach-tally', $payload);
        $resNursingAlias->assertStatus(200);
        $resNursingAlias->assertJsonPath('success', true);

        // 3. Prefixed alias POST /maternity-workbench/save-outreach-tally
        $resMaternityAlias = $this->postJson('/maternity-workbench/save-outreach-tally', $payload);
        $resMaternityAlias->assertStatus(200);
        $resMaternityAlias->assertJsonPath('success', true);

        // 4. Direct root GET /outreach-sessions-report
        $resReportRoot = $this->getJson('/outreach-sessions-report');
        $resReportRoot->assertStatus(200);
        $resReportRoot->assertJsonPath('success', true);
    }

    /** @test */
    public function test_hospital_stock_source_inventory_query_and_batch_deduction_ledger(): void
    {
        // 1. Arrange store and vaccine product
        $store = Store::first() ?? Store::create([
            'store_name' => 'Main Cold Chain Pharmacy',
            'is_active' => true,
        ]);

        $product = Product::where('status', 1)
            ->where(function ($q) {
                $q->where('product_name', 'like', '%vaccine%')
                  ->orWhere('product_name', 'like', '%bcg%')
                  ->orWhere('product_name', 'like', '%opv%');
            })->first();

        if (!$product) {
            $product = Product::create([
                'product_name' => 'BCG Vaccine 20-Dose',
                'product_code' => 'VAC-BCG-TEST',
                'status' => 1,
            ]);
        }

        // Create an active test batch for this product in this store
        $batch = StockBatch::create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'batch_name' => 'Test Batch Outreach',
            'batch_number' => 'TEST-OUT-BTH-' . uniqid(),
            'initial_qty' => 100,
            'current_qty' => 100,
            'sold_qty' => 0,
            'cost_price' => 250.00,
            'expiry_date' => now()->addMonths(12)->toDateString(),
            'received_date' => now()->toDateString(),
            'is_active' => true,
        ]);

        // 2. Query outreach store inventory endpoint
        $resInv = $this->getJson(route('nursing-workbench.outreach-store-inventory', ['store_id' => $store->id]));
        $resInv->assertStatus(200);
        $resInv->assertJsonPath('success', true);
        $resInv->assertJsonPath('store.id', $store->id);
        $this->assertNotEmpty($resInv->json('products'));

        // Also verify root alias endpoint /outreach-store-inventory
        $resInvRoot = $this->getJson('/outreach-store-inventory?store_id=' . $store->id);
        $resInvRoot->assertStatus(200);
        $resInvRoot->assertJsonPath('success', true);

        // 3. Save outreach session using Hospital Store with explicit batch and deduction
        $payload = [
            'session_date' => '2026-07-28',
            'location_settlement' => 'Angwan Shanu Clinic Outreach',
            'strategy' => 'Mobile Outreach Team',
            'stock_source' => 'hospital_store',
            'store_id' => $store->id,
            'auto_deduct_stock' => true,
            'cold_chain_carrier' => 'Cold Box #3',
            'vvm_stage' => 'Stage 1',
            'doses_wasted' => 2,
            'tallies' => [
                [
                    'vaccine_name' => $product->product_name,
                    'dose' => 'Dose 1',
                    'dose_number' => 1,
                    'age_group' => '<1y',
                    'target_group' => 'infants',
                    'gender' => 'All',
                    'headcount' => 10,
                    'doses_wasted' => 1,
                    'product_id' => $product->id,
                    'batch_id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'expiry_date' => $batch->expiry_date->toDateString(),
                    'auto_deduct' => true,
                ],
                [
                    'vaccine_name' => 'Free EPI Buffer Antigen',
                    'dose' => 'Dose 0',
                    'dose_number' => 0,
                    'age_group' => '<1y',
                    'target_group' => 'infants',
                    'gender' => 'All',
                    'headcount' => 8,
                    'doses_wasted' => 0,
                    'product_id' => null,
                    'auto_deduct' => false, // Per-row override: skip hospital deduction
                ],
            ],
        ];

        $resSave = $this->postJson(route('nursing-workbench.outreach-tally'), $payload);
        $resSave->assertStatus(200);
        $resSave->assertJsonPath('success', true);
        $resSave->assertJsonPath('total_doses', 18);
        $resSave->assertJsonPath('tallies_count', 2);
        $resSave->assertJsonPath('stock_source', 'hospital_store');

        $sessionId = $resSave->json('outreach_session_id');
        $records = ImmunizationRecord::where('outreach_session_id', $sessionId)->get();
        $this->assertCount(2, $records);

        $hospitalItem = $records->firstWhere('vaccine_name', $product->product_name);
        $this->assertNotNull($hospitalItem);
        $this->assertEquals($product->id, $hospitalItem->product_id);
        $this->assertEquals($batch->batch_number, $hospitalItem->batch_number);
        $this->assertEquals($store->id, $hospitalItem->dispensed_from_store_id);
        $this->assertTrue((bool) $hospitalItem->auto_deduct_stock);

        $freeItem = $records->firstWhere('vaccine_name', 'Free EPI Buffer Antigen');
        $this->assertNotNull($freeItem);
        $this->assertNull($freeItem->product_id);
        $this->assertFalse((bool) $freeItem->auto_deduct_stock);
    }

    /** @test */
    public function test_outreach_store_inventory_allows_unrestricted_search_of_any_product(): void
    {
        $store = Store::first() ?? Store::create([
            'store_name' => 'General Store Alpha',
            'store_description' => 'Test Store for product search',
            'status' => 1,
        ]);

        $category = ProductCategory::first() ?? ProductCategory::create([
            'category_name' => 'Medical Consumables',
            'user_id' => $this->staff->id,
            'status' => 1,
        ]);

        // Create an arbitrary non-vaccine medical product to verify unrestricted search
        $needleCode = 'PRD-' . uniqid();
        $needleProduct = Product::create([
            'user_id' => $this->staff->id,
            'category_id' => $category->id,
            'product_name' => 'Unique Outreach Medical Consumable Item ' . uniqid(),
            'product_code' => $needleCode,
            'status' => 1,
            'current_quantity' => 200,
        ]);

        // Test searching by product code via nursing workbench
        $resNursing = $this->getJson(route('nursing-workbench.outreach-store-inventory', [
            'store_id' => $store->id,
            'q' => $needleCode,
        ]));

        $resNursing->assertStatus(200);
        $resNursing->assertJsonPath('success', true);
        $productsNursing = $resNursing->json('products');
        $this->assertIsArray($productsNursing);
        $matched = collect($productsNursing)->firstWhere('id', $needleProduct->id);
        $this->assertNotNull($matched, 'Should find any active product by code, unrestricted to vaccine keywords');
        $this->assertEquals($needleProduct->product_name, $matched['name']);

        // Test searching via maternity workbench
        $resMaternity = $this->getJson(route('maternity-workbench.outreach-store-inventory', [
            'store_id' => $store->id,
            'term' => substr($needleProduct->product_name, 0, 15),
        ]));

        $resMaternity->assertStatus(200);
        $resMaternity->assertJsonPath('success', true);
        $productsMaternity = $resMaternity->json('products');
        $this->assertIsArray($productsMaternity);
        $matchedMaternity = collect($productsMaternity)->firstWhere('id', $needleProduct->id);
        $this->assertNotNull($matchedMaternity, 'Should find any active product via maternity endpoint');
    }

    /** @test */
    public function test_outreach_session_with_hospital_store_rejects_insufficient_stock_and_accepts_when_deficit_skipped(): void
    {
        $store = Store::first() ?? Store::create([
            'store_name' => 'Cold Chain Vaccine Store Beta',
            'status' => 1,
        ]);

        $category = ProductCategory::first() ?? ProductCategory::create([
            'category_name' => 'Vaccines & Biologicals',
            'user_id' => $this->staff->id,
            'status' => 1,
        ]);

        // Product with small stock of 5
        $product = Product::create([
            'user_id' => $this->staff->id,
            'category_id' => $category->id,
            'product_name' => 'Yellow Fever Vaccine 10-dose Vial ' . uniqid(),
            'product_code' => 'YF-' . uniqid(),
            'status' => 1,
            'current_quantity' => 5,
        ]);

        StockBatch::create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'batch_name' => 'Deficit Batch YF',
            'batch_number' => 'BATCH-YF-DEFICIT-01',
            'current_qty' => 5,
            'initial_qty' => 10,
            'sold_qty' => 0,
            'cost_price' => 300.00,
            'expiry_date' => now()->addYear()->toDateString(),
            'received_date' => now()->toDateString(),
            'status' => 1,
            'is_active' => true,
        ]);

        // Requesting 20 doses (headcount 18 + wasted 2) with auto_deduct enabled -> Deficit of 15!
        $deficitPayload = [
            'session_date' => '2026-07-22',
            'location_settlement' => 'High-Density Rural Ward',
            'strategy' => 'Mobile Outreach',
            'stock_source' => 'hospital_store',
            'store_id' => $store->id,
            'auto_deduct_stock' => 1,
            'cold_chain_carrier' => 'Cold Box Delta',
            'vvm_stage' => 'Stage 1',
            'tallies' => [
                [
                    'vaccine_name' => 'Yellow Fever',
                    'dose' => 'Dose 1',
                    'dose_number' => 1,
                    'age_group' => '<1y',
                    'target_group' => 'infants',
                    'gender' => 'All',
                    'headcount' => 18,
                    'doses_wasted' => 2,
                    'product_id' => $product->id,
                    'auto_deduct' => 1,
                ],
            ],
        ];

        // 1. Should strictly reject with 422 and requires_resolution
        $resReject = $this->postJson(route('nursing-workbench.outreach-tally'), $deficitPayload);
        $resReject->assertStatus(422);
        $resReject->assertJsonPath('requires_resolution', true);
        $this->assertNotEmpty($resReject->json('deficit_items'));
        $this->assertEquals(15, $resReject->json('deficit_items.0.deficit'));

        // 2. Resolve deficit by flipping auto_deduct to 0 (Option B 1-click Skip Deduct)
        $resolvedPayload = $deficitPayload;
        $resolvedPayload['tallies'][0]['auto_deduct'] = 0;

        $resAccept = $this->postJson(route('nursing-workbench.outreach-tally'), $resolvedPayload);
        $resAccept->assertStatus(200);
        $resAccept->assertJsonPath('success', true);
        $this->assertEquals(1, $resAccept->json('skipped_items_count'));

        // 3. Confirm tally was recorded for DHIS2/NHMIS reporting without hospital stock deduction
        $rec = ImmunizationRecord::where('vaccine_name', 'Yellow Fever')
            ->where('outreach_session_id', $resAccept->json('outreach_session_id'))
            ->first();
        $this->assertNotNull($rec);
        $this->assertEquals(18, $rec->headcount);
        $this->assertFalse((bool) $rec->auto_deduct_stock);
    }
}
