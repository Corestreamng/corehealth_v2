<?php

namespace Tests\Feature\Pharmacy;

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Service;
use App\Models\ServiceBundleItem;
use App\Models\Staff;
use App\Models\User;
use Tests\TestCase;

class PharmacyProductSearchTest extends TestCase
{
    protected $pharmacist;

    protected $patient;

    protected $drug;

    protected $comboService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pharmacist = User::factory()->create(['status' => 1]);
        $clinic = Clinic::first() ?? Clinic::factory()->create();
        Staff::create([
            'user_id' => $this->pharmacist->id,
            'clinic_id' => $clinic->id,
            'staff_id' => 'PHARM-' . $this->pharmacist->id,
            'specialization' => 'Pharmacy',
        ]);

        $this->patient = Patient::factory()->create();

        $prodCatId = ProductCategory::first()->id ?? 1;
        $labCatId = (int) (appsettings('investigation_category_id', 2) ?: 2);
        $prefix = 'TEST_' . uniqid();

        // Create drug
        $this->drug = Product::create([
            'product_name' => "{$prefix} Genera Drug",
            'product_code' => "{$prefix}_GEN",
            'product_type' => 'drug',
            'category_id' => $prodCatId,
            'user_id' => $this->pharmacist->id,
            'status' => 1,
        ]);

        // Create combo service matching search term
        $this->comboService = Service::create([
            'service_name' => "{$prefix} Genera Package Combo",
            'service_code' => "{$prefix}_GPC",
            'category_id' => $labCatId,
            'is_combo' => 1,
            'user_id' => $this->pharmacist->id,
            'status' => 1,
        ]);

        // Attach drug to combo
        ServiceBundleItem::create([
            'parent_service_id' => $this->comboService->id,
            'item_type' => 'product',
            'item_id' => $this->drug->id,
            'qty' => 2,
        ]);
    }

    /**
     * Test pharmacy search-products returns 200 and matches both products and combos without 500 error.
     */
    public function test_search_products_with_matching_combo_returns_200()
    {
        $response = $this->actingAs($this->pharmacist)
            ->getJson("/pharmacy-workbench/search-products?term=Genera&patient_id={$this->patient->id}");

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);

        // Find product and combo in results
        $productMatches = array_filter($data, fn ($item) => ($item['is_combo'] ?? false) === false);
        $comboMatches = array_filter($data, fn ($item) => ($item['is_combo'] ?? false) === true);

        $this->assertNotEmpty($productMatches, 'Results should contain direct drug product');
        $this->assertNotEmpty($comboMatches, 'Results should contain matching combo service');

        $comboIds = array_column($comboMatches, 'id');
        $this->assertContains($this->comboService->id, $comboIds);
    }

    /**
     * Test pharmacy search-products with term less than 2 chars returns empty array.
     */
    public function test_search_products_short_term_returns_empty()
    {
        $response = $this->actingAs($this->pharmacist)
            ->getJson("/pharmacy-workbench/search-products?term=g&patient_id={$this->patient->id}");

        $response->assertStatus(200);
        $this->assertEquals([], $response->json());
    }
}
