<?php

namespace Tests\Feature\NativeClient;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Staff app, Laboratory workspace: guarded like the web sidebar (SUPERADMIN, ADMIN, LAB SCIENTIST) and served by the
 * same LabWorkbenchController methods as the web workbench.
 */
class LabMobileTest extends TestCase
{
    private const AJAX = ['X-Requested-With' => 'XMLHttpRequest'];

    private function staff(string $role): User
    {
        if (!Role::where('name', $role)->exists()) {
            $this->markTestSkipped("{$role} role missing.");
        }
        $user = User::factory()->create(['status' => 1]);
        $user->assignRole($role);

        return $user;
    }

    public function test_only_lab_staff_and_admins_pass_the_guard()
    {
        foreach (['LAB SCIENTIST', 'ADMIN'] as $role) {
            Sanctum::actingAs($this->staff($role));
            $this->getJson('/api/mobile/lab/queue-counts', self::AJAX)->assertOk()->assertJsonStructure(['billing', 'sample', 'results', 'completed', 'freeform', 'total', 'emergency', 'approval']);
        }
        foreach (['NURSE', 'BILLER', 'RADIOLOGIST'] as $role) {
            Sanctum::actingAs($this->staff($role));
            $this->getJson('/api/mobile/lab/queue-counts', self::AJAX)->assertStatus(403);
        }
    }

    public function test_the_queue_is_a_server_paged_datatables_page()
    {
        Sanctum::actingAs($this->staff('LAB SCIENTIST'));
        $page = $this->getJson('/api/mobile/lab/queue?draw=4&start=0&length=3&status=all', self::AJAX)->assertOk()->json();
        $this->assertSame(4, $page['draw']);
        $this->assertLessThanOrEqual(3, count($page['data']));
        $this->assertArrayHasKey('recordsTotal', $page);
        if ($page['data']) {
            $this->assertArrayHasKey('card_data', $page['data'][0]);
        }
    }

    public function test_benches_filters_and_approval_count_answer()
    {
        Sanctum::actingAs($this->staff('LAB SCIENTIST'));
        $this->getJson('/api/mobile/lab/departments', self::AJAX)->assertOk()->assertJsonStructure(['success', 'data']);
        $this->getJson('/api/mobile/lab/approval-count', self::AJAX)->assertOk()->assertJsonStructure(['count']);
        $this->getJson('/api/mobile/lab/filter-hmos', self::AJAX)->assertOk();
        $this->getJson('/api/mobile/lab/v1-result-templates?type=lab', self::AJAX)->assertOk();
    }

    public function test_a_lab_number_is_suggested_for_a_patient()
    {
        $patientId = \DB::table('patients')->orderBy('id')->value('id');
        Sanctum::actingAs($this->staff('LAB SCIENTIST'));
        $r = $this->getJson("/api/mobile/lab/next-lab-number?patient_id={$patientId}", self::AJAX)->assertOk()->json();
        $this->assertNotEmpty($r['lab_number']);
        $this->getJson('/api/mobile/lab/check-lab-number?lab_number=' . urlencode($r['lab_number']) . "&patient_id={$patientId}", self::AJAX)->assertOk()->assertJsonStructure(['exists']);
    }

    public function test_only_an_approver_can_approve_a_result()
    {
        $id = \DB::table('lab_service_requests')->where('status', 5)->value('id');
        if (!$id) {
            $this->markTestSkipped('No result awaiting approval in the test database.');
        }
        Sanctum::actingAs($this->staff('LAB SCIENTIST'));
        // a lab scientist who is not a unit or department head is refused by the controller, as on the web
        $this->postJson("/api/mobile/lab/approval/{$id}/approve", [], self::AJAX)->assertStatus(403);
    }

    public function test_the_report_filters_load_their_lists()
    {
        Sanctum::actingAs($this->staff('LAB SCIENTIST'));
        foreach (['filter-doctors', 'filter-hmos', 'filter-services'] as $path) {
            $rows = $this->getJson("/api/mobile/lab/{$path}", self::AJAX)->assertOk()->json();
            $this->assertIsArray($rows, "{$path} returns a list (filter-services used to fail on an undefined variable and an unknown column).");
        }
    }

    public function test_the_meta_flags_follow_the_web_pages_approver_rule()
    {
        Sanctum::actingAs($this->staff('LAB SCIENTIST'));
        $meta = $this->getJson('/api/mobile/lab/meta', self::AJAX)->assertOk()->json();
        $this->assertFalse($meta['is_approver'], 'A lab scientist who is not a unit or department head is not an approver.');
        $this->assertArrayHasKey('requires_approval', $meta);

        Sanctum::actingAs($this->staff('ADMIN'));
        $this->assertTrue($this->getJson('/api/mobile/lab/meta', self::AJAX)->json('is_approver'));
    }
}
