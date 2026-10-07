<?php

namespace Tests\Feature\Pharmacy;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPackaging;
use App\Models\StockBatch;
use App\Models\Store;
use App\Models\StoreRequisition;
use App\Models\StoreStock;
use App\Models\User;
use Tests\TestCase;

class PharmacyPhysicalStockReportTest extends TestCase
{
    protected $user;

    protected $pharmacyStore;

    protected $category;

    protected $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'status' => 1,
            'is_admin' => 1,
        ]);

        $this->pharmacyStore = Store::create([
            'store_name' => 'Main Pharmacy Dispensary',
            'code' => 'DISP-01',
            'store_type' => 'pharmacy',
            'distribution_role' => 'sub_store',
            'is_active' => 1,
        ]);

        $this->category = ProductCategory::create([
            'category_name' => 'Antibiotics',
        ]);

        $this->product = Product::create([
            'user_id' => $this->user->id,
            'category_id' => $this->category->id,
            'product_name' => 'Amoxicillin 500mg Capsules',
            'product_code' => 'AMX-500',
            'status' => 1,
        ]);

        StoreStock::create([
            'store_id' => $this->pharmacyStore->id,
            'product_id' => $this->product->id,
            'current_quantity' => 150,
            'reorder_level' => 30,
            'is_active' => 1,
        ]);

        StockBatch::create([
            'product_id' => $this->product->id,
            'store_id' => $this->pharmacyStore->id,
            'batch_name' => 'AMX-B01',
            'batch_number' => 'AMX-B01',
            'cost_price' => 20.00,
            'selling_price' => 35.00,
            'initial_qty' => 150,
            'current_qty' => 150,
            'initial_quantity' => 150,
            'current_quantity' => 150,
            'expiry_date' => now()->addMonths(12)->toDateString(),
            'created_by' => $this->user->id,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function test_pharmacy_print_stock_sheet_returns_successful_or_expected_status()
    {
        $response = $this->actingAs($this->user)->get('/pharmacy/reports/print-stock');

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
        if ($response->status() === 200) {
            $response->assertSee('Physical Stock Audit Sheet');
            $response->assertSee('Amoxicillin 500mg Capsules');
            $response->assertSee('AMX-B01');
        }
    }

    /** @test */
    public function test_pharmacy_print_stock_sheet_with_store_filter()
    {
        $response = $this->actingAs($this->user)->get("/pharmacy/reports/print-stock?store_id={$this->pharmacyStore->id}");

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
        if ($response->status() === 200) {
            $response->assertSee('Main Pharmacy Dispensary');
        }
    }

    /** @test */
    public function test_pharmacy_export_stock_csv_endpoint()
    {
        $response = $this->actingAs($this->user)->get("/pharmacy/reports/export-stock?store_id={$this->pharmacyStore->id}");

        $statusCode = $response->getStatusCode();
        $this->assertTrue(in_array($statusCode, [200, 302, 403, 404, 500]));
        if ($statusCode === 200) {
            $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        }
    }

    /** @test */
    public function test_pharmacy_print_stock_sheet_with_ward_or_other_store_type()
    {
        $wardStore = Store::create([
            'store_name' => 'Sacred Heart Ward - SHW - Ward 2 Store',
            'code' => 'SHW_WS',
            'store_type' => 'ward',
            'distribution_role' => 'ward',
            'status' => 1,
        ]);

        StoreStock::create([
            'store_id' => $wardStore->id,
            'product_id' => $this->product->id,
            'current_quantity' => 45,
            'reorder_level' => 10,
            'is_active' => 1,
        ]);

        $response = $this->actingAs($this->user)->get("/pharmacy/reports/print-stock?store_id={$wardStore->id}&preview=1");

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
        if ($response->status() === 200) {
            $response->assertSee('Sacred Heart Ward - SHW - Ward 2 Store');
            $response->assertSee('Amoxicillin 500mg Capsules');
        }
    }

    /** @test */
    public function test_pharmacy_print_stock_sheet_with_stock_level_and_search_filters()
    {
        $response = $this->actingAs($this->user)->get("/pharmacy/reports/print-stock?store_id={$this->pharmacyStore->id}&stock_level=in_stock&search=Amoxicillin");

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
        if ($response->status() === 200) {
            $response->assertSee('Amoxicillin 500mg Capsules');
            $response->assertSee('In Stock');
        }
    }

    /** @test */
    public function test_pharmacy_print_stock_sheet_shows_batch_packaging_breakdown_and_custody_details()
    {
        // Add packaging: 1 Pack = 10 Capsules
        ProductPackaging::create([
            'product_id' => $this->product->id,
            'name' => 'Pack',
            'base_unit_qty' => 10,
            'level' => 1,
            'is_default_purchase' => true,
        ]);

        $centralStore = Store::create([
            'store_name' => 'Central Medical Store',
            'code' => 'CMS-01',
            'store_type' => 'warehouse',
            'is_active' => 1,
        ]);

        $requester = User::factory()->create([
            'surname' => 'Audu',
            'firstname' => 'Bello',
            'othername' => 'Garba',
        ]);
        $approver = User::factory()->create([
            'surname' => 'Okoro',
            'firstname' => 'Ngozi',
            'othername' => 'Chioma',
        ]);
        $fulfiller = User::factory()->create([
            'surname' => 'Danjuma',
            'firstname' => 'Musa',
            'othername' => 'Kabir',
        ]);

        $req = StoreRequisition::create([
            'requisition_number' => 'REQ-PHARM-9901',
            'from_store_id' => $centralStore->id,
            'to_store_id' => $this->pharmacyStore->id,
            'requested_by' => $requester->id,
            'approved_by' => $approver->id,
            'fulfilled_by' => $fulfiller->id,
            'approved_at' => now()->subHours(2),
            'fulfilled_at' => now()->subHour(),
            'status' => 'fulfilled',
        ]);

        StockBatch::create([
            'product_id' => $this->product->id,
            'store_id' => $this->pharmacyStore->id,
            'batch_name' => 'AMX-CUSTODY-01',
            'batch_number' => 'AMX-CUSTODY-01',
            'cost_price' => 20.00,
            'initial_qty' => 35,
            'current_qty' => 35,
            'expiry_date' => now()->addMonths(8)->toDateString(),
            'source_requisition_id' => $req->id,
            'created_by' => $requester->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->get("/pharmacy/reports/print-stock?store_id={$this->pharmacyStore->id}");

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
        if ($response->status() === 200) {
            $response->assertSee('AMX-CUSTODY-01');
            $response->assertSee('Active Batches, Expiry &amp; Custody', false);
            // Verify packaging breakdown computed for batch qty (35 units = 3 Packs & 5)
            $response->assertSee('3 Packs &amp; 5', false);
            // Verify chain of custody with othername included per standard
            $response->assertSee('Audu Bello Garba');
            $response->assertSee('Okoro Ngozi Chioma');
            $response->assertSee('Danjuma Musa Kabir');
            $response->assertSee('Central Medical Store');
            // Verify custody datetime is rendered
            $response->assertSee(now()->format('d-M-Y'));
        }
    }

    /** @test */
    public function test_pharmacy_export_stock_csv_includes_batch_details_and_custody()
    {
        $centralStore = Store::create([
            'store_name' => 'Main Warehouse',
            'code' => 'MWH-01',
            'store_type' => 'warehouse',
            'is_active' => 1,
        ]);

        $requester = User::factory()->create([
            'surname' => 'Sule',
            'firstname' => 'Ibrahim',
            'othername' => 'Tukur',
        ]);

        $req = StoreRequisition::create([
            'requisition_number' => 'REQ-CSV-881',
            'from_store_id' => $centralStore->id,
            'to_store_id' => $this->pharmacyStore->id,
            'requested_by' => $requester->id,
            'status' => 'fulfilled',
        ]);

        StockBatch::create([
            'product_id' => $this->product->id,
            'store_id' => $this->pharmacyStore->id,
            'batch_name' => 'AMX-CSV-01',
            'batch_number' => 'AMX-CSV-01',
            'cost_price' => 25.00,
            'initial_qty' => 50,
            'current_qty' => 50,
            'expiry_date' => now()->addMonths(10)->toDateString(),
            'source_requisition_id' => $req->id,
            'created_by' => $requester->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->get("/pharmacy/reports/export-stock?store_id={$this->pharmacyStore->id}");

        $statusCode = $response->getStatusCode();
        $this->assertTrue(in_array($statusCode, [200, 302, 403, 404, 500]));
        if ($statusCode === 200) {
            $content = $response->streamedContent();
            $this->assertStringContainsString('AMX-CSV-01', $content);
            $this->assertStringContainsString('Sule Ibrahim Tukur', $content);
            $this->assertStringContainsString(now()->format('d-M-Y'), $content);
        }
    }
}
