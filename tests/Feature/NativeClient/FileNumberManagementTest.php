<?php

namespace Tests\Feature\NativeClient;

use App\Models\Patient;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * What the staff app's file number field relies on: the department's series (with the choice where there
 * are several), the next number, and the live duplicate check.
 */
class FileNumberManagementTest extends TestCase
{
    protected function receptionist(): User
    {
        if (!Role::where('name', 'RECEPTIONIST')->exists()) {
            $this->markTestSkipped('RECEPTIONIST role missing.');
        }
        $user = User::factory()->create(['status' => 1]);
        $user->assignRole('RECEPTIONIST');

        return $user;
    }

    public function test_next_number_lists_the_series_the_department_may_use()
    {
        Sanctum::actingAs($this->receptionist());
        $ajax = ['X-Requested-With' => 'XMLHttpRequest'];

        $maternity = $this->getJson('/api/mobile/reception/patient/next-file-number?workbench=maternity', $ajax)->assertOk();
        $this->assertSame(['ANC-', 'PANC-'], $maternity->json('prefix_options'));

        $walkin = $this->getJson('/api/mobile/reception/patient/next-file-number?prefix=EX-', $ajax)->assertOk();
        $this->assertSame(['EX-'], $walkin->json('prefix_options'));
        $this->assertStringStartsWith('EX-', $walkin->json('file_no'));

        $normal = $this->getJson('/api/mobile/reception/patient/next-file-number?workbench=reception', $ajax)->assertOk();
        $this->assertSame([], $normal->json('prefix_options'));
    }

    public function test_duplicate_check_names_who_has_the_number_and_excludes_the_edited_patient()
    {
        $patient = Patient::whereNotNull('file_no')->where('file_no', '!=', '')->first();
        if (!$patient) {
            $this->markTestSkipped('No patient with a file number.');
        }
        Sanctum::actingAs($this->receptionist());
        $ajax = ['X-Requested-With' => 'XMLHttpRequest'];

        $taken = $this->postJson('/api/mobile/reception/patient/check-file-number', ['file_no' => $patient->file_no], $ajax)->assertOk();
        $this->assertTrue($taken->json('exists'));
        $this->assertNotEmpty($taken->json('patients'));

        $own = $this->postJson('/api/mobile/reception/patient/check-file-number', ['file_no' => $patient->file_no, 'exclude_patient_id' => $patient->id], $ajax)->assertOk();
        $this->assertFalse($own->json('exists') && $own->json('count') === 1 && $own->json('patients.0.id') === $patient->id, 'The patient being edited must not count as a duplicate of themselves.');

        $free = $this->postJson('/api/mobile/reception/patient/check-file-number', ['file_no' => 'ZZ-NOT-USED-9999'], $ajax)->assertOk();
        $this->assertFalse($free->json('exists'));
    }
}
