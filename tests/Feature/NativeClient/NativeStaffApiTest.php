<?php

namespace Tests\Feature\NativeClient;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Native staff client API: staff-only login, workspace list, the same role guards as the web
 * sidebar on every mobile route group, and wrappers that reuse the web controllers unchanged.
 */
class NativeStaffApiTest extends TestCase
{
    protected function userWithRole(string $role): User
    {
        if (!Role::where('name', $role)->exists()) {
            $this->markTestSkipped("{$role} role missing.");
        }
        // Real patient accounts have is_admin = 19 and no staff row (other users get one automatically)
        $attrs = ['status' => 1, 'password' => bcrypt('secret-pass')] + ($role === 'PATIENT' ? ['is_admin' => 19] : []);
        $user = User::factory()->create($attrs);
        if ($role === 'PATIENT') {
            \App\Models\Staff::where('user_id', $user->id)->delete();
        }
        $user->assignRole($role);

        return $user;
    }

    /** @test */
    public function test_patient_accounts_cannot_sign_in_to_the_staff_client()
    {
        $patient = $this->userWithRole('PATIENT');
        $resp = $this->postJson('/api/mobile/staff/login', ['email' => $patient->email, 'password' => 'secret-pass']);
        $this->assertEquals(403, $resp->status());

        $staff = $this->userWithRole('RECEPTIONIST');
        $resp = $this->postJson('/api/mobile/staff/login', ['email' => $staff->email, 'password' => 'secret-pass']);
        $this->assertEquals(200, $resp->status());
        $this->assertContains('reception', array_column($resp->json('data.workspaces'), 'key'));
    }

    /** @test */
    public function test_deactivated_staff_cannot_sign_in()
    {
        $staff = $this->userWithRole('DOCTOR');
        $staff->status = 0;
        $staff->save();
        $resp = $this->postJson('/api/mobile/staff/login', ['email' => $staff->email, 'password' => 'secret-pass']);
        $this->assertEquals(403, $resp->status());
    }

    /** @test */
    public function test_workspaces_endpoint_lists_only_permitted_sections_in_sidebar_order()
    {
        Sanctum::actingAs($this->userWithRole('RECEPTIONIST'));
        $resp = $this->getJson('/api/mobile/staff/workspaces');
        $this->assertEquals(200, $resp->status());
        $keys = array_column($resp->json('data.workspaces'), 'key');
        $this->assertSame('global', $keys[0]);
        $this->assertContains('reception', $keys);
        $this->assertNotContains('doctor', $keys);
        foreach ($resp->json('data.workspaces') as $w) {
            foreach ($w['items'] as $item) {
                $this->assertArrayHasKey('web_url', $item);
            }
        }
    }

    /** @test */
    public function test_mobile_routes_enforce_the_sidebar_guards()
    {
        Sanctum::actingAs($this->userWithRole('RECEPTIONIST'));
        $this->assertEquals(403, $this->getJson('/api/mobile/doctor/queues')->status(), 'Receptionist must not use doctor routes');
        $this->assertEquals(200, $this->getJson('/api/mobile/reception/today-stats')->status());

        Sanctum::actingAs($this->userWithRole('DOCTOR'));
        $this->assertEquals(403, $this->getJson('/api/mobile/reception/today-stats')->status(), 'Doctor must not use reception routes');

        Sanctum::actingAs($this->userWithRole('PATIENT'));
        $this->assertEquals(403, $this->getJson('/api/mobile/doctor/queues')->status());
        $this->assertEquals(403, $this->getJson('/api/mobile/doctor/chat/conversations')->status());
        $this->assertEquals(403, $this->getJson('/api/mobile/staff/workspaces')->status());
    }

    /** @test */
    public function test_reception_wrappers_return_exactly_what_the_web_workbench_returns()
    {
        $receptionist = $this->userWithRole('RECEPTIONIST');

        $web = $this->actingAs($receptionist)->getJson('/reception/today-stats');
        Sanctum::actingAs($receptionist);
        $native = $this->getJson('/api/mobile/reception/today-stats');

        $this->assertEquals(200, $native->status());
        $this->assertEquals($web->json(), $native->json(), 'Native wrapper must reuse the web controller unchanged');
    }

    public function test_global_search_returns_exactly_what_the_web_top_bar_search_returns()
    {
        $nurse = $this->userWithRole('NURSE');

        $web = $this->actingAs($nurse)->getJson('/search/api?q=ward');
        Sanctum::actingAs($nurse);
        $native = $this->getJson('/api/mobile/staff/search?q=ward');

        $this->assertEquals(200, $native->status());
        $this->assertNotEmpty($native->json(), 'a nurse should find ward pages');
        $this->assertEquals($web->json(), $native->json(), 'The app must use the web top bar search unchanged');
        $this->assertArrayHasKey('url', $native->json()[0]);
        $this->assertArrayHasKey('section', $native->json()[0]);
    }

    public function test_global_search_is_for_active_staff_only()
    {
        Sanctum::actingAs($this->userWithRole('PATIENT'));
        $this->getJson('/api/mobile/staff/search?q=ward')->assertStatus(403);
    }

    public function test_clinical_context_returns_exactly_what_the_web_modal_loads()
    {
        $patient = \App\Models\Patient::query()->first();
        $this->assertNotNull($patient, 'test database needs a patient');

        foreach (['NURSE', 'MATERNITY'] as $role) {
            $user = $this->userWithRole($role);
            foreach (['vitals', 'notes', 'medications', 'allergies', 'labs', 'imaging', 'procedures'] as $part) {
                $web = $this->actingAs($user)->getJson("/clinical-context/patient/{$patient->id}/{$part}");
                Sanctum::actingAs($user);
                $native = $this->getJson("/api/mobile/clinical-context/patient/{$patient->id}/{$part}");
                $this->assertEquals(200, $native->status(), "$role $part");
                $this->assertEquals($web->json(), $native->json(), "$role $part must match the web modal");
            }
        }
    }

    public function test_clinical_context_is_closed_to_workspaces_without_the_modal()
    {
        $patient = \App\Models\Patient::query()->first();
        Sanctum::actingAs($this->userWithRole('RECEPTIONIST'));
        $this->getJson("/api/mobile/clinical-context/patient/{$patient->id}/vitals")->assertStatus(403);
    }

    public function test_nursing_emergency_queue_matches_the_web_queue()
    {
        $nurse = $this->userWithRole('NURSE');
        $web = $this->actingAs($nurse)->getJson('/emergency/queue');
        Sanctum::actingAs($nurse);
        $native = $this->getJson('/api/mobile/nursing/emergency-queue');
        $this->assertEquals(200, $native->status());
        $this->assertEquals($web->json(), $native->json());
    }

    public function test_nursing_emergency_queue_matches_the_web_workbench()
    {
        $nurse = $this->userWithRole('NURSE');
        $web = $this->actingAs($nurse)->getJson('/emergency/queue');
        Sanctum::actingAs($nurse);
        $native = $this->getJson('/api/mobile/nursing/emergency-queue');

        $this->assertEquals(200, $native->status());
        $this->assertEquals($web->json(), $native->json());
    }
}
