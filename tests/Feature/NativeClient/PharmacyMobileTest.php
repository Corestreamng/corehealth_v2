<?php

namespace Tests\Feature\NativeClient;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Staff app, Pharmacy workspace: the mobile routes are guarded like the web sidebar (pharmacist and admins
 * only) and answer with the same data as the web workbench, including the paged prescription queue.
 */
class PharmacyMobileTest extends TestCase
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

    public function test_only_pharmacy_staff_pass_the_guard()
    {
        Sanctum::actingAs($this->staff('NURSE'));
        $this->getJson('/api/mobile/pharmacy/queue-counts', self::AJAX)->assertStatus(403);

        Sanctum::actingAs($this->staff('PHARMACIST'));
        $this->getJson('/api/mobile/pharmacy/queue-counts', self::AJAX)->assertOk()->assertJsonStructure(['total', 'unbilled', 'ready', 'hmo', 'freeform', 'emergency']);
    }

    public function test_desk_context_and_stores()
    {
        Sanctum::actingAs($this->staff('PHARMACIST'));
        $ctx = $this->getJson('/api/mobile/pharmacy/context', self::AJAX)->assertOk();
        $this->assertArrayHasKey('stores', $ctx->json());
        $this->assertArrayHasKey('can_change_store', $ctx->json());
        $this->getJson('/api/mobile/pharmacy/stores', self::AJAX)->assertOk();
    }

    public function test_the_queue_is_paged_for_the_app_and_whole_for_the_web()
    {
        Sanctum::actingAs($this->staff('PHARMACIST'));

        $page = $this->getJson('/api/mobile/pharmacy/prescription-queue?filter=all&draw=3&start=0&length=2', self::AJAX)->assertOk()->json();
        $this->assertSame(3, $page['draw']);
        $this->assertArrayHasKey('recordsTotal', $page);
        $this->assertLessThanOrEqual(2, count($page['data']));

        $whole = $this->getJson('/api/mobile/pharmacy/prescription-queue?filter=all', self::AJAX)->assertOk()->json();
        $this->assertArrayNotHasKey('data', $whole, 'Without paging parameters the list is the whole queue, as for the web page.');
    }

    public function test_returns_and_damages_feeds_and_stats()
    {
        Sanctum::actingAs($this->staff('PHARMACIST'));
        $this->getJson('/api/mobile/pharmacy/returns?draw=1&start=0&length=2', self::AJAX)->assertOk()->assertJsonStructure(['draw', 'recordsTotal', 'data']);
        $this->getJson('/api/mobile/pharmacy/returns/stats?stats_only=1', self::AJAX)->assertOk()->assertJsonStructure(['stats' => ['total', 'pending']]);
        $this->getJson('/api/mobile/pharmacy/damages?draw=1&start=0&length=2', self::AJAX)->assertOk()->assertJsonStructure(['draw', 'recordsTotal', 'data']);
        $this->getJson('/api/mobile/pharmacy/damages/stats?stats_only=1', self::AJAX)->assertOk()->assertJsonStructure(['stats' => ['total', 'pending']]);
    }

    public function test_report_dates_apply()
    {
        Sanctum::actingAs($this->staff('PHARMACIST'));
        $all = $this->getJson('/api/mobile/pharmacy/reports/statistics?date_from=2000-01-01&date_to=2100-01-01', self::AJAX)->assertOk()->json('total_dispensed');
        $none = $this->getJson('/api/mobile/pharmacy/reports/statistics?date_from=2000-01-01&date_to=2000-01-02', self::AJAX)->assertOk()->json('total_dispensed');
        $this->assertLessThanOrEqual($all, $none);
        $this->assertSame(0, (int) $none, 'A period before the hospital existed has nothing in it.');
    }
}
