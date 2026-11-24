<?php

namespace Tests\Feature\Llm;

use App\Models\Clinic;
use App\Models\Product;
use App\Services\OrderCatalogRegistryService;
use Tests\TestCase;

/**
 * The scribe's orders are grounded in the hospital catalog before the studios dispatch them. These rules protect
 * what lands in the pharmacy and referral queues (no network, no LLM).
 */
class ScribeOrderGroundingTest extends TestCase
{
    private function registry(): OrderCatalogRegistryService
    {
        return app(OrderCatalogRegistryService::class);
    }

    public function test_a_plain_drug_name_does_not_resolve_to_a_combination_product()
    {
        if (!Product::where('status', 1)->where('product_name', 'like', 'PARACETAMOL%')->exists()) {
            $this->markTestSkipped('No single-ingredient paracetamol in the catalog.');
        }
        foreach ([['Paracetamol', '1g TID'], ['Paracetamol', '500mg TID']] as [$name, $dose]) {
            $m = $this->registry()->matchCatalogItem($name, 'PRODUCT', null, $dose);
            $this->assertNotNull($m);
            $this->assertStringStartsWith('PARACETAMOL', strtoupper($m['item_name']), 'Paracetamol must not become a tramadol/codeine/other combination.');
            $this->assertStringNotContainsString('+', $m['item_name']);
        }
    }

    public function test_the_dose_text_picks_the_matching_strength_and_a_combination_when_asked_for()
    {
        $m = $this->registry()->matchCatalogItem('Paracetamol', 'PRODUCT', null, '500mg TID');
        $this->assertStringContainsString('500', $m['item_name']);
        if (Product::where('status', 1)->where('product_name', 'like', '%paracetamol%codeine%')->exists()) {
            $c = $this->registry()->matchCatalogItem('Paracetamol + Codeine', 'PRODUCT');
            $this->assertStringContainsStringIgnoringCase('codeine', $c['item_name']);
        }
    }

    public function test_a_referral_resolves_its_clinic_from_the_specialty()
    {
        $clinic = Clinic::where('name', 'like', '%Ophthalmology%')->first() ?? $this->markTestSkipped('No Ophthalmology clinic.');
        $orders = $this->registry()->groundOrders([
            ['order_type' => 'REFERRAL', 'item_name' => 'Ophthalmology Consultation'],
            ['order_type' => 'REFERRAL', 'item_name' => 'Zzzz unknown specialty'],
        ]);
        $this->assertSame($clinic->id, $orders[0]['target_clinic_id'], 'The referral endpoint requires a clinic; without one every scribe referral was rejected.');
        $this->assertNull($orders[1]['target_clinic_id'], 'No match is reported as none, never guessed.');
    }
}
