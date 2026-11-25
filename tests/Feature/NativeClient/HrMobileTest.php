<?php

namespace Tests\Feature\NativeClient;

use App\Models\HR\LeaveType;
use App\Models\Staff;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The HR and staff self-service routes of the staff app answer from the same controllers as the web pages,
 * with the same permission guards, and give JSON where the web gives a view.
 */
class HrMobileTest extends TestCase
{
    private const AJAX = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];

    private function admin(): User
    {
        $u = User::factory()->create(['status' => 1]);
        $u->assignRole('SUPERADMIN');
        Sanctum::actingAs($u);

        return $u;
    }

    public function test_hr_lists_and_dashboards_answer_json()
    {
        $this->admin();
        foreach (['workbench', 'workbench/stats', 'staff-registry', 'staff-registry/alerts', 'cadres', 'grade-levels', 'pay-heads', 'units', 'leave-calendar'] as $p) {
            $r = $this->getJson("/api/mobile/hr/{$p}", self::AJAX);
            $this->assertSame(200, $r->status(), "{$p}: " . substr($r->getContent(), 0, 200));
        }
        foreach (['leave-types', 'leave-requests', 'leave-balances', 'disciplinary', 'suspensions', 'terminations', 'payroll', 'salary-profiles', 'promotions', 'qualifications', 'trainings', 'medical-exams', 'follow-ups', 'staff-registry/data'] as $p) {
            $r = $this->getJson("/api/mobile/hr/{$p}?draw=1&start=0&length=5&search[regex]=false", self::AJAX);
            $this->assertSame(200, $r->status(), "{$p}: " . substr($r->getContent(), 0, 200));
            $this->assertArrayHasKey('data', $r->json(), $p);
        }
    }

    public function test_leave_request_rows_carry_their_id_for_the_app()
    {
        $this->admin();
        $row = $this->getJson('/api/mobile/hr/leave-requests?draw=1&start=0&length=1', self::AJAX)->json('data.0');
        if ($row) {
            $this->assertArrayHasKey('id', $row);
            $this->assertArrayHasKey('status', $row);
        }
        $this->assertArrayHasKey('all', $this->getJson('/api/mobile/hr/pay-heads', self::AJAX)->json());
    }

    public function test_a_user_without_the_hr_permission_is_refused()
    {
        $u = User::factory()->create(['status' => 1]);
        Sanctum::actingAs($u);
        $this->getJson('/api/mobile/hr/workbench', self::AJAX)->assertStatus(403);
        $this->getJson('/api/mobile/hr/cadres', self::AJAX)->assertStatus(403);
    }

    public function test_cadres_can_be_created_changed_and_removed_through_the_app_routes()
    {
        $this->admin();
        $code = 'T' . random_int(100, 999);
        $this->postJson('/api/mobile/hr/cadres', ['name' => 'Test cadre ' . $code, 'code' => $code], self::AJAX)->assertSuccessful();
        $id = collect($this->getJson('/api/mobile/hr/cadres', self::AJAX)->json('cadres'))->firstWhere('code', $code)['id'];
        $this->putJson("/api/mobile/hr/cadres/{$id}", ['name' => 'Renamed ' . $code, 'code' => $code], self::AJAX)->assertSuccessful();
        $this->deleteJson("/api/mobile/hr/cadres/{$id}", [], self::AJAX)->assertSuccessful();
    }

    public function test_self_service_needs_the_ess_permission_and_answers_for_the_signed_in_staff()
    {
        $u = User::factory()->create(['status' => 1]);
        Sanctum::actingAs($u);
        $this->getJson('/api/mobile/hr/ess', self::AJAX)->assertStatus(403);

        $staff = Staff::query()->whereNotNull('user_id')->first();
        $this->assertNotNull($staff);
        $owner = User::find($staff->user_id);
        $owner->givePermissionTo(Permission::firstOrCreate(['name' => 'ess.access', 'guard_name' => 'web']));
        Sanctum::actingAs($owner);
        foreach (['ess', 'ess/my-leave', 'ess/my-payslips', 'ess/my-disciplinary', 'ess/my-profile'] as $p) {
            $r = $this->getJson("/api/mobile/hr/{$p}", self::AJAX);
            $this->assertSame(200, $r->status(), "{$p}: " . substr($r->getContent(), 0, 200));
            $this->assertArrayHasKey('staff', $r->json(), $p);
        }
    }

    public function test_self_service_leave_request_is_validated_by_the_web_rules()
    {
        $staff = Staff::query()->whereNotNull('user_id')->first();
        $owner = User::find($staff->user_id);
        $owner->givePermissionTo(Permission::firstOrCreate(['name' => 'ess.access', 'guard_name' => 'web']));
        Sanctum::actingAs($owner);
        $this->postJson('/api/mobile/hr/ess/my-leave/request', [], self::AJAX)->assertStatus(422);
        $type = LeaveType::query()->value('id');
        $r = $this->postJson('/api/mobile/hr/ess/my-leave/request', ['leave_type_id' => $type, 'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->toDateString(), 'reason' => 'x'], self::AJAX);
        $this->assertSame(422, $r->status(), 'a start date in the past is refused like on the web');
    }

    public function test_detail_pages_answer_with_what_the_app_shows()
    {
        $this->admin();
        $slips = \App\Models\HR\PayrollBatch::query()->value('id');
        if ($slips) {
            $this->getJson("/api/mobile/hr/payroll/{$slips}", self::AJAX)->assertOk()->assertJsonStructure(['id', 'status', 'total_net']);
            $this->getJson("/api/mobile/hr/payroll/{$slips}/payslips", self::AJAX)->assertOk()->assertJsonStructure(['payslips']);
        }
        $profile = \App\Models\HR\StaffSalaryProfile::query()->value('id');
        if ($profile) {
            $this->getJson("/api/mobile/hr/salary-profiles/{$profile}", self::AJAX)->assertOk()->assertJsonStructure(['items', 'basic_salary']);
            $this->getJson("/api/mobile/hr/salary-profiles/{$profile}/edit", self::AJAX)->assertOk()->assertJsonStructure(['payHeads']);
        }
        $lr = \App\Models\HR\LeaveRequest::query()->value('id');
        if ($lr) {
            $this->getJson("/api/mobile/hr/leave-requests/{$lr}", self::AJAX)->assertOk()->assertJsonStructure(['leaveRequest' => ['staff' => ['user']]]);
        }
        $staff = Staff::query()->value('id');
        $this->getJson("/api/mobile/hr/staff-registry/staff/{$staff}/detail", self::AJAX)->assertOk()->assertJsonStructure(['staff_id', 'employee_id']);
    }

    public function test_salary_profile_and_payroll_batch_can_be_created_from_the_app()
    {
        $this->admin();
        $staff = Staff::query()->whereDoesntHave('salaryProfiles')->first();
        $this->assertNotNull($staff, 'a staff member without a salary profile');
        $head = \App\Models\HR\PayHead::query()->where('is_active', true)->first();
        $r = $this->postJson('/api/mobile/hr/salary-profiles', [
            'staff_id' => $staff->id, 'basic_salary' => 120000, 'effective_date' => now()->toDateString(), 'pay_frequency' => 'monthly',
            'items' => [['pay_head_id' => $head->id, 'calculation_type' => 'fixed', 'calculation_base' => 'basic_salary', 'value' => 5000]],
        ], self::AJAX);
        $this->assertContains($r->status(), [200, 201], substr($r->getContent(), 0, 300));

        $month = now()->addYears(5)->format('Y-m');
        $r = $this->postJson('/api/mobile/hr/payroll', [
            'pay_period' => $month, 'work_period_start' => "{$month}-01", 'work_period_end' => "{$month}-28", 'selection_mode' => 'custom', 'staff_ids' => [$staff->id], 'duplicate_action' => 'skip',
        ], self::AJAX);
        $this->assertContains($r->status(), [200, 201], substr($r->getContent(), 0, 300));

        $month2 = now()->addYears(6)->format('Y-m');
        $r = $this->postJson('/api/mobile/hr/payroll', [
            'pay_period' => $month2, 'work_period_start' => "{$month2}-01", 'work_period_end' => "{$month2}-28", 'selection_mode' => 'department', 'departments' => [(string) $staff->department_id], 'duplicate_action' => 'skip',
        ], self::AJAX);
        $this->assertContains($r->status(), [200, 201], 'department mode: ' . substr($r->getContent(), 0, 300));
    }

    public function test_master_import_takes_a_spreadsheet_through_the_app_route()
    {
        $this->admin();
        $this->getJson('/api/mobile/hr/master-import', self::AJAX)->assertOk()->assertJsonStructure(['modes']);
        $this->post('/api/mobile/hr/master-import/import', ['mode' => 'both'], self::AJAX)->assertStatus(422);
        $csv = \Illuminate\Http\UploadedFile::fake()->createWithContent('staff.csv', "S/NO,NAME,EMPLOYEE NUMBER\n");
        $r = $this->post('/api/mobile/hr/master-import/import', ['mode' => 'both', 'file' => $csv], self::AJAX);
        $this->assertSame(200, $r->status(), substr($r->getContent(), 0, 300));
        $this->assertArrayHasKey('stats', $r->json());
    }

    public function test_calendars_and_team_approvals_answer_for_the_right_people()
    {
        $this->admin();
        foreach (['leave-calendar/events', 'leave-calendar/stats', 'leave-calendar/on-leave-today', 'tracking/calendar/events', 'tracking/calendar', 'reports', 'master-import'] as $p) {
            $r = $this->getJson("/api/mobile/hr/{$p}?start=2026-01-01&end=2026-12-31", self::AJAX);
            $this->assertSame(200, $r->status(), "{$p}: " . substr($r->getContent(), 0, 200));
        }
        $staff = Staff::query()->whereNotNull('user_id')->first();
        $owner = User::find($staff->user_id);
        $owner->syncRoles([]);
        $owner->syncPermissions([Permission::firstOrCreate(['name' => 'ess.access', 'guard_name' => 'web'])]);
        Sanctum::actingAs($owner);
        $this->getJson('/api/mobile/hr/ess/team-approvals', self::AJAX)->assertStatus(403);
        $owner->givePermissionTo(Permission::firstOrCreate(['name' => 'leave-request.supervisor-approve', 'guard_name' => 'web']));
        $r = $this->getJson('/api/mobile/hr/ess/team-approvals', self::AJAX);
        $this->assertSame(200, $r->status(), substr($r->getContent(), 0, 300));
        $this->assertArrayHasKey('pendingRequests', $r->json());
        $this->assertSame(200, $this->getJson('/api/mobile/hr/ess/team-calendar/events?start=2026-01-01&end=2026-12-31', self::AJAX)->status());
    }
}
