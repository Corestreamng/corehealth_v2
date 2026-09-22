<?php

namespace Tests\Feature\Billing;

use App\Models\ApplicationStatu;
use App\Models\Hmo;
use App\Models\HmoTariff;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Procedure;
use App\Models\ProductOrServiceRequest;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ProcedureCustomPricingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        if (function_exists('clearAppSettingsCache')) {
            clearAppSettingsCache();
        }
        if (function_exists('appsettings')) {
            appsettings(null, true);
        }
    }

    /** @test */
    public function test_hospital_config_updates_allow_doctor_set_procedure_price()
    {
        $admin = User::first() ?? User::factory()->create(['status' => 1]);

        $config = ApplicationStatu::first();
        if (!$config) {
            $config = ApplicationStatu::create([
                'site_name' => 'Test Hospital',
                'active' => true,
            ]);
        }

        $response = $this->actingAs($admin)->put(route('hospital-config.update'), [
            'site_name' => 'Test Hospital',
            'allow_doctor_set_procedure_price' => '1',
            'enable_structured_dose' => '1',
        ]);

        $this->assertTrue(in_array($response->status(), [200, 302]));

        $config->refresh();
        $this->assertTrue((bool) $config->allow_doctor_set_procedure_price);
    }

    /** @test */
    public function test_tariff_guide_returns_catalog_and_hmo_tariff()
    {
        $doctor = User::first() ?? User::factory()->create(['status' => 1]);

        $hmo = Hmo::create(['name' => 'Custom Surgical HMO', 'code' => 'CSHMO_' . uniqid(), 'status' => 1]);

        $patientUser = User::factory()->create(['status' => 1]);
        $patient = Patient::create([
            'user_id' => $patientUser->id,
            'hmo_id' => $hmo->id,
            'status' => 1,
        ]);

        $service = Service::create([
            'user_id' => $doctor->id,
            'service_name' => 'Appendectomy Surgical Service',
            'category_id' => 1,
            'status' => 1,
        ]);

        ServicePrice::create([
            'user_id' => $doctor->id,
            'service_id' => $service->id,
            'sale_price' => 75000,
            'cost_price' => 50000,
        ]);

        HmoTariff::updateOrCreate(
            ['hmo_id' => $hmo->id, 'service_id' => $service->id],
            [
                'payable_amount' => 5000,
                'claims_amount' => 70000,
                'coverage_mode' => 'express',
            ]
        );

        $procedure = Procedure::create([
            'service_id' => $service->id,
            'patient_id' => $patient->id,
            'requested_by' => $doctor->id,
            'requested_on' => now(),
            'procedure_status' => Procedure::STATUS_REQUESTED,
            'priority' => Procedure::PRIORITY_ROUTINE,
        ]);

        $response = $this->actingAs($doctor)->getJson(route('patient-procedures.tariff-guide', $procedure->id));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'procedure_id' => $procedure->id,
            'service' => [
                'id' => $service->id,
                'name' => 'Appendectomy Surgical Service',
                'catalog_price' => 75000,
            ],
            'patient' => [
                'id' => $patient->id,
                'is_hmo' => true,
                'hmo_id' => $hmo->id,
            ],
            'hmo_tariff' => [
                'has_tariff' => true,
                'payable_amount' => 5000,
                'claims_amount' => 70000,
                'coverage_mode' => 'express',
            ],
            'is_billed' => false,
        ]);
    }

    /** @test */
    public function test_bill_base_fee_for_cash_patient()
    {
        $doctor = User::first() ?? User::factory()->create(['status' => 1]);

        $patientUser = User::factory()->create(['status' => 1]);
        $patient = Patient::create([
            'user_id' => $patientUser->id,
            'hmo_id' => null,
            'status' => 1,
        ]);

        $service = Service::create([
            'user_id' => $doctor->id,
            'service_name' => 'Wound Debridement',
            'category_id' => 1,
            'status' => 1,
        ]);

        ServicePrice::create([
            'user_id' => $doctor->id,
            'service_id' => $service->id,
            'sale_price' => 20000,
            'cost_price' => 10000,
        ]);

        $procedure = Procedure::create([
            'service_id' => $service->id,
            'patient_id' => $patient->id,
            'requested_by' => $doctor->id,
            'requested_on' => now(),
            'procedure_status' => Procedure::STATUS_REQUESTED,
            'priority' => Procedure::PRIORITY_ROUTINE,
        ]);

        $response = $this->actingAs($doctor)->postJson(route('patient-procedures.bill-base-fee', $procedure->id), [
            'coverage_mode' => 'cash',
            'payable_amount' => 25000, // custom price set by doctor
            'claims_amount' => 0,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Procedure base fee billed successfully',
            'billing' => [
                'payable_amount' => 25000,
                'claims_amount' => 0,
                'coverage_mode' => 'cash',
            ],
        ]);

        $procedure->refresh();
        $this->assertNotNull($procedure->product_or_service_request_id);
        $this->assertEquals($doctor->id, $procedure->billed_by);
        $this->assertNotNull($procedure->billed_on);

        $posr = ProductOrServiceRequest::find($procedure->product_or_service_request_id);
        $this->assertNotNull($posr);
        $this->assertEquals('service', $posr->type);
        $this->assertEquals(25000, $posr->payable_amount);
        $this->assertEquals(0, $posr->claims_amount);
        $this->assertEquals('cash', $posr->coverage_mode);
        $this->assertNull($posr->hmo_id);
    }

    /** @test */
    public function test_bill_base_fee_for_hmo_express_and_primary_modes()
    {
        $doctor = User::first() ?? User::factory()->create(['status' => 1]);

        $hmo = Hmo::create(['name' => 'Surgical Care HMO', 'code' => 'SCHMO_' . uniqid(), 'status' => 1]);

        $patientUser = User::factory()->create(['status' => 1]);
        $patient = Patient::create([
            'user_id' => $patientUser->id,
            'hmo_id' => $hmo->id,
            'status' => 1,
        ]);

        $service = Service::create([
            'user_id' => $doctor->id,
            'service_name' => 'Herniorrhaphy',
            'category_id' => 1,
            'status' => 1,
        ]);

        $procedure = Procedure::create([
            'service_id' => $service->id,
            'patient_id' => $patient->id,
            'requested_by' => $doctor->id,
            'requested_on' => now(),
            'procedure_status' => Procedure::STATUS_REQUESTED,
            'priority' => Procedure::PRIORITY_ROUTINE,
        ]);

        // 1. Bill with Express mode (Auto-Approved)
        $response = $this->actingAs($doctor)->postJson(route('patient-procedures.bill-base-fee', $procedure->id), [
            'coverage_mode' => 'express',
            'payable_amount' => 5000,
            'claims_amount' => 45000,
            'auth_code' => 'AUTH-EXP-1234',
        ]);

        $response->assertStatus(200);
        $procedure->refresh();
        $posr = ProductOrServiceRequest::find($procedure->product_or_service_request_id);
        $this->assertEquals('express', $posr->coverage_mode);
        $this->assertEquals('approved', $posr->validation_status);
        $this->assertEquals($hmo->id, $posr->hmo_id);
        $this->assertEquals('AUTH-EXP-1234', $posr->auth_code);

        // 2. Modify to Primary mode (Pre-Auth Required / Pending)
        $response2 = $this->actingAs($doctor)->postJson(route('patient-procedures.bill-base-fee', $procedure->id), [
            'coverage_mode' => 'primary',
            'payable_amount' => 10000,
            'claims_amount' => 40000,
            'auth_code' => 'AUTH-PRI-5678',
        ]);

        $response2->assertStatus(200);
        $posr->refresh();
        $this->assertEquals('primary', $posr->coverage_mode);
        $this->assertEquals('pending', $posr->validation_status);
        $this->assertEquals('AUTH-PRI-5678', $posr->auth_code);
    }

    /** @test */
    public function test_bill_base_fee_rejects_negative_amounts()
    {
        $doctor = User::first() ?? User::factory()->create(['status' => 1]);

        $patientUser = User::factory()->create(['status' => 1]);
        $patient = Patient::create([
            'user_id' => $patientUser->id,
            'status' => 1,
        ]);

        $service = Service::create([
            'user_id' => $doctor->id,
            'service_name' => 'Minor Surgery',
            'category_id' => 1,
            'status' => 1,
        ]);

        $procedure = Procedure::create([
            'service_id' => $service->id,
            'patient_id' => $patient->id,
            'requested_by' => $doctor->id,
            'requested_on' => now(),
            'procedure_status' => Procedure::STATUS_REQUESTED,
        ]);

        $response = $this->actingAs($doctor)->postJson(route('patient-procedures.bill-base-fee', $procedure->id), [
            'coverage_mode' => 'cash',
            'payable_amount' => -500,
            'claims_amount' => 0,
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function test_bill_base_fee_rejects_already_paid_bills()
    {
        $doctor = User::first() ?? User::factory()->create(['status' => 1]);

        $patientUser = User::factory()->create(['status' => 1]);
        $patient = Patient::create([
            'user_id' => $patientUser->id,
            'status' => 1,
        ]);

        $service = Service::create([
            'user_id' => $doctor->id,
            'service_name' => 'Surgery',
            'category_id' => 1,
            'status' => 1,
        ]);

        $payment = Payment::create([
            'user_id' => $patientUser->id,
            'reference_no' => 'PAY-TEST-' . uniqid(),
            'total' => 50000,
            'amount_paid' => 50000,
            'payment_type' => 'Cash',
        ]);

        $posr = ProductOrServiceRequest::create([
            'type' => 'service',
            'service_id' => $service->id,
            'user_id' => $patientUser->id,
            'staff_user_id' => $doctor->id,
            'payable_amount' => 50000,
            'claims_amount' => 0,
            'coverage_mode' => 'cash',
            'payment_id' => $payment->id,
        ]);

        $procedure = Procedure::create([
            'service_id' => $service->id,
            'patient_id' => $patient->id,
            'requested_by' => $doctor->id,
            'requested_on' => now(),
            'procedure_status' => Procedure::STATUS_REQUESTED,
            'product_or_service_request_id' => $posr->id,
            'billed_by' => $doctor->id,
            'billed_on' => now(),
        ]);

        $response = $this->actingAs($doctor)->postJson(route('patient-procedures.bill-base-fee', $procedure->id), [
            'coverage_mode' => 'cash',
            'payable_amount' => 60000,
            'claims_amount' => 0,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'This procedure has already been billed and paid. Price cannot be modified.',
        ]);
    }

    /** @test */
    public function test_encounter_booking_supports_deferred_billing_and_custom_pricing()
    {
        $doctor = User::first() ?? User::factory()->create(['status' => 1]);

        $config = ApplicationStatu::first();
        if ($config) {
            $config->update(['allow_doctor_set_procedure_price' => true]);
        }

        $patientUser = User::factory()->create(['status' => 1]);
        $hmo = Hmo::create(['name' => 'Encounter HMO', 'code' => 'EHMO_' . uniqid(), 'status' => 1]);
        $patient = Patient::create([
            'user_id' => $patientUser->id,
            'hmo_id' => $hmo->id,
            'status' => 1,
        ]);

        $encounter = \App\Models\Encounter::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'start_time' => now(),
            'status' => 1,
        ]);

        $service1 = Service::create([
            'user_id' => $doctor->id,
            'service_name' => 'Exploratory Laparotomy',
            'category_id' => 1,
            'status' => 1,
        ]);

        // 1. Book with deferred billing
        $response1 = $this->actingAs($doctor)->postJson(route('encounters.addProcedure', $encounter->id), [
            'service_id' => (string) $service1->id,
            'priority' => 'urgent',
            'defer_billing' => 1,
            'scheduled_date' => now()->toDateString(),
            'scheduled_time' => '14:30',
            'operating_room' => 'Theatre 1',
            'pre_notes' => 'Patient fasting from midnight',
        ]);

        $response1->assertStatus(200);
        $response1->assertJson(['success' => true]);

        $proc1 = Procedure::where('encounter_id', $encounter->id)
            ->where('service_id', $service1->id)
            ->first();

        $this->assertNotNull($proc1);
        $this->assertNull($proc1->product_or_service_request_id);
        $this->assertEquals('Theatre 1', $proc1->operating_room);
        $this->assertEquals('14:30:00', $proc1->scheduled_time);

        // 2. Book with custom pricing & Express HMO
        $service2 = Service::create([
            'user_id' => $doctor->id,
            'service_name' => 'Cholecystectomy',
            'category_id' => 1,
            'status' => 1,
        ]);

        $response2 = $this->actingAs($doctor)->postJson(route('encounters.addProcedure', $encounter->id), [
            'service_id' => (string) $service2->id,
            'priority' => 'routine',
            'defer_billing' => 0,
            'coverage_mode' => 'express',
            'payable_amount' => 5000,
            'claims_amount' => 60000,
            'auth_code' => 'AUTH-ENC-001',
        ]);

        $response2->assertStatus(200);
        $response2->assertJson(['success' => true]);

        $proc2 = Procedure::where('encounter_id', $encounter->id)
            ->where('service_id', $service2->id)
            ->first();

        $this->assertNotNull($proc2);
        $this->assertNotNull($proc2->product_or_service_request_id);

        $posr2 = ProductOrServiceRequest::find($proc2->product_or_service_request_id);
        $this->assertNotNull($posr2);
        $this->assertEquals(5000, $posr2->payable_amount);
        $this->assertEquals(60000, $posr2->claims_amount);
        $this->assertEquals('express', $posr2->coverage_mode);
        $this->assertEquals('approved', $posr2->validation_status);
        $this->assertEquals('AUTH-ENC-001', $posr2->auth_code);
    }
}
