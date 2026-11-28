<?php

namespace Tests\Feature\NativeClient;

use App\Models\Encounter;
use App\Models\MaternityEnrollment;
use App\Models\Patient;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Models\VitalSign;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClinicalOrdersAndVitalsTest extends TestCase
{
    public function test_doctor_orders_accept_integer_ids_and_vitals_saves_cleanly()
    {
        $adminRole = Role::where('name', 'SUPERADMIN')->exists() ? 'SUPERADMIN' : 'DOCTOR';
        $encounter = Encounter::query()->first();
        $serviceId = Service::query()->where('status', 1)->value('id');
        $productId = Product::query()->where('status', 1)->where('product_type', 'drug')->value('id');

        if (!$encounter || !$serviceId || !$productId) {
            $this->markTestSkipped('Needs seeded encounter, active service, and drug product.');
        }

        $user = User::factory()->create(['status' => 1]);
        $user->assignRole($adminRole);
        Sanctum::actingAs($user);

        // Batch save endpoints (Flutter sends JSON integers)
        $this->postJson("/api/mobile/doctor/encounters/{$encounter->id}/save-labs", [
            'consult_invest_id' => [(int) $serviceId],
            'consult_invest_note' => ['Fasting sample'],
        ])->assertOk()->assertJsonPath('success', true);

        $this->postJson("/api/mobile/doctor/encounters/{$encounter->id}/save-imaging", [
            'consult_imaging_id' => [(int) $serviceId],
            'consult_imaging_note' => ['PA view'],
        ])->assertOk()->assertJsonPath('success', true);

        $this->postJson("/api/mobile/doctor/encounters/{$encounter->id}/save-prescriptions", [
            'consult_presc_id' => [(int) $productId],
            'consult_presc_dose' => ['500mg | PO | BD | 5 days'],
        ])->assertOk()->assertJsonPath('success', true);

        // Single add endpoints with JSON integers
        $this->postJson("/api/mobile/doctor/encounters/{$encounter->id}/add-lab", [
            'service_id' => (int) $serviceId,
            'note' => 'Urgent',
        ])->assertOk()->assertJsonPath('success', true);

        $this->postJson("/api/mobile/doctor/encounters/{$encounter->id}/add-imaging", [
            'service_id' => (int) $serviceId,
            'note' => 'Baseline',
        ])->assertOk()->assertJsonPath('success', true);

        $this->postJson("/api/mobile/doctor/encounters/{$encounter->id}/add-prescription", [
            'product_id' => (int) $productId,
            'dose' => '1 tab OD',
        ])->assertOk()->assertJsonPath('success', true);

        $this->postJson("/api/mobile/doctor/encounters/{$encounter->id}/add-procedure", [
            'service_id' => (int) $serviceId,
            'priority' => 'routine',
        ])->assertOk()->assertJsonPath('success', true);

        // Doctor vitals save (with ISO datetime + single metric BP only + without polluting form_data)
        $this->postJson('/api/mobile/doctor/vitals', [
            'patient_id' => $encounter->patient_id,
            'encounter_id' => $encounter->id,
            'bloodPressure' => '120/80',
            'datetimeField' => '2026-11-28T09:45:12.123456',
        ])->assertOk()->assertJsonPath('success', true);

        $latestVital = VitalSign::where('patient_id', $encounter->patient_id)->latest('id')->first();
        $this->assertNotNull($latestVital);
        $this->assertSame('120/80', $latestVital->blood_pressure);
        $this->assertNull($latestVital->form_data);

        // Validation errors return clean 422 JSON
        $this->postJson('/api/mobile/doctor/vitals', [
            'patient_id' => $encounter->patient_id,
            'bodyTemperature' => 25,
        ])->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_nursing_and_maternity_clinical_orders_end_to_end()
    {
        $patientId = Patient::query()->value('id');
        $serviceId = Service::query()->where('status', 1)->value('id');
        $productId = Product::query()->where('status', 1)->where('product_type', 'drug')->value('id');
        if (!$patientId || !$serviceId || !$productId) {
            $this->markTestSkipped('Needs seeded patient, active service, and drug product.');
        }

        $user = User::factory()->create(['status' => 1]);
        foreach (['NURSE', 'MATERNITY', 'DOCTOR'] as $r) {
            if (Role::where('name', $r)->exists()) {
                $user->assignRole($r);
            }
        }
        Sanctum::actingAs($user);

        // Nursing clinical requests (int & string IDs, edit, remove)
        $nLab = $this->postJson('/api/mobile/nursing/clinical-requests/add-lab', [
            'patient_id' => $patientId,
            'service_id' => (int) $serviceId,
            'note' => 'Nurse note',
        ])->assertOk()->assertJsonPath('success', true);
        $this->putJson('/api/mobile/nursing/clinical-requests/labs/' . $nLab->json('id') . '/note', ['note' => 'Updated'])
            ->assertOk()->assertJsonPath('success', true);
        $this->deleteJson('/api/mobile/nursing/clinical-requests/labs/' . $nLab->json('id'))
            ->assertOk()->assertJsonPath('success', true);

        $nImg = $this->postJson('/api/mobile/nursing/clinical-requests/add-imaging', [
            'patient_id' => $patientId,
            'service_id' => (int) $serviceId,
            'note' => 'Portable',
        ])->assertOk()->assertJsonPath('success', true);
        $this->deleteJson('/api/mobile/nursing/clinical-requests/imaging/' . $nImg->json('id'))
            ->assertOk()->assertJsonPath('success', true);

        $nRx = $this->postJson('/api/mobile/nursing/clinical-requests/add-prescription', [
            'patient_id' => $patientId,
            'product_id' => (int) $productId,
            'dose' => '500mg BD',
        ])->assertOk()->assertJsonPath('success', true);
        $this->putJson('/api/mobile/nursing/clinical-requests/prescriptions/' . $nRx->json('id') . '/dose', ['dose' => '500mg TDS'])
            ->assertOk()->assertJsonPath('success', true);
        $this->deleteJson('/api/mobile/nursing/clinical-requests/prescriptions/' . $nRx->json('id'))
            ->assertOk()->assertJsonPath('success', true);

        $nProc = $this->postJson('/api/mobile/nursing/clinical-requests/add-procedure', [
            'patient_id' => $patientId,
            'service_id' => (int) $serviceId,
            'priority' => 'routine',
        ])->assertOk()->assertJsonPath('success', true);
        $this->deleteJson('/api/mobile/nursing/clinical-requests/procedures/' . $nProc->json('id'))
            ->assertOk()->assertJsonPath('success', true);

        // Maternity clinical orders
        $matId = MaternityEnrollment::query()->value('id');
        if ($matId) {
            $mLab = $this->postJson("/api/mobile/maternity/enrollment/{$matId}/add-lab", [
                'service_id' => (int) $serviceId,
                'note' => 'ANC lab',
            ])->assertOk()->assertJsonPath('success', true);
            $this->deleteJson("/api/mobile/maternity/enrollment/{$matId}/labs/" . $mLab->json('id'))
                ->assertOk()->assertJsonPath('success', true);

            $mImg = $this->postJson("/api/mobile/maternity/enrollment/{$matId}/add-imaging", [
                'service_id' => (int) $serviceId,
                'note' => 'USS',
            ])->assertOk()->assertJsonPath('success', true);
            $this->deleteJson("/api/mobile/maternity/enrollment/{$matId}/imaging/" . $mImg->json('id'))
                ->assertOk()->assertJsonPath('success', true);

            $mRx = $this->postJson("/api/mobile/maternity/enrollment/{$matId}/add-prescription", [
                'product_id' => (int) $productId,
                'dose' => 'OD',
            ])->assertOk()->assertJsonPath('success', true);
            $this->putJson("/api/mobile/maternity/enrollment/{$matId}/prescriptions/" . $mRx->json('id') . '/dose', ['dose' => 'BD'])
                ->assertOk()->assertJsonPath('success', true);
            $this->deleteJson("/api/mobile/maternity/enrollment/{$matId}/prescriptions/" . $mRx->json('id'))
                ->assertOk()->assertJsonPath('success', true);

            $mProc = $this->postJson("/api/mobile/maternity/enrollment/{$matId}/add-procedure", [
                'service_id' => (int) $serviceId,
                'priority' => 'routine',
            ])->assertOk()->assertJsonPath('success', true);
            $this->deleteJson("/api/mobile/maternity/enrollment/{$matId}/procedures/" . $mProc->json('id'))
                ->assertOk()->assertJsonPath('success', true);
        }
    }
}
