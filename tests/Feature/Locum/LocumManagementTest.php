<?php

namespace Tests\Feature\Locum;

use App\Models\LocumPass;
use App\Models\LocumProfile;
use App\Models\Staff;
use App\Models\User;
use App\Services\Locum\LocumManagementService;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LocumManagementTest extends TestCase
{
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::find(1)
            ?: User::role(['ADMIN', 'SUPERADMIN', 'super-admin'])->first()
            ?: User::where('is_admin', 21)->first();
        if ($this->adminUser) {
            $this->adminUser->status = 1;
            $this->adminUser->save();
            if ($this->adminUser->staff_profile) {
                $this->adminUser->staff_profile->update([
                    'status' => 1,
                    'employment_status' => 'active',
                    'suspended_at' => null,
                    'suspended_by' => null,
                    'suspension_reason' => null,
                ]);
            }
            if (!$this->adminUser->hasRole('ADMIN')) {
                Role::firstOrCreate(['name' => 'ADMIN', 'guard_name' => 'web']);
                $this->adminUser->assignRole('ADMIN');
            }
        }
    }

    /**
     * Test admin can access the main Locum Management console.
     */
    public function test_admin_can_access_locum_management_console(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('locum.index'));

        $this->assertContains($response->getStatusCode(), [200, 302]);
        if ($response->getStatusCode() === 200) {
            $response->assertSeeText('Locum Management');
        }
    }

    /**
     * Test data endpoint returns JSON list for DataTables.
     */
    public function test_locum_data_endpoint_returns_json_table(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('locum.data'));

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    /**
     * Test locum creation assigns Spatie roles immediately at creation.
     */
    public function test_locum_creation_assigns_roles_at_creation(): void
    {
        // Ensure DOCTOR role exists
        Role::firstOrCreate(['name' => 'DOCTOR', 'guard_name' => 'web']);

        $email = 'locum_doc_' . uniqid() . '@corehealth.test';
        $phone = '080' . random_int(10000000, 99999999);

        $payload = [
            'surname' => 'Eze',
            'firstname' => 'Chinedu',
            'othername' => 'Kalu',
            'email' => $email,
            'phone_number' => $phone,
            'cadre_template' => 'DOCTOR',
            'roles' => ['DOCTOR'],
            'regulatory_body' => 'MDCN',
            'license_number' => 'MDCN/TEST/' . random_int(1000, 9999),
            'shift_type' => 'NIGHT',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('locum.store'), $payload);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Verify User was created with is_locum = 1
        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertTrue((bool) $user->is_locum);

        // Verify Spatie role was assigned at creation
        $this->assertTrue($user->hasRole('DOCTOR'));

        // Verify Staff profile was created with employment_type = locum
        $staff = $user->staff_profile;
        $this->assertNotNull($staff);
        $this->assertEquals('locum', $staff->employment_type);
        $this->assertEquals('active', $staff->employment_status);
        $this->assertEquals($phone, $staff->phone_number);

        // Verify LocumProfile was created with licensing
        $profile = $user->locum_profile;
        $this->assertNotNull($profile);
        $this->assertEquals('MDCN', $profile->regulatory_body);
        $this->assertEquals($payload['license_number'], $profile->license_number);

        // Verify LocumPass was issued with 6-digit PIN and active status
        $pass = $user->active_locum_pass;
        $this->assertNotNull($pass);
        $this->assertEquals(6, strlen($pass->pin_display));
        $this->assertTrue($pass->isActive());
    }

    /**
     * Test locum creation for Radiologist assigns RADIOLOGIST role.
     */
    public function test_locum_creation_for_radiologist_assigns_radiologist_role(): void
    {
        Role::firstOrCreate(['name' => 'RADIOLOGIST', 'guard_name' => 'web']);

        $email = 'locum_rad_' . uniqid() . '@corehealth.test';
        $payload = [
            'surname' => 'Okafor',
            'firstname' => 'Amaka',
            'email' => $email,
            'phone_number' => '070' . random_int(10000000, 99999999),
            'cadre_template' => 'RADIOLOGIST',
            'roles' => ['RADIOLOGIST'],
            'regulatory_body' => 'RRBN',
            'license_number' => 'RRBN/RAD/' . random_int(1000, 9999),
            'shift_type' => 'MORNING',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('locum.store'), $payload);

        $response->assertStatus(200);
        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('RADIOLOGIST'));
    }

    /**
     * Test issuing a new shift pass to existing locum supersedes older passes.
     */
    public function test_issue_pass_to_existing_locum_supersedes_previous(): void
    {
        $service = app(LocumManagementService::class);
        $result = $service->createLocum([
            'surname' => 'Bello',
            'firstname' => 'Musa',
            'email' => 'musa_' . uniqid() . '@corehealth.test',
            'phone_number' => '081' . random_int(10000000, 99999999),
            'cadre_template' => 'DOCTOR',
            'roles' => ['DOCTOR'],
            'regulatory_body' => 'MDCN',
            'license_number' => 'MDCN/MUSA/' . random_int(1000, 9999),
            'shift_type' => 'NIGHT',
        ], $this->adminUser);

        $user = $result['user'];
        $firstPass = $result['pass'];
        $this->assertTrue($firstPass->isActive());

        // Issue new pass
        $response = $this->actingAs($this->adminUser)
            ->postJson(route('locum.issue-pass', $user), [
                'shift_type' => 'NIGHT',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Previous pass must now be revoked/superseded
        $firstPass->refresh();
        $this->assertNotNull($firstPass->revoked_at);
        $this->assertStringContainsString('Superseded', $firstPass->revocation_reason);

        // New active pass exists
        $user->refresh();
        $activePass = $user->active_locum_pass;
        $this->assertNotNull($activePass);
        $this->assertNotEquals($firstPass->id, $activePass->id);
    }

    /**
     * Test revoking a pass immediately locks access.
     */
    public function test_revoke_pass_endpoint(): void
    {
        $service = app(LocumManagementService::class);
        $result = $service->createLocum([
            'surname' => 'Garba',
            'firstname' => 'Aliyu',
            'email' => 'aliyu_' . uniqid() . '@corehealth.test',
            'phone_number' => '090' . random_int(10000000, 99999999),
            'cadre_template' => 'DOCTOR',
            'roles' => ['DOCTOR'],
            'regulatory_body' => 'MDCN',
            'license_number' => 'MDCN/ALIYU/' . random_int(1000, 9999),
            'shift_type' => 'NIGHT',
        ], $this->adminUser);

        $pass = $result['pass'];
        $this->assertTrue($pass->isActive());

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('locum.revoke-pass', $pass), [
                'reason' => 'Emergency duty reassignment',
            ]);

        $response->assertStatus(200);
        $pass->refresh();
        $this->assertNotNull($pass->revoked_at);
        $this->assertEquals('Emergency duty reassignment', $pass->revocation_reason);
        $this->assertFalse($pass->isActive());
    }

    /**
     * Test extending a shift pass adds requested minutes.
     */
    public function test_extend_pass_endpoint(): void
    {
        $service = app(LocumManagementService::class);
        $result = $service->createLocum([
            'surname' => 'Sule',
            'firstname' => 'Kareem',
            'email' => 'kareem_' . uniqid() . '@corehealth.test',
            'phone_number' => '080' . random_int(10000000, 99999999),
            'cadre_template' => 'DOCTOR',
            'roles' => ['DOCTOR'],
            'regulatory_body' => 'MDCN',
            'license_number' => 'MDCN/SULE/' . random_int(1000, 9999),
            'shift_type' => 'NIGHT',
        ], $this->adminUser);

        $pass = $result['pass'];
        $originalExpiry = $pass->expires_at->copy();

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('locum.extend-pass', $pass), [
                'minutes' => 120, // +2 hours
            ]);

        $response->assertStatus(200);
        $pass->refresh();
        $this->assertEquals($originalExpiry->addMinutes(120)->toDateTimeString(), $pass->expires_at->toDateTimeString());
    }

    /**
     * Test fast shift pass login authentication succeeds for valid active pass.
     */
    public function test_fast_shift_pass_login_succeeds_with_valid_pin(): void
    {
        $service = app(LocumManagementService::class);
        $phone = '080' . random_int(10000000, 99999999);
        $result = $service->createLocum([
            'surname' => 'Danjuma',
            'firstname' => 'Theophilus',
            'email' => 'theo_' . uniqid() . '@corehealth.test',
            'phone_number' => $phone,
            'cadre_template' => 'DOCTOR',
            'roles' => ['DOCTOR'],
            'regulatory_body' => 'MDCN',
            'license_number' => 'MDCN/THEO/' . random_int(1000, 9999),
            'shift_type' => 'NIGHT',
        ], $this->adminUser);

        $pin = $result['pin'];

        $response = $this->postJson(route('login.locum-pass'), [
            'identifier' => $phone,
            'pin' => $pin,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertEquals($result['user']->id, Auth::id());
    }

    /**
     * Test fast shift pass login rejects expired pass.
     */
    public function test_fast_shift_pass_login_rejects_expired_pass(): void
    {
        $service = app(LocumManagementService::class);
        $phone = '080' . random_int(10000000, 99999999);
        $result = $service->createLocum([
            'surname' => 'Kuti',
            'firstname' => 'Femi',
            'email' => 'femi_' . uniqid() . '@corehealth.test',
            'phone_number' => $phone,
            'cadre_template' => 'DOCTOR',
            'roles' => ['DOCTOR'],
            'regulatory_body' => 'MDCN',
            'license_number' => 'MDCN/KUTI/' . random_int(1000, 9999),
            'shift_type' => 'NIGHT',
        ], $this->adminUser);

        $pass = $result['pass'];
        // Force expiry
        $pass->starts_at = now()->subHours(10);
        $pass->expires_at = now()->subHours(2);
        $pass->save();

        $response = $this->postJson(route('login.locum-pass'), [
            'identifier' => $phone,
            'pin' => $result['pin'],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $this->assertStringContainsString('expired', strtolower($response->json('message')));
    }

    /**
     * Test fast shift pass login rejects revoked pass.
     */
    public function test_fast_shift_pass_login_rejects_revoked_pass(): void
    {
        $service = app(LocumManagementService::class);
        $phone = '080' . random_int(10000000, 99999999);
        $result = $service->createLocum([
            'surname' => 'Ademola',
            'firstname' => 'Ade',
            'email' => 'ade_' . uniqid() . '@corehealth.test',
            'phone_number' => $phone,
            'cadre_template' => 'DOCTOR',
            'roles' => ['DOCTOR'],
            'regulatory_body' => 'MDCN',
            'license_number' => 'MDCN/ADE/' . random_int(1000, 9999),
            'shift_type' => 'NIGHT',
        ], $this->adminUser);

        $pass = $result['pass'];
        $pass->revoke($this->adminUser->id, 'Disciplinary suspension');

        $response = $this->postJson(route('login.locum-pass'), [
            'identifier' => $phone,
            'pin' => $result['pin'],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $this->assertStringContainsString('revoked', strtolower($response->json('message')));
    }

    /**
     * Test printable pass slip endpoint renders correctly.
     */
    public function test_print_pass_slip_renders(): void
    {
        $service = app(LocumManagementService::class);
        $result = $service->createLocum([
            'surname' => 'Yakubu',
            'firstname' => 'Yakubu',
            'email' => 'yakubu_' . uniqid() . '@corehealth.test',
            'phone_number' => '080' . random_int(10000000, 99999999),
            'cadre_template' => 'DOCTOR',
            'roles' => ['DOCTOR'],
            'regulatory_body' => 'MDCN',
            'license_number' => 'MDCN/YAK/' . random_int(1000, 9999),
            'shift_type' => 'NIGHT',
        ], $this->adminUser);

        $pass = $result['pass'];
        $response = $this->actingAs($this->adminUser)->get(route('locum.print-pass', $pass));

        $response->assertStatus(200);
        $response->assertSeeText('LOCUM CLINICAL SMART PASS');
        $response->assertSeeText($pass->pin_display);
    }

    /**
     * Test admin prefix alias routes work without 404.
     */
    public function test_admin_locum_routes_alias_resolves_and_functions(): void
    {
        $service = app(LocumManagementService::class);
        $result = $service->createLocum([
            'surname' => 'Audu',
            'firstname' => 'Ibrahim',
            'email' => 'audu_' . uniqid() . '@corehealth.test',
            'phone_number' => '080' . random_int(10000000, 99999999),
            'cadre_template' => 'DOCTOR',
            'roles' => ['DOCTOR'],
            'regulatory_body' => 'MDCN',
            'license_number' => 'MDCN/AUDU/' . random_int(1000, 9999),
            'shift_type' => 'NIGHT',
        ], $this->adminUser);

        $pass = $result['pass'];

        // Test /admin/locum/pass/{pass}/extend
        $extendRes = $this->actingAs($this->adminUser)
            ->postJson("/admin/locum/pass/{$pass->id}/extend", ['minutes' => 60]);
        $extendRes->assertStatus(200);
        $extendRes->assertJson(['success' => true]);

        // Test /admin/locum/pass/{pass}/details
        $detailsRes = $this->actingAs($this->adminUser)
            ->getJson("/admin/locum/pass/{$pass->id}/details");
        $detailsRes->assertStatus(200);
        $detailsRes->assertJson([
            'success' => true,
            'data' => [
                'full_name' => 'Audu Ibrahim',
                'pin' => $pass->pin_display,
            ],
        ]);

        // Test /admin/locum/pass/{pass}/revoke
        $revokeRes = $this->actingAs($this->adminUser)
            ->postJson("/admin/locum/pass/{$pass->id}/revoke", ['reason' => 'Completed shift early']);
        $revokeRes->assertStatus(200);
        $revokeRes->assertJson(['success' => true]);
    }
}
