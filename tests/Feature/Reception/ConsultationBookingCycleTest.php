<?php

namespace Tests\Feature\Reception;

use App\Models\Clinic;
use App\Models\DoctorQueue;
use App\Models\Patient;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use App\Models\Staff;
use App\Models\User;
use Tests\TestCase;

class ConsultationBookingCycleTest extends TestCase
{
    protected $receptionistUser;

    protected $receptionistStaff;

    protected $patient;

    protected $clinic1;

    protected $clinic2;

    protected $consultationCategory;

    protected $service1;

    protected $service2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->receptionistUser = User::factory()->create(['status' => 1]);
        $this->receptionistStaff = Staff::create([
            'user_id' => $this->receptionistUser->id,
            'status' => 1,
        ]);

        $patientUser = User::factory()->create(['status' => 1]);
        $this->patient = Patient::factory()->create([
            'user_id' => $patientUser->id,
            'hmo_id' => 1, // Private
        ]);

        $this->clinic1 = Clinic::create(['name' => 'General Clinic']);
        $this->clinic2 = Clinic::create(['name' => 'Cardiology Clinic']);

        $consultationCategoryId = appsettings('consultation_category_id', 1);
        $this->consultationCategory = ServiceCategory::firstOrCreate(
            ['id' => $consultationCategoryId],
            ['name' => 'Consultation', 'status' => 1]
        );

        $this->service1 = Service::create([
            'user_id' => $this->receptionistUser->id,
            'category_id' => $this->consultationCategory->id,
            'service_name' => 'General Consultation',
            'service_code' => 'GEN-CONS-' . uniqid(),
            'status' => 1,
            'consult_cycle_duration' => 24,
        ]);
        ServicePrice::create([
            'service_id' => $this->service1->id,
            'sale_price' => 2200,
        ]);

        $this->service2 = Service::create([
            'user_id' => $this->receptionistUser->id,
            'category_id' => $this->consultationCategory->id,
            'service_name' => 'Cardiologist Consultation',
            'service_code' => 'CARD-CONS-' . uniqid(),
            'status' => 1,
            'consult_cycle_duration' => 24,
        ]);
        ServicePrice::create([
            'service_id' => $this->service2->id,
            'sale_price' => 6200,
        ]);
    }

    /** @test */
    public function test_booking_two_different_clinics_back_to_back_bills_both_clinics()
    {
        $this->actingAs($this->receptionistUser);

        // 1. Book first clinic (General Consultation at General Clinic)
        $payload1 = [
            'bookings' => [
                [
                    'patient_id' => $this->patient->id,
                    'service_id' => $this->service1->id,
                    'clinic_id' => $this->clinic1->id,
                    'doctor_id' => null,
                    'force_rebill' => 0,
                ],
            ],
        ];

        $response1 = $this->postJson(route('reception.book-consultation'), $payload1);
        $this->assertTrue(in_array($response1->status(), [200, 302]));

        if ($response1->status() === 200) {
            $data1 = $response1->json();
            $this->assertTrue($data1['success']);
            $this->assertTrue($data1['batch_responses'][0]['is_new_bill']);
            $serviceReqId1 = $data1['batch_responses'][0]['service_request_id'];
            $queueId1 = $data1['batch_responses'][0]['queue_id'];

            $queue1 = DoctorQueue::with('request_entry.service')->find($queueId1);
            $this->assertEquals($this->clinic1->id, $queue1->clinic_id);
            $this->assertEquals($this->service1->id, $queue1->request_entry->service_id);
            $this->assertEquals('General Consultation', $queue1->request_entry->service->service_name);

            // 2. Book second clinic immediately (Cardiologist Consultation at Cardiology Clinic)
            $payload2 = [
                'bookings' => [
                    [
                        'patient_id' => $this->patient->id,
                        'service_id' => $this->service2->id,
                        'clinic_id' => $this->clinic2->id,
                        'doctor_id' => null,
                        'force_rebill' => 0,
                    ],
                ],
            ];

            $response2 = $this->postJson(route('reception.book-consultation'), $payload2);
            $this->assertEquals(200, $response2->status());

            $data2 = $response2->json();
            $this->assertTrue($data2['success']);

            // Must NOT skip billing for a different clinic
            $this->assertTrue($data2['batch_responses'][0]['is_new_bill']);
            $serviceReqId2 = $data2['batch_responses'][0]['service_request_id'];
            $queueId2 = $data2['batch_responses'][0]['queue_id'];

            // Service request must be different from the first clinic
            $this->assertNotEquals($serviceReqId1, $serviceReqId2);

            $queue2 = DoctorQueue::with('request_entry.service')->find($queueId2);
            $this->assertEquals($this->clinic2->id, $queue2->clinic_id);
            $this->assertEquals($this->service2->id, $queue2->request_entry->service_id);
            $this->assertEquals('Cardiologist Consultation', $queue2->request_entry->service->service_name);
        }
    }

    /** @test */
    public function test_booking_same_clinic_and_service_within_cycle_skips_billing_unless_forced()
    {
        $this->actingAs($this->receptionistUser);

        // 1. Initial booking
        $payload1 = [
            'bookings' => [
                [
                    'patient_id' => $this->patient->id,
                    'service_id' => $this->service1->id,
                    'clinic_id' => $this->clinic1->id,
                    'doctor_id' => null,
                    'force_rebill' => 0,
                ],
            ],
        ];

        $response1 = $this->postJson(route('reception.book-consultation'), $payload1);
        $this->assertEquals(200, $response1->status());
        $data1 = $response1->json();
        $this->assertTrue($data1['batch_responses'][0]['is_new_bill']);
        $serviceReqId1 = $data1['batch_responses'][0]['service_request_id'];

        // 2. Return visit to same clinic and service within cycle (force_rebill = 0)
        $payload2 = [
            'bookings' => [
                [
                    'patient_id' => $this->patient->id,
                    'service_id' => $this->service1->id,
                    'clinic_id' => $this->clinic1->id,
                    'doctor_id' => null,
                    'force_rebill' => 0,
                ],
            ],
        ];

        $response2 = $this->postJson(route('reception.book-consultation'), $payload2);
        $this->assertEquals(200, $response2->status());
        $data2 = $response2->json();

        // Billing should be skipped, reusing request entry 1
        $this->assertFalse($data2['batch_responses'][0]['is_new_bill']);
        $this->assertEquals($serviceReqId1, $data2['batch_responses'][0]['service_request_id']);

        // 3. Return visit with force_rebill = 1
        $payload3 = [
            'bookings' => [
                [
                    'patient_id' => $this->patient->id,
                    'service_id' => $this->service1->id,
                    'clinic_id' => $this->clinic1->id,
                    'doctor_id' => null,
                    'force_rebill' => 1,
                ],
            ],
        ];

        $response3 = $this->postJson(route('reception.book-consultation'), $payload3);
        $this->assertEquals(200, $response3->status());
        $data3 = $response3->json();

        // Billing should NOT be skipped when forced
        $this->assertTrue($data3['batch_responses'][0]['is_new_bill']);
        $this->assertNotEquals($serviceReqId1, $data3['batch_responses'][0]['service_request_id']);
    }
}
