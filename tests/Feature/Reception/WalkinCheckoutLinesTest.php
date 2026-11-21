<?php

namespace Tests\Feature\Reception;

use App\Models\Patient;
use App\Models\Product;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Walk-in sales checkout: each booked line carries its name, quantity and per-unit payable, so the
 * payment modal shows the items and quantities actually booked.
 */
class WalkinCheckoutLinesTest extends TestCase
{
    public function test_booked_lines_have_name_qty_and_unit_payable()
    {
        if (!Role::where('name', 'RECEPTIONIST')->exists()) {
            $this->markTestSkipped('RECEPTIONIST role missing.');
        }
        $product = Product::whereHas('price', fn ($q) => $q->where('current_sale_price', '>', 0))->first();
        $patient = Patient::query()->first();
        if (!$product || !$patient) {
            $this->markTestSkipped('Needs a priced product and a patient.');
        }
        $user = User::factory()->create(['status' => 1]);
        $user->assignRole('RECEPTIONIST');
        Sanctum::actingAs($user);

        $res = $this->postJson('/api/mobile/reception/book-walkin', [
            'patient_id' => $patient->id,
            'items' => [['id' => $product->id, 'type' => 'product', 'qty' => 3, 'quantity' => 3, 'name' => $product->product_name]],
        ]);
        $this->assertTrue($res->json('success') === true, $res->getContent());
        $line = $res->json('requests.0');
        $this->assertNotEmpty($line['name']);
        $this->assertEquals(3, $line['qty']);
        $this->assertEqualsWithDelta($line['payable_amount'] / 3, $line['unit_payable'], 0.01);

        $recent = $this->getJson("/api/mobile/reception/patient/{$patient->id}/recent-requests")->json('requests');
        $hit = collect($recent)->firstWhere('service_request_id', $line['service_request_id']);
        $this->assertNotNull($hit);
        $this->assertEquals(3, $hit['qty']);
    }
}
