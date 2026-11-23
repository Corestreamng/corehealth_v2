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
}
