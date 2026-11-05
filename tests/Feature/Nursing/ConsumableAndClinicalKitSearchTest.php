<?php

namespace Tests\Feature\Nursing;

use App\Helpers\ClinicalSearchHelper;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceBundleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ConsumableAndClinicalKitSearchTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function test_clinical_search_helper_tokenizes_and_expands_synonyms()
    {
        $tokens = ClinicalSearchHelper::tokenize('Private ward- wond dressing');
        $this->assertContains('private', $tokens);
        $this->assertContains('ward', $tokens);
        $this->assertContains('wond', $tokens);
        $this->assertContains('dressing', $tokens);

        $expanded = ClinicalSearchHelper::expandTokens($tokens);
        $this->assertContains('wound', $expanded);
    }

    /** @test */
    public function test_nursing_search_services_finds_items_from_middle_of_name_and_typos()
    {
        $nurse = User::factory()->create(['status' => 1]);
        $this->actingAs($nurse);

        Service::create([
            'service_name' => 'Private ward - Wound dressing - Minor',
            'service_code' => 'TEST-PW-WOUND-' . uniqid(),
            'user_id' => $nurse->id,
            'category_id' => 8,
            'status' => 1,
        ]);

        // Test searching "wond" finds wound dressing services
        $response = $this->getJson('/nursing-workbench/search-services?term=wond');
        $response->assertStatus(200);

        $data = $response->json();
        $this->assertNotEmpty($data);

        $names = collect($data)->pluck('name')->implode(' | ');
        $this->assertStringContainsString('Wound dressing', $names);

        // Test multi-token with hyphen and typo: "Private ward- wond dressing"
        $response2 = $this->getJson('/nursing-workbench/search-services?term=' . urlencode('Private ward- wond dressing'));
        $response2->assertStatus(200);

        $data2 = $response2->json();
        $this->assertNotEmpty($data2);
        $names2 = collect($data2)->pluck('name')->implode(' | ');
        $this->assertStringContainsString('Private ward - Wound dressing', $names2);

        // Test reversed tokens: "ward wound"
        $response3 = $this->getJson('/nursing-workbench/search-services?term=' . urlencode('ward wound'));
        $response3->assertStatus(200);
        $data3 = $response3->json();
        $this->assertNotEmpty($data3);
        $names3 = collect($data3)->pluck('name')->implode(' | ');
        $this->assertStringContainsString('Private ward - Wound dressing', $names3);
    }

    /** @test */
    public function test_nursing_search_products_includes_consumable_services_with_service_tag()
    {
        $nurse = User::factory()->create(['status' => 1]);
        $this->actingAs($nurse);

        Service::create([
            'service_name' => 'Private ward - Wound dressing - Minor',
            'service_code' => 'TEST-PW-CON-' . uniqid(),
            'user_id' => $nurse->id,
            'category_id' => 8,
            'status' => 1,
        ]);

        // Under nurse consumable search, searching "wound" or "wond" should return consumable nursing services
        $response = $this->getJson('/nursing-workbench/search-products?term=wond');
        $response->assertStatus(200);

        $data = $response->json();
        $this->assertNotEmpty($data);

        $serviceItems = collect($data)->where('is_service', true);
        $this->assertNotEmpty($serviceItems);

        $firstService = $serviceItems->first();
        $this->assertTrue($firstService['is_service']);
        $this->assertStringContainsString('Wound', $firstService['name']);
    }

    /** @test */
    public function test_service_combos_clinical_kit_search_finds_by_bundle_items_and_tokens()
    {
        $user = User::factory()->create(['status' => 1]);
        $this->actingAs($user);

        $combo = Service::create([
            'service_name' => 'ANC Investigations combo (Return)',
            'service_code' => 'TEST-COMBO-' . uniqid(),
            'user_id' => $user->id,
            'category_id' => 1,
            'is_combo' => true,
            'status' => 1,
        ]);

        $childService = Service::create([
            'service_name' => 'PCV Blood Test',
            'service_code' => 'TEST-PCV-' . uniqid(),
            'user_id' => $user->id,
            'category_id' => 2,
            'status' => 1,
        ]);

        ServiceBundleItem::create([
            'parent_service_id' => $combo->id,
            'item_id' => $childService->id,
            'item_type' => 'service',
            'qty' => 1,
        ]);

        // Search by bundle item contained inside combo: "PCV"
        $response = $this->getJson('/service-combos/search?term=PCV');
        $response->assertStatus(200);

        $data = $response->json();
        $this->assertNotEmpty($data);

        $names = collect($data)->pluck('service_name')->implode(' | ');
        $this->assertStringContainsString('ANC Investigations combo', $names);

        // Search by multi-token non-contiguous words: "anc return"
        $response2 = $this->getJson('/service-combos/search?term=' . urlencode('anc return'));
        $response2->assertStatus(200);

        $data2 = $response2->json();
        $this->assertNotEmpty($data2);
        $names2 = collect($data2)->pluck('service_name')->implode(' | ');
        $this->assertStringContainsString('ANC Investigations combo', $names2);
    }

    /** @test */
    public function test_live_search_products_finds_items_with_reordered_and_middle_tokens()
    {
        $user = User::factory()->create(['status' => 1]);
        $this->actingAs($user);

        Product::create([
            'product_name' => 'LATEX GLOVES (STORE)',
            'product_code' => 'TEST-GLOVES-' . uniqid(),
            'product_type' => 'consumable',
            'category_id' => 1,
            'user_id' => $user->id,
            'status' => 1,
        ]);

        // "gloves store" matching "LATEX GLOVES (STORE)"
        $response = $this->getJson('/live-search-products?term=' . urlencode('gloves store'));
        $response->assertStatus(200);

        $data = $response->json();
        $this->assertNotEmpty($data);

        $names = collect($data)->pluck('product_name')->implode(' | ');
        $this->assertStringContainsString('GLOVES', $names);
    }
}
