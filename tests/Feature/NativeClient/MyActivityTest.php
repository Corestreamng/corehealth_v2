<?php

namespace Tests\Feature\NativeClient;

use App\Models\User;
use App\Services\MyActivity\MyActivityService;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The Transactions Console's "What I did": every workbench describes the stages a person works at; the
 * console shows my counts per stage. Cash views follow the web's biller rule.
 */
class MyActivityTest extends TestCase
{
    private function staff(string $role): User
    {
        if (!Role::where('name', $role)->exists()) {
            $this->markTestSkipped("{$role} role missing.");
        }
        $user = User::factory()->create(['status' => 1]);
        $user->assignRole($role);

        return $user;
    }

    private function keys(array $pipeline): array
    {
        return array_column($pipeline, 'key');
    }

    public function test_each_workbench_has_its_own_stages_and_all_my_work()
    {
        $svc = app(MyActivityService::class);
        $user = $this->staff('RECEPTIONIST');
        $from = Carbon::today();
        $to = Carbon::today()->endOfDay();

        $this->assertSame(['all', 'booked'], $this->keys($svc->run('reception', $user, $from, $to)['pipeline']));
        $this->assertSame(['all', 'billed', 'dispensed', 'returns'], $this->keys($svc->run('pharmacy', $user, $from, $to)['pipeline']));
        // Lab: a stage appears for what a person can do; approval is only offered to approvers (and only if on).
        $lab = $this->keys($svc->run('lab', $user, $from, $to)['pipeline']);
        $this->assertSame(['all', 'billed', 'sample', 'result'], $lab, 'A non-approver is not offered "Approved by me".');
        $this->assertSame(['all', 'billed', 'result'], $this->keys($svc->run('imaging', $user, $from, $to)['pipeline']), 'Imaging has no sample stage.');
    }

    public function test_an_approver_is_offered_the_approval_stage_when_approval_is_enabled()
    {
        $admin = $this->staff('ADMIN');
        $svc = app(MyActivityService::class);
        $keys = $this->keys($svc->run('lab', $admin, Carbon::today(), Carbon::today()->endOfDay())['pipeline']);
        // Admins are approvers; the stage shows when the hospital requires approval.
        $this->assertSame((bool) appsettings('lab_results_require_approval'), in_array('approved', $keys, true));
    }

    public function test_the_feed_is_the_same_for_the_app_and_says_who_may_collect()
    {
        $ajax = ['X-Requested-With' => 'XMLHttpRequest'];

        Sanctum::actingAs($this->staff('PHARMACIST'));
        $res = $this->getJson('/api/mobile/pharmacy/my-activity?workbench=pharmacy', $ajax)->assertOk();
        $this->assertFalse($res->json('can_collect'), 'A pharmacist has no biller rights: no cash views.');
        $this->assertSame(['all', 'billed', 'dispensed', 'returns'], $this->keys($res->json('pipeline')));
        $this->assertArrayHasKey('summary', $res->json());

        Sanctum::actingAs($this->staff('BILLER'));
        $this->assertTrue($this->getJson('/api/mobile/billing/my-activity?workbench=billing', $ajax)->assertOk()->json('can_collect'));
    }

    public function test_the_web_console_shows_cash_tabs_only_to_billers()
    {
        $this->actingAs($this->staff('PHARMACIST'));
        $html = $this->get('/pharmacy-workbench')->getContent();
        $this->assertStringContainsString('id="tab-my-activity"', $html);
        $this->assertStringNotContainsString('id="tab-my-transactions"', $html, 'No cash tab without biller rights.');
        $this->assertStringNotContainsString('id="tab-manage-transactions"', $html);

        $this->actingAs($this->staff('BILLER'));
        $billerHtml = $this->get('/billing-workbench')->getContent();
        $this->assertStringContainsString('id="tab-my-transactions"', $billerHtml, 'Billers keep the web standard console.');
        $this->assertStringContainsString('id="tab-my-activity"', $billerHtml);
    }
}
