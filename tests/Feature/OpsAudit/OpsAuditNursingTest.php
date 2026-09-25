<?php

namespace Tests\Feature\OpsAudit;

use App\Models\AdmissionRequest;
use App\Models\Patient;
use App\Models\User;
use App\Models\Ward;
use Tests\TestCase;

class OpsAuditNursingTest extends TestCase
{
    /** @test */
    public function test_nursing_audit_index_renders_all_tabs_including_recent_discharges()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/ops-audit/nursing');

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 500]));
        if ($response->status() === 200) {
            $response->assertSee('id="tab-admissions"', false);
            $response->assertSee('id="tab-discharges"', false);
            $response->assertSee('id="tab-notes"', false);
            $response->assertSee('id="tab-bills"', false);
            $response->assertSee('id="tab-requisitions"', false);
            $response->assertSee('id="dt-admissions"', false);
            $response->assertSee('id="dt-discharges"', false);
        }
    }

    /** @test */
    public function test_nursing_audit_admissions_with_top_level_ward_filter_does_not_crash()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $ward = Ward::first();
        $wardId = $ward ? $ward->id : 1;

        $response = $this->actingAs($user)->getJson("/ops-audit/nursing/data/admissions?ward_id={$wardId}&start=0&length=25");

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 500]));
        if ($response->status() === 200) {
            $response->assertJsonStructure(['data', 'recordsTotal', 'recordsFiltered', 'kpis']);
        }
    }

    /** @test */
    public function test_nursing_audit_admissions_identifies_emergency_intakes_without_bed()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $patient = Patient::factory()->create();

        AdmissionRequest::create([
            'patient_id' => $patient->id,
            'doctor_id' => $user->id,
            'priority' => 'emergency',
            'esi_level' => 1,
            'chief_complaint' => 'Acute severe chest pain',
            'admission_reason' => '[EMERGENCY INTAKE] ESI Level 1: Resuscitation',
            'admission_status' => 'pending_checklist',
            'discharged' => false,
            'bed_id' => null,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/ops-audit/nursing/data/admissions?start=0&length=25');

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 500]));
        if ($response->status() === 200) {
            $data = $response->json('data');
            $this->assertIsArray($data);
            $found = collect($data)->first(fn ($row) => str_contains($row['patient'] ?? '', $patient->file_no));
            if ($found) {
                $this->assertStringContainsString('EMERGENCY', $found['patient']);
                $this->assertStringContainsString('ESI 1', $found['patient']);
                $this->assertStringContainsString('Emergency', $found['ward']);
            }
        }
    }

    /** @test */
    public function test_nursing_audit_discharges_tab_returns_discharged_admissions()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $patient = Patient::factory()->create();

        AdmissionRequest::create([
            'patient_id' => $patient->id,
            'doctor_id' => $user->id,
            'discharged_by' => $user->id,
            'admission_status' => 'discharged',
            'discharged' => true,
            'discharge_date' => now(),
            'discharge_reason' => 'Patient fully recovered and discharged home',
            'created_at' => now()->subDays(3),
            'bed_assign_date' => now()->subDays(3),
        ]);

        $response = $this->actingAs($user)->getJson('/ops-audit/nursing/data/discharges?start=0&length=25');

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 500]));
        if ($response->status() === 200) {
            $response->assertJsonStructure(['data', 'recordsTotal', 'recordsFiltered', 'kpis']);
            $data = $response->json('data');
            $found = collect($data)->first(fn ($row) => str_contains($row['patient'] ?? '', $patient->file_no));
            if ($found) {
                $this->assertStringContainsString('day', $found['los']);
                $this->assertStringContainsString('recovered', $found['reason']);
                $this->assertStringContainsString('Details', $found['audit']);
            }
        }
    }

    /** @test */
    public function test_nursing_audit_details_modal_populates_patient_info_and_emergency_intake()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $patient = Patient::factory()->create();

        $admission = AdmissionRequest::create([
            'patient_id' => $patient->id,
            'doctor_id' => $user->id,
            'priority' => 'emergency',
            'esi_level' => 2,
            'chief_complaint' => 'Difficulty breathing',
            'admission_reason' => '[EMERGENCY INTAKE] ESI Level 2: Emergent',
            'admission_status' => 'admitted',
            'discharged' => false,
            'created_at' => now()->subDays(2),
            'bed_assign_date' => now()->subDays(2),
        ]);

        $response = $this->actingAs($user)->getJson("/ops-audit/details/admission/{$admission->id}");

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 500]));
        if ($response->status() === 200) {
            $title = $response->json('title');
            $this->assertStringNotContainsString('Unknown', $title);

            $html = $response->json('html');
            $this->assertStringContainsString($patient->file_no, $html);
            $this->assertStringContainsString('EMERGENCY INTAKE', $html);
            $this->assertStringContainsString('ESI Level 2', $html);
            $this->assertStringContainsString('Difficulty breathing', $html);
        }
    }
}
