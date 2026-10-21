<?php

namespace Tests\Feature\Billing;

use App\Models\Patient;
use App\Models\Payment;
use App\Models\Price;
use App\Models\Product;
use App\Models\ProductOrServiceRequest;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\User;
use Tests\TestCase;

class MyTransactionsEnhancedTest extends TestCase
{
    protected User $user;

    protected Patient $patient;

    protected Service $service;

    protected Product $product;

    protected Payment $servicePayment;

    protected Payment $productPayment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $this->user = User::first() ?? User::factory()->create(['status' => 1]);

        $patientUser = User::factory()->create(['status' => 1]);
        $this->patient = Patient::create([
            'user_id' => $patientUser->id,
            'file_no' => 'TX_' . uniqid(),
            'status' => 1,
        ]);

        $this->service = Service::create([
            'user_id' => $this->user->id,
            'service_name' => 'Wound Dressing ' . uniqid(),
            'category_id' => 1,
            'status' => 1,
        ]);

        ServicePrice::create([
            'user_id' => $this->user->id,
            'service_id' => $this->service->id,
            'sale_price' => 3000,
        ]);

        $this->product = Product::create([
            'user_id' => $this->user->id,
            'product_name' => 'Amoxicillin 500mg ' . uniqid(),
            'category_id' => 1,
            'status' => 1,
        ]);

        Price::create([
            'product_id' => $this->product->id,
            'initial_sale_price' => 2000,
            'current_sale_price' => 2000,
        ]);

        // Create a service payment
        $this->servicePayment = Payment::create([
            'reference_no' => 'REC-SVC-' . uniqid(),
            'total' => 3000,
            'total_discount' => 0,
            'payment_type' => 'CASH',
            'payment_method' => 'CASH',
            'patient_id' => $this->patient->id,
            'user_id' => $this->user->id,
        ]);

        ProductOrServiceRequest::create([
            'user_id' => $this->patient->user_id,
            'staff_user_id' => $this->user->id,
            'patient_id' => $this->patient->id,
            'payment_id' => $this->servicePayment->id,
            'service_id' => $this->service->id,
            'qty' => 1,
            'payable_amount' => 3000,
            'claims_amount' => 0,
            'status' => 1,
        ]);

        // Create a product payment
        $this->productPayment = Payment::create([
            'reference_no' => 'REC-PRD-' . uniqid(),
            'total' => 2000,
            'total_discount' => 0,
            'payment_type' => 'POS',
            'payment_method' => 'POS',
            'patient_id' => $this->patient->id,
            'user_id' => $this->user->id,
        ]);

        ProductOrServiceRequest::create([
            'user_id' => $this->patient->user_id,
            'staff_user_id' => $this->user->id,
            'patient_id' => $this->patient->id,
            'payment_id' => $this->productPayment->id,
            'product_id' => $this->product->id,
            'qty' => 1,
            'payable_amount' => 2000,
            'claims_amount' => 0,
            'status' => 1,
        ]);
    }

    /** @test */
    public function test_my_transactions_returns_transactions_with_items_and_kpis()
    {
        $response = $this->actingAs($this->user)->getJson(route('billing.my-transactions', [
            'from_date' => now()->subDay()->toDateString(),
            'to_date' => now()->addDay()->toDateString(),
        ]));

        $this->assertTrue(in_array($response->status(), [200, 302]));
        $data = $response->json();
        $this->assertTrue($data['success'] ?? false);
        $this->assertArrayHasKey('transactions', $data);
        $this->assertArrayHasKey('summary', $data);

        $transactions = collect($data['transactions']);
        $this->assertTrue($transactions->count() >= 2);

        // Check line items are included on transactions
        $first = $transactions->firstWhere('id', $this->servicePayment->id);
        $this->assertNotNull($first);
        $this->assertNotEmpty($first['items']);
        $this->assertEquals('Service', $first['items'][0]['type']);

        // Check KPI cards
        $summary = $data['summary'];
        $this->assertArrayHasKey('total_amount', $summary);
        $this->assertArrayHasKey('product_total', $summary);
        $this->assertArrayHasKey('service_total', $summary);
    }

    /** @test */
    public function test_my_transactions_filters_by_item_type()
    {
        // Filter by services only
        $responseServices = $this->actingAs($this->user)->getJson(route('billing.my-transactions', [
            'from_date' => now()->subDay()->toDateString(),
            'to_date' => now()->addDay()->toDateString(),
            'item_type' => 'service',
        ]));

        $this->assertTrue(in_array($responseServices->status(), [200, 302]));
        $servicesData = $responseServices->json();
        $svcTxIds = collect($servicesData['transactions'])->pluck('id')->all();

        $this->assertContains($this->servicePayment->id, $svcTxIds);
        $this->assertNotContains($this->productPayment->id, $svcTxIds);

        // Filter by products only
        $responseProducts = $this->actingAs($this->user)->getJson(route('billing.my-transactions', [
            'from_date' => now()->subDay()->toDateString(),
            'to_date' => now()->addDay()->toDateString(),
            'item_type' => 'product',
        ]));

        $this->assertTrue(in_array($responseProducts->status(), [200, 302]));
        $productsData = $responseProducts->json();
        $prdTxIds = collect($productsData['transactions'])->pluck('id')->all();

        $this->assertContains($this->productPayment->id, $prdTxIds);
        $this->assertNotContains($this->servicePayment->id, $prdTxIds);
    }

    /** @test */
    public function test_manage_transactions_datatable_endpoint_works()
    {
        $response = $this->actingAs($this->user)->getJson(route('billing.manage-transactions', [
            'from_date' => now()->subDay()->toDateString(),
            'to_date' => now()->addDay()->toDateString(),
            'scope' => 'all',
        ]));

        $this->assertTrue(in_array($response->status(), [200, 302]));
        $data = $response->json();
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('recordsTotal', $data);
    }
}
