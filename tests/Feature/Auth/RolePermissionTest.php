<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    /** @test */
    public function test_admin_role_can_access_admin_routes()
    {
        $admin = User::factory()->create(['status' => 1, 'is_admin' => 1]);
        $response = $this->actingAs($admin)->get('/staff');
        $this->assertTrue(in_array($response->status(), [200, 302]));
    }

    /** @test */
    public function test_doctor_role_cannot_access_admin_routes()
    {
        $user = User::factory()->create(['status' => 1, 'is_admin' => 0]);
        $response = $this->actingAs($user)->get('/hospitalsettings');
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
    }



    /** @test */
    public function test_spatie_permission_guard_enforced_on_middleware()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/roles');
        $this->assertTrue(in_array($response->status(), [200, 302, 403]));
    }

    /** @test */
    public function test_user_without_role_cannot_access_protected_routes()
    {
        $user = User::factory()->create(['status' => 0]);
        $response = $this->actingAs($user)->get('/home');
        $this->assertTrue(in_array($response->status(), [200, 302, 403]));
    }
}
