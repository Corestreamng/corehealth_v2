<?php

namespace Tests\Feature\NativeClient;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Staff app, Imaging workspace (registry key radiology): guarded like the web sidebar (SUPERADMIN, ADMIN, RADIOLOGIST),
 * served by ImagingWorkbenchController, with imaging's own reports (the web page used to show the laboratory's).
 */
class ImagingMobileTest extends TestCase
{
    private const AJAX = ['X-Requested-With' => 'XMLHttpRequest'];

    protected function tearDown(): void
    {
        // the approval test changes a cached setting; never leave it behind
        \Cache::forget('app_settings');
        parent::tearDown();
    }

    private function staff(string $role): User
    {
        if (!Role::where('name', $role)->exists()) {
            $this->markTestSkipped("{$role} role missing.");
        }
        $user = User::factory()->create(['status' => 1]);
        $user->assignRole($role);

        return $user;
    }

    public function test_only_imaging_staff_and_admins_pass_the_guard()
    {
        foreach (['RADIOLOGIST', 'ADMIN'] as $role) {
            Sanctum::actingAs($this->staff($role));
            // imaging has no sample step: no sample and no completed counts
            $this->getJson('/api/mobile/radiology/queue-counts', self::AJAX)->assertOk()->assertJsonStructure(['billing', 'results', 'freeform', 'total', 'emergency', 'approval']);
        }
        foreach (['LAB SCIENTIST', 'NURSE', 'BILLER'] as $role) {
            Sanctum::actingAs($this->staff($role));
            $this->getJson('/api/mobile/radiology/queue-counts', self::AJAX)->assertStatus(403);
        }
    }

    public function test_the_queue_is_a_server_paged_datatables_page()
    {
        Sanctum::actingAs($this->staff('RADIOLOGIST'));
        $page = $this->getJson('/api/mobile/radiology/queue?draw=2&start=0&length=3&status=all', self::AJAX)->assertOk()->json();
        $this->assertSame(2, $page['draw']);
        $this->assertLessThanOrEqual(3, count($page['data']));
        $this->assertArrayHasKey('recordsTotal', $page);
    }

    public function test_imaging_reports_are_imagings_own_data()
    {
        Sanctum::actingAs($this->staff('RADIOLOGIST'));
        $rows = $this->getJson('/api/mobile/radiology/reports?draw=1&start=0&length=2', self::AJAX)->assertOk()->json();
        $this->assertSame(\App\Models\ImagingServiceRequest::count(), $rows['recordsTotal'], 'The report counts imaging requests, not laboratory ones.');

        $stats = $this->getJson('/api/mobile/radiology/statistics', self::AJAX)->assertOk()->json();
        $this->assertSame(\App\Models\ImagingServiceRequest::count(), $stats['summary']['total_requests']);
        foreach ($stats['top_doctors'] as $d) {
            $this->assertArrayHasKey('name', $d);
            $this->assertArrayNotHasKey('email', $d['doctor'] ?? [], 'A doctor is a name, not the whole user record.');
        }
    }

    public function test_report_filters_load_and_the_status_filter_applies()
    {
        Sanctum::actingAs($this->staff('RADIOLOGIST'));
        foreach (['filter-doctors', 'filter-hmos', 'filter-services', 'departments'] as $path) {
            $this->getJson("/api/mobile/radiology/{$path}", self::AJAX)->assertOk();
        }
        $all = $this->getJson('/api/mobile/radiology/statistics', self::AJAX)->json('summary.total_requests');
        $billing = $this->getJson('/api/mobile/radiology/statistics?status=1', self::AJAX)->json('summary.total_requests');
        $this->assertLessThanOrEqual($all, $billing);
    }

    public function test_meta_follows_the_imaging_approval_setting()
    {
        Sanctum::actingAs($this->staff('RADIOLOGIST'));
        $meta = $this->getJson('/api/mobile/radiology/meta', self::AJAX)->assertOk()->json();
        $this->assertFalse($meta['is_approver']);
        $this->assertSame((bool) appsettings('imaging_results_require_approval'), $meta['requires_approval']);
    }

    public function test_the_web_reports_page_uses_the_imaging_endpoints()
    {
        $js = file_get_contents(base_path('public/js/imaging-results.js'));
        $this->assertStringNotContainsString("lab.reports'", $js);
        $this->assertStringNotContainsString("lab.statistics'", $js);
        $this->assertStringContainsString("imaging.reports'", $js);
    }

    public function test_attachment_ids_are_their_positions_so_removal_works()
    {
        Sanctum::actingAs($this->staff('RADIOLOGIST'));
        $req = \App\Models\ImagingServiceRequest::first() ?? $this->markTestSkipped('No imaging request.');
        $req->attachments = [['name' => 'a.png', 'path' => 'imaging_results/a.png'], ['name' => 'b.png', 'path' => 'imaging_results/b.png']];
        $req->save();

        $list = $this->getJson("/api/mobile/radiology/requests/{$req->id}/attachments", self::AJAX)->assertOk()->json();
        $this->assertSame([0, 1], array_column($list, 'id'), 'A removal (deleted_attachments) is keyed by position; random ids made it a no-op.');
        $this->assertSame(['a.png', 'b.png'], array_column($list, 'filename'));
    }

    /** The approval chain on the imaging routes: entry waits for approval, an approver approves, rejects or reverses. */
    public function test_imaging_results_follow_the_approval_chain_when_the_hospital_requires_it()
    {
        $settings = \App\Models\ApplicationStatu::first() ?? $this->markTestSkipped('No application settings row.');
        $settings->update(['imaging_results_require_approval' => 1]);
        appsettings(null, true);

        $req = \App\Models\ImagingServiceRequest::where('is_free_form', '!=', 1)->whereNotNull('service_id')->first() ?? $this->markTestSkipped('No imaging request.');
        $req->update(['status' => 2, 'billed_by' => $req->billed_by ?? 1, 'attachments' => null]);

        $rad = $this->staff('RADIOLOGIST');
        Sanctum::actingAs($rad);
        $this->assertTrue($this->getJson('/api/mobile/radiology/meta', self::AJAX)->json('requires_approval'));

        $fields = [
            'invest_res_entry_id' => $req->id,
            'invest_res_template_version' => 1,
            'invest_res_template_submited' => '<p>No acute findings.</p>',
            'entered_by_name' => 'Test Radiologist',
            'entered_by_designation' => 'Radiologist',
            'entry_source' => 'imaging_workbench',
        ];
        $map = $this->getJson("/api/mobile/radiology/nhmis/service-mapping/{$req->service_id}?type=imaging", self::AJAX)->json();
        if (!empty($map['is_mapped'])) {
            $o = $map['supported_outcomes'][0];
            $fields['nhmis_outcome'] = $fields['nhmis_outcome_raw'] = is_array($o) ? ($o['value'] ?? $o['key']) : $o;
        }
        $this->post('/api/mobile/radiology/save-result', $fields, self::AJAX + ['Accept' => 'application/json'])->assertOk();
        $this->assertSame(5, (int) $req->fresh()->status, 'Entered, not final: it waits for an approver.');

        // a radiologist who is not a unit or department head cannot approve
        $this->postJson("/api/mobile/radiology/approval/{$req->id}/approve", [], self::AJAX)->assertStatus(403);

        $admin = $this->staff('ADMIN');
        Sanctum::actingAs($admin);
        $queue = $this->getJson('/api/mobile/radiology/approval-queue?draw=1&start=0&length=50', self::AJAX)->assertOk()->json();
        $this->assertContains($req->id, collect($queue['data'])->map(fn ($r) => (int) ($r['id'] ?? 0))->all());

        $this->postJson("/api/mobile/radiology/approval/{$req->id}/reject", ['rejection_reason' => 'Wrong side reported, please recheck'], self::AJAX)->assertOk();
        $this->assertSame(6, (int) $req->fresh()->status);

        $req->update(['status' => 5]);
        $this->postJson("/api/mobile/radiology/approval/{$req->id}/approve", [], self::AJAX)->assertOk();
        $this->assertSame(4, (int) $req->fresh()->status);
        $this->assertNotNull($req->fresh()->approved_at);

        $this->postJson("/api/mobile/radiology/approval/{$req->id}/reverse", [], self::AJAX)->assertOk();
        $this->assertNotSame(4, (int) $req->fresh()->status, 'A reversed approval is no longer final.');
    }
}
