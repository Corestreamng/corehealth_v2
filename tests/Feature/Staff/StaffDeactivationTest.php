<?php

namespace Tests\Feature\Staff;

use App\Models\Clinic;
use App\Models\HR\StaffSuspension;
use App\Models\HR\StaffTermination;
use App\Models\LocumPass;
use App\Models\LocumProfile;
use App\Models\Staff;
use App\Models\User;
use App\Services\Locum\LocumManagementService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffDeactivationTest extends TestCase
{
    /** @test */
    public function test_updating_employment_status_to_suspended_deactivates_user_and_revokes_login()
    {
        $admin = User::factory()->create(['status' => 1, 'is_admin' => 1]);

        $doctorUser = User::factory()->create([
            'status' => 1,
            'is_admin' => 21,
            'password' => Hash::make('Secret123!'),
        ]);
        $doctorStaff = Staff::firstOrCreate(
            ['user_id' => $doctorUser->id],
            ['status' => 1, 'employment_status' => 'active', 'employment_type' => 'full_time']
        );

        $response = $this->actingAs($admin)->postJson(route('staff.updateStatus', $doctorUser->id), [
            'employment_status' => 'suspended',
            'reason' => 'Gross misconduct under investigation',
            'effective_date' => now()->format('Y-m-d'),
            'suspension_end_date' => now()->addDays(14)->format('Y-m-d'),
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'status' => 'suspended', 'user_status' => 0]);

        $doctorUser->refresh();
        $doctorStaff->refresh();

        $this->assertEquals(0, $doctorUser->status);
        $this->assertEquals(0, $doctorStaff->status);
        $this->assertEquals('suspended', $doctorStaff->employment_status);
        $this->assertEquals('Gross misconduct under investigation', $doctorStaff->suspension_reason);
        $this->assertFalse($doctorStaff->canLogin());

        // Attempt logging in as suspended user
        Auth::logout();
        $loginResponse = $this->post('/login', [
            'email' => $doctorUser->email,
            'password' => 'Secret123!',
        ]);

        $loginResponse->assertSessionHasErrors('email');
        $this->assertFalse(Auth::check());
    }

    /** @test */
    public function test_suspended_logged_in_user_is_logged_out_by_middleware()
    {
        $doctorUser = User::factory()->create([
            'status' => 0,
            'is_admin' => 21,
        ]);
        $doctorStaff = Staff::firstOrCreate(
            ['user_id' => $doctorUser->id],
            ['status' => 0, 'employment_status' => 'suspended', 'suspension_reason' => 'Disciplinary action']
        );

        // Acting as doctor hits CheckSuspension middleware in web group
        $response = $this->actingAs($doctorUser)->get('/home');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertFalse(Auth::check());
    }

    /** @test */
    public function test_updating_employment_status_to_terminated_or_resigned_deactivates_user_and_blocks_login()
    {
        $admin = User::factory()->create(['status' => 1, 'is_admin' => 1]);

        $staffUser = User::factory()->create([
            'status' => 1,
            'is_admin' => 22,
            'password' => Hash::make('Secret123!'),
        ]);
        $staff = Staff::firstOrCreate(
            ['user_id' => $staffUser->id],
            ['status' => 1, 'employment_status' => 'active']
        );

        $response = $this->actingAs($admin)->postJson(route('staff.updateStatus', $staffUser->id), [
            'employment_status' => 'terminated',
            'reason' => 'Downsizing restructuring',
            'effective_date' => now()->format('Y-m-d'),
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'status' => 'terminated', 'user_status' => 0]);

        $staffUser->refresh();
        $staff->refresh();

        $this->assertEquals(0, $staffUser->status);
        $this->assertEquals(0, $staff->status);
        $this->assertEquals('terminated', $staff->employment_status);
        $this->assertFalse($staff->canLogin());

        // Attempt login
        Auth::logout();
        $loginResponse = $this->post('/login', [
            'email' => $staffUser->email,
            'password' => 'Secret123!',
        ]);

        $loginResponse->assertSessionHasErrors('email');
        $this->assertFalse(Auth::check());
    }

    /** @test */
    public function test_reinstating_staff_to_active_restores_user_status_and_clears_suspension()
    {
        $admin = User::factory()->create(['status' => 1, 'is_admin' => 1]);

        $user = User::factory()->create(['status' => 0, 'is_admin' => 21]);
        $staff = Staff::firstOrCreate(
            ['user_id' => $user->id],
            [
                'status' => 0,
                'employment_status' => 'suspended',
                'suspended_at' => now()->subDays(5),
                'suspension_reason' => 'Temporary suspension',
            ]
        );

        $response = $this->actingAs($admin)->postJson(route('staff.updateStatus', $user->id), [
            'employment_status' => 'active',
            'reason' => 'Disciplinary inquiry cleared',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'status' => 'active', 'user_status' => 1]);

        $user->refresh();
        $staff->refresh();

        $this->assertEquals(1, $user->status);
        $this->assertEquals(1, $staff->status);
        $this->assertEquals('active', $staff->employment_status);
        $this->assertNull($staff->suspended_at);
        $this->assertNull($staff->suspension_reason);
        $this->assertTrue($staff->canLogin());
        $this->assertTrue($staff->isActive());
    }

    /** @test */
    public function test_deactivating_locum_practitioner_revokes_active_shift_passes_and_blocks_pass_login()
    {
        $admin = User::factory()->create(['status' => 1, 'is_admin' => 1]);

        $locumUser = User::factory()->create([
            'status' => 1,
            'is_admin' => 21,
            'is_locum' => true,
        ]);
        $locumStaff = Staff::firstOrCreate(
            ['user_id' => $locumUser->id],
            ['status' => 1, 'employment_status' => 'active', 'employment_type' => 'locum', 'phone_number' => '08099887766']
        );
        LocumProfile::create([
            'user_id' => $locumUser->id,
            'regulatory_body' => 'MDCN',
            'license_number' => 'MDCN/TEST/12345',
            'primary_specialty' => 'General Practice',
            'cadre_template' => 'DOCTOR',
            'created_by' => $admin->id,
        ]);

        // Issue active pass
        $pass = LocumPass::create([
            'user_id' => $locumUser->id,
            'pin_display' => '654321',
            'passcode_hash' => Hash::make('654321'),
            'shift_type' => 'DAY',
            'starts_at' => now()->subHour(),
            'expires_at' => now()->addHours(6),
            'created_by' => $admin->id,
        ]);

        $this->assertTrue($pass->isActive());
        $this->assertTrue($locumUser->hasActiveLocumPass());

        // Deactivate / suspend locum practitioner via quick status
        $response = $this->actingAs($admin)->postJson(route('staff.updateStatus', $locumUser->id), [
            'employment_status' => 'suspended',
            'reason' => 'Regulatory license revoked',
        ]);

        $response->assertStatus(200);

        $pass->refresh();
        $this->assertNotNull($pass->revoked_at);
        $this->assertTrue($pass->isRevoked());

        // Pass authentication must fail
        $locumService = app(LocumManagementService::class);
        $authResult = $locumService->authenticatePass('08099887766', '654321');
        $this->assertFalse($authResult['success']);
    }

    /** @test */
    public function test_locum_practitioner_without_active_pass_is_kicked_out_by_middleware()
    {
        $locumUser = User::factory()->create([
            'status' => 1,
            'is_admin' => 21,
            'is_locum' => true,
        ]);
        Staff::firstOrCreate(
            ['user_id' => $locumUser->id],
            ['status' => 1, 'employment_status' => 'active', 'employment_type' => 'locum']
        );

        // Expired shift pass
        LocumPass::create([
            'user_id' => $locumUser->id,
            'pin_display' => '112233',
            'passcode_hash' => Hash::make('112233'),
            'shift_type' => 'NIGHT',
            'starts_at' => now()->subHours(10),
            'expires_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($locumUser)->get('/home');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertFalse(Auth::check());
    }

    /** @test */
    public function test_list_staff_endpoint_includes_all_statuses_and_filters_correctly()
    {
        $admin = User::factory()->create(['status' => 1, 'is_admin' => 1]);

        $activeUser = User::factory()->create(['status' => 1, 'surname' => 'ActiveTester']);
        Staff::firstOrCreate(['user_id' => $activeUser->id], ['status' => 1, 'employment_status' => 'active']);

        $suspendedUser = User::factory()->create(['status' => 0, 'surname' => 'SuspendedTester']);
        Staff::firstOrCreate(['user_id' => $suspendedUser->id], ['status' => 0, 'employment_status' => 'suspended']);

        // Check All Statuses
        $responseAll = $this->actingAs($admin)->getJson(route('listStaff', ['employment_status' => 'all']));
        $responseAll->assertStatus(200);

        // Check Suspended filter
        $responseSuspended = $this->actingAs($admin)->getJson(route('listStaff', ['employment_status' => 'suspended']));
        $responseSuspended->assertStatus(200);

        // Check Locum type filter
        $responseLocum = $this->actingAs($admin)->getJson(route('listStaff', ['employment_type' => 'locum']));
        $responseLocum->assertStatus(200);
    }

    /** @test */
    public function test_reception_get_doctors_by_clinic_excludes_inactive_or_suspended_doctors()
    {
        $admin = User::factory()->create(['status' => 1, 'is_admin' => 1]);
        $clinic = Clinic::first() ?: Clinic::create(['name' => 'General Clinic', 'status' => 1]);

        // 1. Active Doctor
        $activeDocUser = User::factory()->create(['status' => 1, 'surname' => 'ActiveDoc']);
        $activeDoc = Staff::updateOrCreate(
            ['user_id' => $activeDocUser->id],
            ['clinic_id' => $clinic->id, 'status' => 1, 'employment_status' => 'active']
        );

        // 2. Suspended Doctor
        $suspendedDocUser = User::factory()->create(['status' => 0, 'surname' => 'SuspendedDoc']);
        $suspendedDoc = Staff::updateOrCreate(
            ['user_id' => $suspendedDocUser->id],
            ['clinic_id' => $clinic->id, 'status' => 0, 'employment_status' => 'suspended']
        );

        // 3. On Leave Doctor
        $leaveDocUser = User::factory()->create(['status' => 1, 'surname' => 'LeaveDoc']);
        $leaveDoc = Staff::updateOrCreate(
            ['user_id' => $leaveDocUser->id],
            ['clinic_id' => $clinic->id, 'status' => 1, 'employment_status' => 'on_leave']
        );

        $response = $this->actingAs($admin)->getJson(route('reception.clinic.doctors', $clinic->id));
        $this->assertEquals(200, $response->status());

        $doctorIds = collect($response->json())->pluck('id')->toArray();
        $this->assertContains($activeDoc->id, $doctorIds);
        $this->assertNotContains($suspendedDoc->id, $doctorIds);
        $this->assertNotContains($leaveDoc->id, $doctorIds);
    }

    /** @test */
    public function test_hr_disciplinary_suspension_and_lift_flow_synchronizes_status()
    {
        $admin = User::factory()->create(['status' => 1, 'is_admin' => 1]);
        $user = User::factory()->create([
            'email' => 'hr.disciplinary.test@example.com',
            'password' => Hash::make('password123'),
            'status' => 1,
        ]);
        $staff = Staff::firstOrCreate(
            ['user_id' => $user->id],
            ['status' => 1, 'employment_status' => 'active']
        );

        // 1. Create suspension via HR model/flow
        $suspension = StaffSuspension::create([
            'staff_id' => $staff->id,
            'type' => StaffSuspension::TYPE_UNPAID,
            'start_date' => now(),
            'end_date' => now()->addDays(7),
            'reason' => 'Disciplinary investigation by HR Committee',
            'suspension_message' => 'Suspended pending investigation.',
            'issued_by' => $admin->id,
            'status' => StaffSuspension::STATUS_ACTIVE,
        ]);

        $staff->refresh();
        $user->refresh();

        $this->assertEquals('suspended', $staff->employment_status);
        $this->assertEquals(0, $staff->status);
        $this->assertEquals(0, $user->status);

        // 2. Attempt login while suspended
        $loginResponse = $this->post('/login', [
            'email' => 'hr.disciplinary.test@example.com',
            'password' => 'password123',
        ]);
        $loginResponse->assertRedirect(route('login'));
        $loginResponse->assertSessionHasErrors('email');
        $this->assertFalse(Auth::check());

        // 3. Lift suspension via HR lift workflow
        $suspension->lift($admin->id, 'Exonerated by disciplinary committee');

        $staff->refresh();
        $user->refresh();

        $this->assertEquals('active', $staff->employment_status);
        $this->assertEquals(1, $staff->status);
        $this->assertEquals(1, $user->status);

        // 4. Staff can now log in successfully
        $successResponse = $this->post('/login', [
            'email' => 'hr.disciplinary.test@example.com',
            'password' => 'password123',
        ]);
        $successResponse->assertRedirect(route('home'));
        $this->assertTrue(Auth::check());
        $this->assertEquals($user->id, Auth::id());
    }

    /** @test */
    public function test_hr_disciplinary_termination_flow_deactivates_account()
    {
        $admin = User::factory()->create(['status' => 1, 'is_admin' => 1]);
        $user = User::factory()->create([
            'email' => 'hr.termination.test@example.com',
            'password' => Hash::make('password123'),
            'status' => 1,
        ]);
        $staff = Staff::firstOrCreate(
            ['user_id' => $user->id],
            ['status' => 1, 'employment_status' => 'active']
        );

        // Process termination via HR model
        StaffTermination::create([
            'staff_id' => $staff->id,
            'type' => StaffTermination::TYPE_INVOLUNTARY,
            'reason_category' => StaffTermination::REASON_MISCONDUCT,
            'reason_details' => 'Gross misconduct determination',
            'notice_date' => now(),
            'effective_date' => now(),
            'last_working_day' => now(),
            'processed_by' => $admin->id,
        ]);

        $staff->refresh();
        $user->refresh();

        $this->assertEquals('terminated', $staff->employment_status);
        $this->assertEquals(0, $staff->status);
        $this->assertEquals(0, $user->status);

        // Attempt login - must be rejected
        $loginResponse = $this->post('/login', [
            'email' => 'hr.termination.test@example.com',
            'password' => 'password123',
        ]);
        $loginResponse->assertRedirect(route('login'));
        $loginResponse->assertSessionHasErrors('email');
        $this->assertFalse(Auth::check());
    }
}
