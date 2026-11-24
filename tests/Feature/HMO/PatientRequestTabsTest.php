<?php

namespace Tests\Feature\HMO;

use App\Models\ProductOrServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A request the patient has already paid their share of, whose HMO claim nobody has validated, belongs in Pending
 * (it used to land in Past, so it never reached the HMO executive).
 */
class PatientRequestTabsTest extends TestCase
{
    private function request(string $status, float $claims, bool $paid): ProductOrServiceRequest
    {
        $r = ProductOrServiceRequest::whereNotNull('coverage_mode')->where('coverage_mode', '!=', 'express')->where('is_bundle_item', false)
            ->whereHas('user.patient_profile', fn ($q) => $q->whereNotNull('hmo_id'))->first() ?? $this->markTestSkipped('No HMO request in this database.');
        $r->forceFill(['validation_status' => $status, 'claims_amount' => $claims, 'coverage_mode' => 'primary', 'payment_id' => $paid ? (DB::table('payments')->value('id') ?? 1) : null])->saveQuietly();

        return $r->fresh();
    }

    private function tabOf(ProductOrServiceRequest $r): ?string
    {
        $admin = User::factory()->create(['status' => 1]);
        $admin->assignRole('SUPERADMIN');
        $body = $this->actingAs($admin)->getJson('/hmo/patient/' . $r->user->patient_profile->id . '/all-requests', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json();
        foreach ($body['tabs'] as $tab => $rows) {
            if (in_array($r->id, array_column($rows, 'id'), true)) {
                return $tab;
            }
        }

        return null;
    }

    public function test_a_paid_but_unvalidated_claim_stays_in_pending()
    {
        $this->assertSame('pending', $this->tabOf($this->request('pending', 5000, true)));
        $this->assertSame('pending', $this->tabOf($this->request('pending', 5000, false)));
    }

    public function test_a_request_with_no_hmo_share_is_flagged_and_not_a_claim()
    {
        $r = $this->request('pending', 0, true);
        $this->assertSame('past', $this->tabOf($r));
        $admin = User::factory()->create(['status' => 1]);
        $admin->assignRole('SUPERADMIN');
        $rows = $this->actingAs($admin)->getJson('/hmo/patient/' . $r->user->patient_profile->id . '/all-requests', ['X-Requested-With' => 'XMLHttpRequest'])->json('tabs.past');
        $this->assertTrue(collect($rows)->firstWhere('id', $r->id)['no_hmo_share']);
    }

    public function test_settled_requests_keep_their_tabs()
    {
        $this->assertSame('past', $this->tabOf($this->request('approved', 5000, true)));
        $this->assertSame('approved', $this->tabOf($this->request('approved', 5000, false)));
        $this->assertSame('rejected', $this->tabOf($this->request('rejected', 5000, false)));
    }
}
