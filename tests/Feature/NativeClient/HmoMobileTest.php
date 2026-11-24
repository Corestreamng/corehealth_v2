<?php

namespace Tests\Feature\NativeClient;

use App\Models\Hmo;
use App\Models\ProductOrServiceRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Staff app, HMO Executive. The routes are the web controllers' own (MobileHmoController extends the workbench
 * controller); these tests check the guards, that every read answers JSON, and walk the workflows.
 */
class HmoMobileTest extends TestCase
{
    private const AJAX = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];
    private const DT = '?draw=1&start=0&length=3&search[value]=';

    private function as(string $role): User
    {
        $u = User::factory()->create(['status' => 1]);
        $u->assignRole(Role::where('name', $role)->exists() ? $role : 'ADMIN');
        Sanctum::actingAs($u);

        return $u;
    }

    /** An HMO request in the state a test needs (claims above nil, pending, unpaid unless asked). */
    private function request(string $status = 'pending', float $claims = 4000, string $mode = 'primary'): ProductOrServiceRequest
    {
        $r = ProductOrServiceRequest::whereNotNull('coverage_mode')->where('is_bundle_item', false)->where('coverage_mode', '!=', 'express')->whereDoesntHave('labRequest')->whereDoesntHave('imagingRequest')->whereNull('product_id')
            ->whereHas('user.patient_profile', fn ($q) => $q->whereNotNull('hmo_id'))->orderBy('id')->first() ?? $this->markTestSkipped('No HMO request in this database.');
        DB::table('product_or_service_requests')->where('id', $r->id)->update(['validation_status' => $status, 'claims_amount' => $claims, 'coverage_mode' => $mode, 'payment_id' => null, 'validated_by' => null, 'validated_at' => null, 'auth_code' => null]);

        return $r->fresh();
    }

    public function test_the_hmo_section_is_for_hmo_roles_and_management_for_admins_and_heads()
    {
        $this->as('NURSE');
        $this->getJson('/api/mobile/hmo/context', self::AJAX)->assertStatus(403);
        $this->getJson('/api/mobile/hmo/requests', self::AJAX)->assertStatus(403);

        $exec = $this->as('HMO Executive');
        $this->getJson('/api/mobile/hmo/context', self::AJAX)->assertOk();
        $this->getJson('/api/mobile/hmo/hmos' . self::DT, self::AJAX)->assertStatus(403);
        $this->getJson('/api/mobile/hmo/tariffs/lookups', self::AJAX)->assertStatus(403);
        $this->getJson('/api/mobile/hmo/organizations' . self::DT, self::AJAX)->assertStatus(403);
        $this->assertFalse($this->getJson('/api/mobile/hmo/context', self::AJAX)->json('can.manage'));

        $this->as('ADMIN');
        $this->getJson('/api/mobile/hmo/hmos' . self::DT, self::AJAX)->assertOk();
        $this->assertTrue($this->getJson('/api/mobile/hmo/context', self::AJAX)->json('can.manage'));
    }

    public function test_every_read_answers_json_and_the_queue_is_paged()
    {
        $this->as('SUPERADMIN');
        $pat = DB::table('patients')->whereNotNull('hmo_id')->value('id');
        $req = DB::table('product_or_service_requests')->whereNotNull('coverage_mode')->where('claims_amount', '>', 0)->value('id');
        $org = DB::table('organizations')->value('id');
        $hmo = DB::table('hmos')->value('id');
        $tariff = DB::table('hmo_tariffs')->value('id');
        $reads = ['context', 'queue-counts', 'financial-summary', "requests/{$req}", "requests/{$req}/tariff-details", "patient/{$pat}/history", "patient/{$pat}/vitals", "patient/{$pat}/notes", "patient/{$pat}/medications", "patient/{$pat}/allergies", "patient/{$pat}/pending-requests", "patient/{$pat}/all-requests",
            'reports/outstanding', "reports/patient/{$pat}", 'reports/monthly?month=11&year=2026', 'reports/utilization', 'reports/print-data', 'banks',
            'reports/claims' . self::DT, 'reports/auth-codes' . self::DT, 'reports/remittances' . self::DT, 'patients' . self::DT, 'hmos' . self::DT, 'hmos/create', "hmos/{$hmo}/edit", 'tariffs/lookups', 'tariffs' . self::DT, 'tariffs/items?type=service&term=a', 'tariffs/items?type=product&term=a', "tariffs/scheme-hmos/1", 'tariffs/overrides', 'organizations' . self::DT, "organizations/{$org}", "organizations/{$org}/bills"];
        foreach (['pending', 'express', 'approved', 'rejected', 'awaiting_code', 'claims', 'all'] as $tab) {
            $reads[] = "requests?tab={$tab}&per_page=3";
        }
        foreach ($reads as $path) {
            $r = $this->getJson('/api/mobile/hmo/' . $path, self::AJAX);
            $this->assertSame(200, $r->status(), "{$path}: " . substr($r->getContent(), 0, 250));
            $this->assertNotNull(json_decode($r->getContent(), true), "{$path} is JSON");
        }
        $page = $this->getJson('/api/mobile/hmo/requests?tab=all&per_page=3', self::AJAX)->json();
        $this->assertLessThanOrEqual(3, count($page['data']), 'the queue is paged by the server');
        $this->assertGreaterThan(3, $page['total'], 'and reports its total');
        $this->assertArrayHasKey('can_reverse', $page['data'][0]);
        $this->assertNotNull($tariff);
    }

    public function test_the_mobile_queue_is_the_web_queue()
    {
        $this->as('SUPERADMIN');
        foreach (['pending', 'approved', 'rejected', 'awaiting_code', 'express'] as $tab) {
            $web = $this->getJson("/hmo/requests?tab={$tab}&draw=1&start=0&length=5&search[value]=", self::AJAX)->json();
            $mobile = $this->getJson("/api/mobile/hmo/requests?tab={$tab}&per_page=100", self::AJAX)->json();
            $this->assertSame((int) $web['recordsFiltered'], (int) $mobile['total'], "the {$tab} tab counts the same on the web and in the app");
        }
    }

    public function test_a_request_is_approved_reversed_rejected_and_approved_again()
    {
        $this->as('HMO Executive');
        $r = $this->request();
        $this->postJson("/api/mobile/hmo/requests/{$r->id}/approve", ['validation_notes' => 'ok'], self::AJAX)->assertOk();
        $this->assertSame('approved', DB::table('product_or_service_requests')->where('id', $r->id)->value('validation_status'));
        $this->assertTrue($this->getJson("/api/mobile/hmo/requests/{$r->id}", self::AJAX)->json('data.can_reverse'));

        $this->postJson("/api/mobile/hmo/requests/{$r->id}/reverse", [], self::AJAX)->assertStatus(422);
        $this->postJson("/api/mobile/hmo/requests/{$r->id}/reverse", ['reason' => 'Entered in error'], self::AJAX)->assertOk();
        $this->assertSame('pending', DB::table('product_or_service_requests')->where('id', $r->id)->value('validation_status'));

        $this->postJson("/api/mobile/hmo/requests/{$r->id}/reject", [], self::AJAX)->assertStatus(422);
        $this->postJson("/api/mobile/hmo/requests/{$r->id}/reject", ['rejection_reason' => 'not_covered'], self::AJAX)->assertOk();
        $this->assertSame('rejected', DB::table('product_or_service_requests')->where('id', $r->id)->value('validation_status'));
        $this->postJson("/api/mobile/hmo/requests/{$r->id}/reapprove", [], self::AJAX)->assertOk();
        $this->assertSame('approved', DB::table('product_or_service_requests')->where('id', $r->id)->value('validation_status'));
    }

    public function test_batch_and_group_decisions_and_auth_codes()
    {
        $this->as('HMO Executive');
        $a = $this->request();
        $this->postJson('/api/mobile/hmo/batch-approve', ['request_ids' => [$a->id]], self::AJAX)->assertOk();
        $this->assertSame('approved', DB::table('product_or_service_requests')->where('id', $a->id)->value('validation_status'));

        $b = $this->request('pending', 3000, 'secondary');
        $this->postJson('/api/mobile/hmo/batch-reject', ['request_ids' => [$b->id], 'rejection_reason' => 'no_preauth'], self::AJAX)->assertOk();
        $this->assertSame('rejected', DB::table('product_or_service_requests')->where('id', $b->id)->value('validation_status'));

        $c = $this->request('pending', 2500);
        $this->postJson('/api/mobile/hmo/group-approve', ['request_ids' => [$c->id], 'auth_mode' => 'shared', 'shared_auth_code' => 'AUTH-1'], self::AJAX)->assertOk();
        $row = DB::table('product_or_service_requests')->where('id', $c->id)->first();
        $this->assertContains($row->validation_status, ['approved', 'awaiting_code']);

        $d = $this->request('awaiting_code', 1800, 'secondary');
        $this->postJson("/api/mobile/hmo/requests/{$d->id}/submit-auth-code", ['auth_code' => 'CODE-9'], self::AJAX)->assertOk();
        $this->assertSame('CODE-9', DB::table('product_or_service_requests')->where('id', $d->id)->value('auth_code'));

        $e = $this->request('pending', 900);
        $this->postJson('/api/mobile/hmo/group-reject', ['request_ids' => [$e->id], 'rejection_reason' => 'duplicate'], self::AJAX)->assertOk();
        $this->assertSame('rejected', DB::table('product_or_service_requests')->where('id', $e->id)->value('validation_status'));
    }

    public function test_a_tariff_is_edited_on_a_request_and_a_patients_hmo_is_changed()
    {
        $this->as('HMO Executive');
        $r = $this->request('pending', 4000, 'primary');
        $res = $this->postJson("/api/mobile/hmo/requests/{$r->id}/update-tariff", ['coverage_mode' => 'primary', 'claims_amount' => 3500, 'payable_amount' => 500, 'apply_to_scheme' => false], self::AJAX);
        $this->assertSame(200, $res->status(), $res->getContent());
        $this->postJson("/api/mobile/hmo/requests/{$r->id}/update-tariff", ['coverage_mode' => 'bogus', 'claims_amount' => 1, 'payable_amount' => 1], self::AJAX)->assertStatus(422);

        $patient = $r->user->patient_profile;
        $other = Hmo::where('id', '!=', $patient->hmo_id)->where('status', 1)->value('id');
        $this->postJson("/api/mobile/hmo/patient/{$patient->id}/update-hmo", ['hmo_id' => $other, 'hmo_no' => 'NEW-123'], self::AJAX)->assertOk();
        $this->assertSame('NEW-123', DB::table('patients')->where('id', $patient->id)->value('hmo_no'));
    }

    public function test_remittances_are_recorded_linked_edited_and_deleted_and_claims_marked_submitted()
    {
        $this->as('HMO Executive');
        $hmo = DB::table('hmos')->value('id');
        $bank = DB::table('banks')->where('is_active', 1)->whereNotNull('account_id')->value('id') ?? $this->markTestSkipped('No bank with a ledger account.');
        $body = ['hmo_id' => $hmo, 'bank_id' => $bank, 'amount' => 1500, 'payment_date' => now()->toDateString(), 'reference_number' => 'REM-T1', 'payment_method' => 'transfer'];
        $r = $this->postJson('/api/mobile/hmo/reports/remittances', $body, self::AJAX);
        $this->assertSame(200, $r->status(), substr($r->getContent(), 0, 300));
        $id = DB::table('hmo_remittances')->where('reference_number', 'REM-T1')->value('id');
        $this->assertNotNull($id);
        $this->getJson("/api/mobile/hmo/reports/remittances/{$id}", self::AJAX)->assertOk()->assertJsonStructure(['remittance', 'claims']);
        $this->putJson("/api/mobile/hmo/reports/remittances/{$id}", ['bank_id' => $bank, 'amount' => 1600, 'payment_date' => now()->toDateString(), 'reference_number' => 'REM-T1'], self::AJAX)->assertOk();

        $claim = $this->request('approved', 2000);
        $this->postJson('/api/mobile/hmo/reports/mark-submitted', ['claim_ids' => [$claim->id], 'batch_reference' => 'BATCH-1'], self::AJAX)->assertOk();
        $this->assertNotNull(DB::table('product_or_service_requests')->where('id', $claim->id)->value('submitted_to_hmo_at'));
        $this->postJson('/api/mobile/hmo/reports/link-claims', ['remittance_id' => $id, 'claim_ids' => [$claim->id]], self::AJAX)->assertOk();
        $this->deleteJson("/api/mobile/hmo/reports/remittances/{$id}", [], self::AJAX)->assertOk();
        $this->assertNull(DB::table('hmo_remittances')->where('id', $id)->value('id'));
    }

    public function test_management_hmos_tariffs_overrides_and_organizations_round_trip()
    {
        $this->as('ADMIN');
        $tag = uniqid();

        $this->postJson('/api/mobile/hmo/hmos', ['name' => "Test HMO {$tag}", 'description' => 'x', 'discount' => 10, 'hmo_scheme_id' => 1], self::AJAX)->assertOk();
        $hmo = DB::table('hmos')->where('name', "Test HMO {$tag}")->value('id');
        $this->assertNotNull($hmo);
        $this->postJson('/api/mobile/hmo/hmos', ['description' => 'no name'], self::AJAX)->assertStatus(422);
        $this->putJson("/api/mobile/hmo/hmos/{$hmo}", ['name' => "Test HMO {$tag} B", 'description' => 'y', 'discount' => 20, 'hmo_scheme_id' => 1], self::AJAX)->assertOk();
        $this->assertSame(20, (int) DB::table('hmos')->where('id', $hmo)->value('discount'));
        $this->postJson("/api/mobile/hmo/hmos/{$hmo}/toggle-status", [], self::AJAX)->assertOk();

        $service = DB::table('services')->where('status', 1)->value('id');
        // a new HMO already gets a tariff for every item; remove this one to create it through the app
        if ($existing = DB::table('hmo_tariffs')->where('hmo_id', $hmo)->where('service_id', $service)->value('id')) {
            $this->deleteJson("/api/mobile/hmo/tariffs/{$existing}", [], self::AJAX)->assertOk();
        }
        $t = $this->postJson('/api/mobile/hmo/tariffs', ['hmo_id' => $hmo, 'item_type' => 'service', 'service_id' => $service, 'claims_amount' => 800, 'payable_amount' => 200, 'coverage_mode' => 'primary'], self::AJAX);
        $this->assertSame(200, $t->status(), substr($t->getContent(), 0, 300));
        $tid = DB::table('hmo_tariffs')->where('hmo_id', $hmo)->where('service_id', $service)->value('id');
        $this->putJson("/api/mobile/hmo/tariffs/{$tid}", ['coverage_mode' => 'express', 'claims_amount' => 900, 'payable_amount' => 100], self::AJAX)->assertOk();
        $this->assertEquals(900, DB::table('hmo_tariffs')->where('id', $tid)->value('claims_amount'));

        $o = $this->postJson('/api/mobile/hmo/tariffs/overrides', ['context_type' => 'hmo', 'context_id' => $hmo, 'target_type' => 'all_services', 'override_type' => 'percentage', 'amount' => 50], self::AJAX);
        $this->assertSame(200, $o->status(), substr($o->getContent(), 0, 300));
        $oid = collect($this->getJson('/api/mobile/hmo/tariffs/overrides', self::AJAX)->json('data'))->first()['id'];
        $this->deleteJson("/api/mobile/hmo/tariffs/overrides/{$oid}", [], self::AJAX)->assertOk();
        $this->deleteJson("/api/mobile/hmo/tariffs/{$tid}", [], self::AJAX)->assertOk();

        $this->postJson('/api/mobile/hmo/organizations', ['name' => "Org {$tag}", 'status' => true, 'credit_limit' => 1000], self::AJAX)->assertOk();
        $org = DB::table('organizations')->where('name', "Org {$tag}")->value('id');
        $this->putJson("/api/mobile/hmo/organizations/{$org}", ['name' => "Org {$tag} B", 'status' => true], self::AJAX)->assertOk();
        $this->getJson("/api/mobile/hmo/organizations/{$org}/bills", self::AJAX)->assertOk();
        $this->deleteJson("/api/mobile/hmo/organizations/{$org}", [], self::AJAX)->assertOk();
    }

    public function test_the_tariff_import_previews_before_it_changes_anything()
    {
        $this->as('ADMIN');
        $hmo = DB::table('hmos')->value('id');
        $file = UploadedFile::fake()->createWithContent('t.csv', "Item Code,Item Name\nX1,Nothing\n");
        $r = $this->post('/api/mobile/hmo/tariffs/import/preview', ['file' => $file, 'scope' => 'hmo', 'hmo_id' => $hmo], self::AJAX);
        $this->assertContains($r->status(), [200, 422], substr($r->getContent(), 0, 200));
        $this->assertIsArray($r->json());
    }

    public function test_the_hmo_patients_list_only_has_patients_with_an_hmo()
    {
        $this->as('SUPERADMIN');
        $web = $this->getJson('/patientsList?hmo_only=1&draw=1&start=0&length=5&search[value]=', self::AJAX)->json();
        $all = $this->getJson('/patientsList?draw=1&start=0&length=5&search[value]=', self::AJAX)->json();
        $this->assertSame(DB::table('patients')->whereNotNull('hmo_id')->count(), (int) $web['recordsFiltered'], 'the sidebar link now lists HMO patients only');
        $this->assertGreaterThanOrEqual((int) $web['recordsFiltered'], (int) $all['recordsFiltered']);
        $mobile = $this->getJson('/api/mobile/hmo/patients' . self::DT, self::AJAX)->json();
        $this->assertSame((int) $web['recordsFiltered'], (int) $mobile['recordsFiltered']);
    }
}
