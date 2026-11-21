<?php

namespace Tests\Feature\Reception;

use App\Models\Patient;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Registration (web reception workbench and the staff app use ReceptionWorkbenchController):
 * occupation and next-of-kin relationship are saved and returned; formats are validated, while
 * incomplete records still save.
 */
class PatientRegistrationFieldsTest extends TestCase
{
    private function receptionist(): User
    {
        if (!Role::where('name', 'RECEPTIONIST')->exists()) {
            $this->markTestSkipped('RECEPTIONIST role missing.');
        }
        $u = User::factory()->create(['status' => 1]);
        $u->assignRole('RECEPTIONIST');

        return $u;
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'surname' => 'Testreg',
            'firstname' => 'Fields' . random_int(1000, 9999),
            'gender' => 'Female',
            'dob' => '1990-05-04',
            'phone_no' => '08031234567',
        ], $extra);
    }

    public function test_occupation_and_relationship_are_saved_and_returned()
    {
        Sanctum::actingAs($this->receptionist());
        $res = $this->postJson('/api/mobile/reception/patient/quick-register', $this->payload([
            'occupation' => 'Teacher',
            'next_of_kin_name' => 'Ada Testreg',
            'next_of_kin_relationship' => 'Sister',
            'next_of_kin_phone' => '+234 803 000 1111',
        ]));
        $this->assertTrue(in_array($res->status(), [200, 201]), $res->getContent());
        $patient = Patient::whereHas('user', fn ($q) => $q->where('surname', 'Testreg'))->latest('id')->first();
        $this->assertSame('Teacher', $patient->occupation);
        $this->assertSame('Sister', $patient->next_of_kin_relationship);

        $detail = $this->getJson("/api/mobile/reception/patient/{$patient->id}");
        $this->assertStringContainsString('Sister', $detail->getContent());
        $this->assertStringContainsString('Teacher', $detail->getContent());
    }

    public function test_invalid_formats_are_rejected_with_field_errors()
    {
        Sanctum::actingAs($this->receptionist());
        $res = $this->postJson('/api/mobile/reception/patient/quick-register', $this->payload([
            'next_of_kin_phone' => 'call me',
            'dob' => now()->addDay()->toDateString(),
        ]));
        $res->assertStatus(422)->assertJsonValidationErrors(['next_of_kin_phone', 'dob']);
    }

    public function test_optional_details_may_be_missing()
    {
        Sanctum::actingAs($this->receptionist());
        $res = $this->postJson('/api/mobile/reception/patient/quick-register', $this->payload(['phone_no' => null]));
        $this->assertTrue(in_array($res->status(), [200, 201]), $res->getContent());
    }
}
