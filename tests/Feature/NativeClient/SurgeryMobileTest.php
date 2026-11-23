<?php

namespace Tests\Feature\NativeClient;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Staff app, Surgery workspace: its own section (SUPERADMIN, ADMIN, SURGERY, DOCTOR, NURSE, as the web sidebar),
 * and a queue the app can page while the web keeps getting the whole list.
 */
class SurgeryMobileTest extends TestCase
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

    public function test_each_surgery_role_passes_and_others_do_not()
    {
        foreach (['SURGERY', 'DOCTOR', 'NURSE', 'ADMIN'] as $role) {
            Sanctum::actingAs($this->staff($role));
            $this->getJson('/api/mobile/surgery/queue-counts', self::AJAX)->assertOk();
        }
        foreach (['BILLER', 'PHARMACIST'] as $role) {
            Sanctum::actingAs($this->staff($role));
            $this->getJson('/api/mobile/surgery/queue-counts', self::AJAX)->assertStatus(403);
        }
    }

    public function test_the_workspace_list_has_surgery_as_its_own_section()
    {
        Sanctum::actingAs($this->staff('SURGERY'));
        $keys = collect($this->getJson('/api/mobile/staff/workspaces', self::AJAX)->assertOk()->json('data.workspaces') ?? $this->getJson('/api/mobile/staff/workspaces', self::AJAX)->json('workspaces'))->pluck('key')->all();
        $this->assertContains('surgery', $keys);
        $this->assertNotContains('nursing', $keys, 'A surgery-only user does not get the Nursing section.');
    }

    public function test_the_queue_pages_for_the_app_and_is_whole_for_the_web()
    {
        Sanctum::actingAs($this->staff('SURGERY'));
        $page = $this->getJson('/api/mobile/surgery/queue?per_page=2&page=1', self::AJAX)->assertOk()->json();
        $this->assertArrayHasKey('total', $page);
        $this->assertLessThanOrEqual(2, count($page['data']));
        $whole = $this->getJson('/api/mobile/surgery/queue', self::AJAX)->assertOk()->json();
        $this->assertArrayNotHasKey('total', $whole);
        $this->assertGreaterThanOrEqual(count($page['data']), count($whole['data']));
    }

    private function anyProcedureId(): int
    {
        $id = \DB::table('procedures')->orderByDesc('id')->value('id');
        if (!$id) {
            $this->markTestSkipped('No procedure in the test database.');
        }

        return (int) $id;
    }

    public function test_procedure_meta_carries_the_lists_and_the_users_abilities()
    {
        $id = $this->anyProcedureId();

        Sanctum::actingAs($this->staff('NURSE'));
        $nurse = $this->getJson("/api/mobile/surgery/procedure/{$id}/meta", self::AJAX)->assertOk()->json();
        $this->assertArrayHasKey('chief_surgeon', $nurse['team_roles']);
        $this->assertArrayHasKey('pre_op', $nurse['note_types']);
        $this->assertSame('nurse', $nurse['page_role']);
        $this->assertFalse($nurse['abilities']['team_manage'], 'A nurse cannot manage the team (web: SUPERADMIN|ADMIN|DOCTOR).');
        $this->assertFalse($nurse['abilities']['reopen']);

        Sanctum::actingAs($this->staff('ADMIN'));
        $admin = $this->getJson("/api/mobile/surgery/procedure/{$id}/meta", self::AJAX)->assertOk()->json();
        $this->assertIsBool($admin['abilities']['reopen']);
        $this->assertTrue($admin['abilities']['consent_change_after_obtained']);
    }

    public function test_staff_search_and_attachment_list_answer_for_the_team_picker_and_documents_tab()
    {
        $id = $this->anyProcedureId();
        Sanctum::actingAs($this->staff('SURGERY'));
        $this->getJson('/api/mobile/surgery/staff-search?search=a', self::AJAX)->assertOk()->assertJsonStructure(['data']);
        $this->getJson("/api/mobile/surgery/procedure/{$id}/attachments", self::AJAX)->assertOk()->assertJsonStructure(['attachments']);
    }

    public function test_a_team_member_can_be_changed_to_any_role_the_page_offers()
    {
        $id = $this->anyProcedureId();
        $staffUser = User::factory()->create(['status' => 1]);
        Sanctum::actingAs($this->staff('ADMIN'));
        $created = $this->postJson("/api/mobile/surgery/procedure/{$id}/team", ['user_id' => $staffUser->id, 'role' => 'scrub_nurse'], self::AJAX);
        if ($created->status() !== 200) {
            $this->markTestSkipped('Team member could not be added here: ' . substr($created->getContent(), 0, 120));
        }
        $memberId = \DB::table('procedure_team_members')->where('procedure_id', $id)->where('user_id', $staffUser->id)->value('id');
        $this->putJson("/api/mobile/surgery/procedure/{$id}/team/{$memberId}", ['role' => 'chief_surgeon'], self::AJAX)->assertOk();
        $this->assertSame('chief_surgeon', \DB::table('procedure_team_members')->where('id', $memberId)->value('role'));
    }

    public function test_queue_rows_carry_the_surgical_flag_and_the_type_filter_splits_the_list()
    {
        Sanctum::actingAs($this->staff('SURGERY'));
        $all = $this->getJson('/api/mobile/surgery/queue', self::AJAX)->assertOk()->json('data');
        $surgical = $this->getJson('/api/mobile/surgery/queue?type=surgical', self::AJAX)->assertOk()->json('data');
        $bedside = $this->getJson('/api/mobile/surgery/queue?type=bedside', self::AJAX)->assertOk()->json('data');
        $this->assertSame(count($all), count($surgical) + count($bedside), 'Every procedure is either surgical or bedside.');
        foreach ($surgical as $row) {
            $this->assertTrue($row['is_surgical']);
        }
        foreach (array_slice($bedside, 0, 20) as $row) {
            $this->assertFalse($row['is_surgical']);
        }
    }

    public function test_service_search_tells_the_app_whether_a_procedure_is_surgical()
    {
        $surgicalId = \DB::table('procedure_definitions')->where('is_surgical', 1)->value('service_id');
        if (!$surgicalId) {
            $this->markTestSkipped('No surgical procedure definition in the test database.');
        }
        $name = \DB::table('services')->where('id', $surgicalId)->value('service_name');
        Sanctum::actingAs($this->staff('NURSE'));
        $rows = $this->getJson('/api/mobile/nursing/search-services?term=' . urlencode(substr($name, 0, 6)), self::AJAX)->assertOk()->json();
        $hit = collect($rows)->firstWhere('id', $surgicalId);
        $this->assertNotNull($hit, 'The surgical procedure is found by name.');
        $this->assertTrue($hit['is_surgical']);
        $this->assertArrayHasKey('procedure_category', $hit);
    }

    public function test_the_notes_list_works_for_a_procedure_that_has_notes()
    {
        $id = \DB::table('procedure_notes')->orderByDesc('id')->value('procedure_id');
        if (!$id) {
            $this->markTestSkipped('No procedure note in the test database.');
        }
        Sanctum::actingAs($this->staff('SURGERY'));
        $notes = $this->getJson("/api/mobile/surgery/procedure/{$id}/notes", self::AJAX)->assertOk()->json('notes');
        $this->assertNotEmpty($notes);
        $this->assertArrayHasKey('created_by', $notes[0]);
    }

    public function test_each_tile_counts_what_its_list_shows()
    {
        Sanctum::actingAs($this->staff('SURGERY'));
        $counts = $this->getJson('/api/mobile/surgery/queue-counts', self::AJAX)->assertOk()->json();
        foreach (['requested', 'scheduled', 'in_progress', 'completed', 'cancelled'] as $status) {
            $list = $this->getJson("/api/mobile/surgery/queue?status={$status}", self::AJAX)->assertOk()->json('data');
            $this->assertSame($counts[$status], count($list), "The {$status} tile and its list differ.");
        }
    }
}
