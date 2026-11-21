<?php

namespace Tests\Feature\Nursing;

use App\Models\MedicationAdministration;
use App\Models\Patient;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Drug charting as the staff app drives it (patient's own drug, no stock or billing): schedule, give,
 * edit without a comment, delete, and the calendar/removal rules around them.
 */
class MedicationChartCycleTest extends TestCase
{
    public function test_patient_own_drug_chart_cycle()
    {
        if (!Role::where('name', 'DOCTOR')->exists() || !($patientId = Patient::query()->value('id'))) {
            $this->markTestSkipped('Needs the DOCTOR role and one patient.');
        }
        $user = User::factory()->create(['status' => 1]);
        $user->assignRole('DOCTOR');
        Sanctum::actingAs($user);
        $base = '/api/mobile/doctor';
        $today = now()->toDateString();
        $drug = 'ZZ-TEST Paracetamol';

        $res = $this->postJson("$base/medication-chart/schedule", [
            'patient_id' => $patientId, 'is_direct' => true, 'drug_source' => 'patient_own', 'external_drug_name' => $drug,
            'times' => ['08:00', '20:00'], 'dose' => '500mg', 'route' => 'Oral', 'repeat_type' => 'daily',
            'duration_days' => 1, 'start_date' => $today,
        ]);
        $res->assertOk()->assertJsonPath('success', true);
        $scheduleId = $res->json('schedules.0.id');

        // A one-day range includes that day's doses (the end date is inclusive).
        $cal = "$base/patient/$patientId/medication-chart/direct-calendar?drug_source=patient_own&external_drug_name=" . rawurlencode($drug) . "&start_date=$today&end_date=$today";
        $this->assertSame(2, $this->getJson($cal)->assertOk()->json('total_scheduled'));

        $this->postJson("$base/patient/$patientId/medication-chart/administer-direct", [
            'drug_source' => 'patient_own', 'external_drug_name' => $drug, 'external_qty' => 1, 'schedule_id' => $scheduleId,
            'administered_at' => now()->format('Y-m-d H:i'), 'administered_dose' => '500mg', 'route' => 'Oral',
        ])->assertOk()->assertJsonPath('success', true);
        $adminId = MedicationAdministration::where('schedule_id', $scheduleId)->value('id');
        $this->assertNotNull($adminId);

        // Editing without a comment works.
        $this->postJson("$base/medication-chart/edit", [
            'administration_id' => $adminId, 'administered_at' => now()->format('Y-m-d H:i'), 'dose' => '1000mg', 'route' => 'Oral', 'edit_reason' => 'test',
        ])->assertOk()->assertJsonPath('success', true);
        $this->assertSame('1000mg', MedicationAdministration::find($adminId)->dose);

        // A schedule that had a dose given cannot be removed, even after the dose is deleted.
        $this->postJson("$base/medication-chart/remove-schedule", ['schedule_id' => $scheduleId])->assertStatus(422);
        $this->postJson("$base/medication-chart/delete", ['administration_id' => $adminId, 'reason' => 'test'])->assertOk();
        $this->postJson("$base/medication-chart/remove-schedule", ['schedule_id' => $scheduleId])->assertStatus(422);
    }

    public function test_prescribed_drug_calendar_opens_with_and_without_a_start_date()
    {
        $row = \App\Models\ProductOrServiceRequest::whereNotNull('product_id')->first();
        $patientId = $row ? ($row->patient_id ?: Patient::where('user_id', $row->user_id)->value('id')) : null;
        if (!$row || !$patientId || !Role::where('name', 'DOCTOR')->exists()) {
            $this->markTestSkipped('Needs a prescription and the DOCTOR role.');
        }
        $user = User::factory()->create(['status' => 1]);
        $user->assignRole('DOCTOR');
        Sanctum::actingAs($user);
        $url = "/api/mobile/doctor/patient/$patientId/medication-chart/calendar/{$row->id}";

        // The staff app omits the start date; that used to fail with "Too few arguments".
        $this->getJson($url)->assertOk()->assertJsonStructure(['schedules']);
        $this->getJson("$url/" . now()->toDateString())->assertOk()->assertJsonStructure(['schedules']);
    }
}
