<?php

namespace Tests\Feature\Nursing;

use App\Models\IntakeOutputPeriod;
use App\Models\IntakeOutputRecord;
use App\Models\MedicationAdministration;
use App\Models\MedicationSchedule;
use App\Models\NursingNote;
use App\Models\NursingShift;
use App\Models\Patient;
use App\Models\ProductOrServiceRequest;
use App\Models\User;
use App\Models\VitalSign;
use App\Services\ClinicalHandoverFormatter;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClinicalHandoverFormatterTest extends TestCase
{
    private ClinicalHandoverFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new ClinicalHandoverFormatter();
    }

    private function makeUser(array $attrs = []): User
    {
        return User::create(array_merge([
            'surname' => 'Johnson',
            'firstname' => 'Grace',
            'othername' => 'Chioma',
            'email' => 'nurse_' . Str::random(8) . '@example.com',
            'password' => bcrypt('secret123'),
            'status' => 1,
            'is_admin' => 0,
        ], $attrs));
    }

    private function makePatient(array $attrs = []): Patient
    {
        $user = $this->makeUser([
            'surname' => 'Eze',
            'firstname' => 'Emeka',
            'othername' => 'Kalu',
            'email' => 'patient_' . Str::random(8) . '@example.com',
        ]);

        return Patient::create(array_merge([
            'user_id' => $user->id,
            'file_no' => 'PAT-' . Str::random(6),
            'gender' => 'Male',
            'phone_no' => '0803' . rand(1000000, 9999999),
        ], $attrs));
    }

    /** @test */
    public function test_format_vital_sign_audit_produces_clinical_line_and_category()
    {
        $patient = $this->makePatient();
        $timestamp = Carbon::now();

        $values = [
            'patient_id' => $patient->id,
            'blood_pressure' => '120/80',
            'heart_rate' => '72',
            'temperature' => '36.8',
            'spo2' => '98',
            'respiratory_rate' => '18',
        ];

        $result = $this->formatter->formatAudit(
            VitalSign::class,
            'created',
            [],
            $values,
            $timestamp
        );

        $this->assertEquals('Vital Signs', $result['category']);
        $this->assertEquals('mdi-heart-pulse', $result['icon']);
        $this->assertEquals($patient->id, $result['patient_id']);
        $this->assertStringContainsString('BP 120/80 mmHg', $result['line']);
        $this->assertStringContainsString('PR 72 bpm', $result['line']);
        $this->assertStringContainsString('Temp 36.8°C', $result['line']);
        $this->assertStringContainsString('SpO₂ 98%', $result['line']);
        $this->assertEmpty($result['alerts']);
    }

    /** @test */
    public function test_format_vital_sign_audit_detects_abnormal_vitals_as_alerts()
    {
        $patient = $this->makePatient();
        $timestamp = Carbon::now();

        // High BP (160/100) and fever (39.2) and low SpO2 (88)
        $values = [
            'patient_id' => $patient->id,
            'blood_pressure' => '160/100',
            'heart_rate' => '125',
            'temperature' => '39.2',
            'spo2' => '88',
        ];

        $result = $this->formatter->formatAudit(
            VitalSign::class,
            'created',
            [],
            $values,
            $timestamp
        );

        $this->assertNotEmpty($result['alerts']);
        $alertLevels = array_column($result['alerts'], 'level');
        $this->assertTrue(in_array('critical', $alertLevels) || in_array('warning', $alertLevels));
    }

    /** @test */
    public function test_format_medication_admin_audit()
    {
        $patient = $this->makePatient();
        $timestamp = Carbon::now();

        $values = [
            'patient_id' => $patient->id,
            'product_name' => 'Ceftriaxone 1g',
            'dose' => '1g',
            'route' => 'IV',
            'status' => 'given',
            'drug_source' => 'ward_stock',
        ];

        $result = $this->formatter->formatAudit(
            MedicationAdministration::class,
            'created',
            [],
            $values,
            $timestamp
        );

        $this->assertEquals('Medication Administration', $result['category']);
        $this->assertStringContainsString('Ceftriaxone', $result['line']);
        $this->assertStringContainsString('IV', $result['line']);
    }

    /** @test */
    public function test_format_medication_schedule_audit()
    {
        $patient = $this->makePatient();
        $timestamp = Carbon::now();

        $values = [
            'patient_id' => $patient->id,
            'product_name' => 'Paracetamol 500mg',
            'dose' => '1000mg',
            'route' => 'Oral',
            'scheduled_time' => '2026-09-26 14:00:00',
            'frequency' => 'TID',
        ];

        $result = $this->formatter->formatAudit(
            MedicationSchedule::class,
            'created',
            [],
            $values,
            $timestamp
        );

        $this->assertEquals('Medication Schedule', $result['category']);
        $this->assertStringContainsString('Paracetamol', $result['line']);
        $this->assertStringContainsString('1000mg', $result['line']);
    }

    /** @test */
    public function test_format_nursing_note_audit()
    {
        $patient = $this->makePatient();
        $timestamp = Carbon::now();

        $values = [
            'patient_id' => $patient->id,
            'note' => '<p>Patient reported mild abdominal pain. Resting comfortably.</p>',
            'note_type' => 'Progress Note',
        ];

        $result = $this->formatter->formatAudit(
            NursingNote::class,
            'created',
            [],
            $values,
            $timestamp
        );

        $this->assertEquals('Nursing Notes', $result['category']);
        $this->assertStringContainsString('Patient reported mild abdominal pain', $result['line']);
        $this->assertStringNotContainsString('<p>', $result['line']); // Stripped HTML
    }

    /** @test */
    public function test_format_io_period_and_record_audit()
    {
        $patient = $this->makePatient();
        $timestamp = Carbon::now();

        $periodValues = [
            'patient_id' => $patient->id,
            'period_name' => 'Day Shift (08:00 - 16:00)',
            'total_intake' => 1200,
            'total_output' => 950,
            'balance' => 250,
        ];

        $periodResult = $this->formatter->formatAudit(
            IntakeOutputPeriod::class,
            'created',
            [],
            $periodValues,
            $timestamp
        );

        $this->assertEquals('I/O Period', $periodResult['category']);
        $this->assertStringContainsString('1200', $periodResult['line']);
        $this->assertStringContainsString('950', $periodResult['line']);

        $recordValues = [
            'patient_id' => $patient->id,
            'type' => 'intake',
            'route' => 'Oral Fluid',
            'amount' => 300,
        ];

        $recordResult = $this->formatter->formatAudit(
            IntakeOutputRecord::class,
            'created',
            [],
            $recordValues,
            $timestamp
        );

        $this->assertEquals('I/O Record', $recordResult['category']);
        $this->assertStringContainsString('300', $recordResult['line']);
    }

    /** @test */
    public function test_format_billing_audit()
    {
        $patient = $this->makePatient();
        $timestamp = Carbon::now();

        $values = [
            'patient_id' => $patient->id,
            'product_name' => 'Wound Dressing Pack',
            'qty' => 2,
            'unit_price' => 1500,
            'claims_amount' => 3000,
        ];

        $result = $this->formatter->formatAudit(
            ProductOrServiceRequest::class,
            'created',
            [],
            $values,
            $timestamp
        );

        $this->assertEquals('Billing', $result['category']);
        $this->assertStringContainsString('Wound Dressing Pack', $result['line']);
    }

    /** @test */
    public function test_format_billing_audit_resolves_service_from_services_table()
    {
        $patient = $this->makePatient();
        $timestamp = Carbon::now();

        $service = \App\Models\Service::withoutGlobalScopes()->first();
        if (!$service) {
            $service = \App\Models\Service::withoutGlobalScopes()->create([
                'user_id' => $this->makeUser()->id,
                'category_id' => 1,
                'service_name' => 'Nursing Consultation Test',
                'status' => 1,
            ]);
        }

        $values = [
            'patient_id' => $patient->id,
            'service_id' => $service->id,
            'qty' => 1,
            'payable_amount' => 2500,
        ];

        $result = $this->formatter->formatAudit(
            ProductOrServiceRequest::class,
            'created',
            [],
            $values,
            $timestamp
        );

        $this->assertEquals('Billing', $result['category']);
        $this->assertStringContainsString($service->service_name, $result['line']);
    }

    /** @test */
    public function test_patient_name_resolution_includes_othername()
    {
        $patient = $this->makePatient();

        $shift = NursingShift::create([
            'user_id' => $this->makeUser()->id,
            'ward_id' => null,
            'shift_type' => 'morning',
            'started_at' => Carbon::now()->subHours(2),
            'scheduled_end_at' => Carbon::now()->addHours(6),
            'status' => 'active',
        ]);

        $payload = $this->formatter->formatShiftHandover($shift);

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('executive_summary', $payload);
        $this->assertArrayHasKey('patient_summaries', $payload);
        $this->assertArrayHasKey('activity_timeline', $payload);
        $this->assertArrayHasKey('category_counts', $payload);
        $this->assertArrayHasKey('alerts', $payload);
    }
}
