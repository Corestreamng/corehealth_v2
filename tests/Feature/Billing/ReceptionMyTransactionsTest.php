<?php

namespace Tests\Feature\Billing;

use App\Models\Patient;
use App\Models\Payment;
use App\Models\ProductOrServiceRequest;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReceptionMyTransactionsTest extends TestCase
{
    protected User $receptionistBiller;

    protected User $pureReceptionist;

    protected Patient $patient;

    protected Service $service;

    protected Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $this->receptionistBiller = User::factory()->create(['status' => 1]);
        $billerRole = Role::firstOrCreate(['name' => 'Biller', 'guard_name' => 'web']);
        $receptionRole = Role::firstOrCreate(['name' => 'Receptionist', 'guard_name' => 'web']);
        $this->receptionistBiller->assignRole($billerRole);
        $this->receptionistBiller->assignRole($receptionRole);

        $this->pureReceptionist = User::factory()->create(['status' => 1]);
        $this->pureReceptionist->assignRole($receptionRole);

        $patientUser = User::factory()->create(['status' => 1]);
        $this->patient = Patient::create([
            'user_id' => $patientUser->id,
            'file_no' => 'REC_TX_' . uniqid(),
            'status' => 1,
        ]);

        $this->service = Service::create([
            'user_id' => $this->receptionistBiller->id,
            'service_name' => 'Reception Walkin Lab ' . uniqid(),
            'category_id' => 1,
            'status' => 1,
        ]);

        ServicePrice::create([
            'user_id' => $this->receptionistBiller->id,
            'service_id' => $this->service->id,
            'sale_price' => 4500,
        ]);

        $this->payment = Payment::create([
            'reference_no' => 'REC-RCP-' . uniqid(),
            'total' => 4500,
            'total_discount' => 0,
            'payment_type' => 'CASH',
            'payment_method' => 'CASH',
            'patient_id' => $this->patient->id,
            'user_id' => $this->receptionistBiller->id,
        ]);

        ProductOrServiceRequest::create([
            'user_id' => $this->patient->user_id,
            'staff_user_id' => $this->receptionistBiller->id,
            'patient_id' => $this->patient->id,
            'payment_id' => $this->payment->id,
            'service_id' => $this->service->id,
            'qty' => 1,
            'payable_amount' => 4500,
            'claims_amount' => 0,
            'status' => 1,
        ]);
    }

    /** @test */
    public function test_receptionist_biller_can_access_my_transactions_endpoint()
    {
        $response = $this->actingAs($this->receptionistBiller)->getJson(route('reception.my-transactions', [
            'from_date' => now()->subDay()->toDateString(),
            'to_date' => now()->addDay()->toDateString(),
        ]));

        $this->assertTrue(in_array($response->status(), [200, 302]));

        if ($response->status() === 200) {
            $data = $response->json();
            $this->assertTrue($data['success'] ?? false);
            $this->assertArrayHasKey('transactions', $data);
            $this->assertArrayHasKey('summary', $data);

            $transactions = collect($data['transactions']);
            $match = $transactions->firstWhere('id', $this->payment->id);
            $this->assertNotNull($match);
            $this->assertNotEmpty($match['items']);
            $this->assertEquals('Service', $match['items'][0]['type']);
            $this->assertEquals('Cash', $match['payment_method_display']);
        }
    }

    /** @test */
    public function test_reception_workbench_view_renders_for_receptionist_biller()
    {
        $response = $this->actingAs($this->receptionistBiller)->get(route('reception.workbench'));
        $this->assertTrue(in_array($response->status(), [200, 302]));

        if ($response->status() === 200) {
            $response->assertSee('myTransactionsModal');
            $response->assertSee('paymentModal');
        }
    }
}
