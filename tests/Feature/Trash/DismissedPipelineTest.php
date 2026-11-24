<?php

namespace Tests\Feature\Trash;

use App\Models\ImagingServiceRequest;
use App\Models\LabServiceRequest;
use App\Models\ProductRequest;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Lab, Imaging and Pharmacy share one dismissed pipeline: dismiss (who, when, why) -> browse (paged, searchable)
 * -> restore to the stage it was at. Native routes are the web controllers' own.
 */
class DismissedPipelineTest extends TestCase
{
    private const AJAX = ['X-Requested-With' => 'XMLHttpRequest'];

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 1]);
        $user->assignRole(Role::where('name', 'SUPERADMIN')->exists() ? 'SUPERADMIN' : 'ADMIN');
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_imaging_dismiss_records_who_why_and_restore_returns_the_billed_stage()
    {
        $admin = $this->admin();
        $req = ImagingServiceRequest::whereIn('status', [1, 2])->whereNotNull('billed_by')->first() ?? $this->markTestSkipped('No billed imaging request.');
        $req->update(['status' => 2]);

        $this->postJson("/api/mobile/radiology/requests/{$req->id}/dismiss", ['reason' => 'Wrong patient selected'], self::AJAX)->assertOk();
        $row = $this->getJson('/api/mobile/radiology/dismissed-requests?per_page=5&search=', self::AJAX)->assertOk()->json();
        $this->assertArrayHasKey('total', $row);
        $mine = collect($row['data'])->firstWhere('id', $req->id);
        $this->assertNotNull($mine, 'The dismissed request is listed.');
        $this->assertSame('Wrong patient selected', $mine['reason']);
        $this->assertNotEmpty($mine['by_name']);

        $this->postJson("/api/mobile/radiology/requests/{$req->id}/undismiss", [], self::AJAX)->assertOk();
        $fresh = $req->fresh();
        $this->assertSame(2, (int) $fresh->status, 'A billed scan returns to awaiting result, not to billing.');
        $this->assertNull($fresh->dismissed_at);
    }

    public function test_imaging_delete_and_restore_round_trip()
    {
        $this->admin();
        $req = ImagingServiceRequest::whereNotNull('billed_by')->first() ?? $this->markTestSkipped('No imaging request.');

        $this->deleteJson("/api/mobile/radiology/requests/{$req->id}", ['reason' => 'Duplicate request entered'], self::AJAX)->assertOk();
        $list = $this->getJson('/api/mobile/radiology/deleted-requests?per_page=100', self::AJAX)->json('data');
        $this->assertSame('Duplicate request entered', collect($list)->firstWhere('id', $req->id)['reason'] ?? null);
        $this->postJson("/api/mobile/radiology/requests/{$req->id}/restore", [], self::AJAX)->assertOk();
        $this->assertNull(ImagingServiceRequest::find($req->id)->deletion_reason);
        $this->postJson("/api/mobile/radiology/requests/{$req->id}/restore", [], self::AJAX)->assertStatus(400);
    }

    public function test_a_lab_request_dismissed_in_bulk_appears_in_the_dismissed_list()
    {
        $this->admin();
        $req = LabServiceRequest::whereIn('status', [1, 2])->first() ?? $this->markTestSkipped('No open lab request.');

        $this->postJson('/api/mobile/lab/dismiss-requests', ['request_ids' => [$req->id], 'patient_id' => $req->patient_id, 'reason' => 'Patient left before sampling'], self::AJAX)->assertOk();
        $list = $this->getJson('/api/mobile/lab/dismissed-requests?per_page=100', self::AJAX)->json('data');
        $mine = collect($list)->firstWhere('id', $req->id);
        $this->assertNotNull($mine, 'Bulk dismissals were invisible before: no dismissed_at was recorded.');
        $this->assertSame('Patient left before sampling', $mine['dismiss_reason']);
        $this->assertNotEmpty($mine['by_name']);
        $this->postJson("/api/mobile/lab/requests/{$req->id}/undismiss", [], self::AJAX)->assertOk();
    }

    public function test_pharmacy_dismiss_lists_with_reason_and_restores_to_its_stage()
    {
        $this->admin();
        $pr = ProductRequest::whereIn('status', [1, 2])->first() ?? $this->markTestSkipped('No open prescription.');

        $this->postJson('/api/mobile/pharmacy/dismiss', ['prescription_ids' => [$pr->id], 'reason' => 'Doctor changed the order'], self::AJAX)->assertOk();
        $page = $this->getJson('/api/mobile/pharmacy/dismissed?per_page=100', self::AJAX)->assertOk()->json();
        $mine = collect($page['data'])->firstWhere('id', $pr->id);
        $this->assertNotNull($mine);
        $this->assertSame('Doctor changed the order', $mine['reason']);
        $this->assertNotEmpty($mine['by_name']);
        $this->assertArrayHasKey('product_name', $mine);

        $this->postJson('/api/mobile/pharmacy/restore-dismissed', ['prescription_ids' => [$pr->id]], self::AJAX)->assertOk();
        $fresh = ProductRequest::find($pr->id);
        $this->assertSame($pr->billed_by ? 2 : 1, (int) $fresh->status, 'Back at the stage it was.');
        $this->assertNull($fresh->dismiss_reason);
        $this->postJson('/api/mobile/pharmacy/restore-dismissed', ['prescription_ids' => [$pr->id]], self::AJAX)->assertStatus(422);
    }

    public function test_a_dispensed_prescription_cannot_be_dismissed_and_paging_caps_the_page()
    {
        $this->admin();
        $pr = ProductRequest::where('status', 3)->first() ?? $this->markTestSkipped('No dispensed prescription.');
        $res = $this->postJson('/api/mobile/pharmacy/dismiss', ['prescription_ids' => [$pr->id]], self::AJAX)->assertOk()->json();
        $this->assertSame(0, $res['dismissed_count']);
        $this->assertSame(3, (int) ProductRequest::find($pr->id)->status);
        $page = $this->getJson('/api/mobile/pharmacy/dismissed?per_page=1000', self::AJAX)->json();
        $this->assertLessThanOrEqual(100, count($page['data']));
    }

    public function test_the_guards_follow_the_workspaces()
    {
        $nurse = User::factory()->create(['status' => 1]);
        $nurse->assignRole('NURSE');
        Sanctum::actingAs($nurse);
        foreach (['/api/mobile/lab/dismissed-requests', '/api/mobile/radiology/dismissed-requests', '/api/mobile/pharmacy/dismissed'] as $url) {
            $this->getJson($url, self::AJAX)->assertStatus(403);
        }
    }

    public function test_the_webs_own_dismiss_route_also_records_who_and_why()
    {
        $this->admin();
        $pr = ProductRequest::whereIn('status', [1, 2])->first() ?? $this->markTestSkipped('No open prescription.');
        $this->postJson('/product-dismiss-patient-ajax', ['prescription_ids' => [$pr->id], 'patient_id' => $pr->patient_id, 'reason' => 'Out of stock for the week'], self::AJAX)->assertOk();
        $mine = collect($this->getJson('/api/mobile/pharmacy/dismissed?per_page=100', self::AJAX)->json('data'))->firstWhere('id', $pr->id);
        $this->assertSame('Out of stock for the week', $mine['reason'] ?? null);
    }
}
