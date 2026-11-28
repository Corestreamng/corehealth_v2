<?php

namespace Tests\Feature\NativeClient;

use App\Models\AdmissionRequest;
use App\Models\Patient;
use App\Models\Staff;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DoctorAdmissionsAndDoctorsTest extends TestCase
{
    public function test_doctor_admissions_doctors_and_patient_admissions_endpoints_return_expected_payloads()
    {
        if (!Role::where('name', 'DOCTOR')->exists() || !($patientId = Patient::query()->value('id'))) {
            $this->markTestSkipped('Needs the DOCTOR role and at least one patient.');
        }

        $user = User::factory()->create([
            'surname' => 'Okafor',
            'firstname' => 'Chidi',
            'othername' => 'Emeka',
            'status' => 1,
        ]);
        $user->assignRole('DOCTOR');

        $staff = Staff::where('user_id', $user->id)->first()
            ?? Staff::create(['user_id' => $user->id, 'status' => 1, 'employment_status' => Staff::STATUS_ACTIVE]);

        $admission = AdmissionRequest::create([
            'patient_id' => $patientId,
            'doctor_id' => $user->id,
            'admission_reason' => 'Observation for fever',
            'discharged' => 0,
            'status' => 1,
            'admission_status' => 'admitted',
        ]);

        Sanctum::actingAs($user);

        // 1. My Admissions
        $myRes = $this->getJson('/api/mobile/doctor/admissions?per_page=10');
        $myRes->assertOk()
            ->assertJsonPath('status', true);
        $myIds = collect($myRes->json('data'))->pluck('id')->all();
        $this->assertContains($admission->id, $myIds);
        $myRow = collect($myRes->json('data'))->firstWhere('id', $admission->id);
        $this->assertSame('Okafor Chidi Emeka', $myRow['doctor_name']);

        // 2. All Admissions filtered by user.id AND by staff.id
        $allByUser = $this->getJson('/api/mobile/doctor/admissions/all?per_page=10&doctor_id=' . $user->id);
        $allByUser->assertOk()->assertJsonPath('status', true);
        $this->assertContains($admission->id, collect($allByUser->json('data'))->pluck('id')->all());

        $allByStaff = $this->getJson('/api/mobile/doctor/admissions/all?per_page=10&doctor_id=' . $staff->id);
        $allByStaff->assertOk()->assertJsonPath('status', true);
        $this->assertContains($admission->id, collect($allByStaff->json('data'))->pluck('id')->all());

        // 3. Doctors dropdown
        $docsRes = $this->getJson('/api/mobile/doctor/doctors');
        $docsRes->assertOk()->assertJsonPath('status', true);
        $docEntry = collect($docsRes->json('data'))->firstWhere('id', $staff->id);
        $this->assertNotNull($docEntry);
        $this->assertSame($user->id, $docEntry['user_id']);
        $this->assertSame('Okafor Chidi Emeka', $docEntry['name']);

        // 4. Patient admissions under doctor workspace
        $patRes = $this->getJson('/api/mobile/doctor/patient/' . $patientId . '/admissions');
        $patRes->assertOk();
        $patRow = collect($patRes->json('admissions'))->firstWhere('id', $admission->id);
        $this->assertNotNull($patRow);
        $this->assertSame('Okafor Chidi Emeka', $patRow['doctor_name']);
        $this->assertArrayHasKey('ward_name', $patRow);
        $this->assertArrayHasKey('bed_name', $patRow);
    }
}
