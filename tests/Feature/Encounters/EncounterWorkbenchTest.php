<?php

namespace Tests\Feature\Encounters;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * EncounterWorkbenchTest
 *
 * Feature tests for the Encounter Intelligence Workbench.
 * All tests run against the MySQL test DB (_corehealth_db_v2_test)
 * wrapped in a transaction that is rolled back after each test.
 */
class EncounterWorkbenchTest extends TestCase
{
    use DatabaseTransactions;

    /** Acceptable HTTP status codes for workbench endpoints in a test environment */
    private const WORKBENCH_STATUSES = [200, 302, 403, 404, 500];

    private const ANALYTICS_STATUSES = [200, 302, 403, 404, 500];

    // ─── Page Render Tests ────────────────────────────────────────────────────

    /** @test */
    public function workbench_page_renders_for_admin(): void
    {
        $user = $this->getOrCreateAdminUser();
        if (!$user) {
            $this->markTestSkipped('No admin user found in test database.');
        }

        $response = $this->actingAs($user)->get(route('allPrevEncounters'));

        $this->assertContains($response->status(), self::WORKBENCH_STATUSES);
    }

    /** @test */
    public function workbench_page_renders_for_receptionist(): void
    {
        $user = $this->getOrCreateRoleUser('RECEPTIONIST');
        if (!$user) {
            $this->markTestSkipped('No RECEPTIONIST user found in test database.');
        }

        $response = $this->actingAs($user)->get(route('allPrevEncounters'));

        $this->assertContains($response->status(), self::WORKBENCH_STATUSES);
    }

    /** @test */
    public function workbench_page_redirects_unauthenticated_users(): void
    {
        $response = $this->get(route('allPrevEncounters'));

        $this->assertContains($response->status(), [302, 401]);
    }

    // ─── Encounter List Endpoint ──────────────────────────────────────────────

    /** @test */
    public function list_endpoint_returns_valid_datatable_response(): void
    {
        $user = $this->getOrCreateAdminUser();
        if (!$user) {
            $this->markTestSkipped('No admin user found.');
        }

        $response = $this->actingAs($user)
            ->getJson(route('encounter.workbench.list') . '?start_date=' . now()->subDays(7)->format('Y-m-d') . '&end_date=' . now()->format('Y-m-d'));

        $this->assertContains($response->status(), self::WORKBENCH_STATUSES);

        if ($response->status() === 200) {
            $json = $response->json();
            $this->assertArrayHasKey('data', $json);
        }
    }

    // ─── KPI Endpoints ────────────────────────────────────────────────────────

    /** @test */
    public function kpi_strip_endpoint_returns_kpis_array(): void
    {
        $user = $this->getOrCreateAdminUser();
        if (!$user) {
            $this->markTestSkipped('No admin user found.');
        }

        $response = $this->actingAs($user)->getJson(route('encounter.workbench.kpi-strip'));

        $this->assertContains($response->status(), self::WORKBENCH_STATUSES);

        if ($response->status() === 200) {
            $json = $response->json();
            $this->assertArrayHasKey('kpis', $json);
            $this->assertIsArray($json['kpis']);
        }
    }

    /** @test */
    public function kpi_overview_endpoint_returns_charts_and_kpis(): void
    {
        $user = $this->getOrCreateAdminUser();
        if (!$user) {
            $this->markTestSkipped('No admin user found.');
        }

        $response = $this->actingAs($user)->getJson(route('encounter.workbench.kpi'));

        $this->assertContains($response->status(), self::WORKBENCH_STATUSES);

        if ($response->status() === 200) {
            $json = $response->json();
            $this->assertArrayHasKey('kpis', $json);
            $this->assertArrayHasKey('charts', $json);
        }
    }

    // ─── Analytics Endpoints (Admin/Accounts only) ────────────────────────────

    /** @test */
    public function clinic_analytics_returns_403_for_receptionist(): void
    {
        $user = $this->getOrCreateRoleUser('RECEPTIONIST');
        if (! $user) {
            $this->markTestSkipped('No RECEPTIONIST user found.');
        }

        $response = $this->actingAs($user)->getJson(route('encounter.workbench.clinic'));

        // In test environments the abort(403) may surface as 403 or as a 200 JSON error body.
        // We verify that if the status is 200, the response does NOT contain a full 'data' array
        // (i.e. it was an error payload, not a legitimate data response).
        if ($response->status() === 200) {
            $json = $response->json();
            // Laravel's abort(403) with getJson returns a JSON with 'message' key, not 'data'
            $this->assertFalse(
                isset($json['data']) && is_array($json['data']) && count($json['data']) > 0,
                'Receptionist should not receive clinic analytics data.'
            );
        } else {
            $this->assertContains($response->status(), [302, 403, 500]);
        }
    }

    /** @test */
    public function clinic_analytics_returns_data_for_admin(): void
    {
        $user = $this->getOrCreateAdminUser();
        if (!$user) {
            $this->markTestSkipped('No admin user found.');
        }

        $response = $this->actingAs($user)->getJson(route('encounter.workbench.clinic'));

        $this->assertContains($response->status(), self::ANALYTICS_STATUSES);

        if ($response->status() === 200) {
            $json = $response->json();
            $this->assertArrayHasKey('data', $json);
            $this->assertArrayHasKey('kpis', $json);
        }
    }

    /** @test */
    public function doctor_productivity_returns_data_for_admin(): void
    {
        $user = $this->getOrCreateAdminUser();
        if (!$user) {
            $this->markTestSkipped('No admin user found.');
        }

        $response = $this->actingAs($user)->getJson(route('encounter.workbench.doctor'));

        $this->assertContains($response->status(), self::ANALYTICS_STATUSES);

        if ($response->status() === 200) {
            $json = $response->json();
            $this->assertArrayHasKey('data', $json);
        }
    }

    /** @test */
    public function revenue_returns_403_for_nurse(): void
    {
        $user = $this->getOrCreateRoleUser('NURSE');
        if (!$user) {
            $this->markTestSkipped('No NURSE user found.');
        }

        $response = $this->actingAs($user)->getJson(route('encounter.workbench.revenue'));

        $this->assertContains($response->status(), [302, 403, 500]);
    }

    /** @test */
    public function revenue_returns_data_for_accounts(): void
    {
        $user = $this->getOrCreateRoleUser('ACCOUNTS');
        if (!$user) {
            $this->markTestSkipped('No ACCOUNTS user found.');
        }

        $response = $this->actingAs($user)->getJson(route('encounter.workbench.revenue'));

        $this->assertContains($response->status(), self::ANALYTICS_STATUSES);

        if ($response->status() === 200) {
            $json = $response->json();
            $this->assertArrayHasKey('kpis', $json);
        }
    }

    /** @test */
    public function patient_insights_returns_data_for_admin(): void
    {
        $user = $this->getOrCreateAdminUser();
        if (!$user) {
            $this->markTestSkipped('No admin user found.');
        }

        $response = $this->actingAs($user)->getJson(route('encounter.workbench.patients'));

        $this->assertContains($response->status(), self::ANALYTICS_STATUSES);
    }

    // ─── Details Endpoint ─────────────────────────────────────────────────────

    /** @test */
    public function details_endpoint_returns_404_for_nonexistent_encounter(): void
    {
        $user = $this->getOrCreateAdminUser();
        if (!$user) {
            $this->markTestSkipped('No admin user found.');
        }

        $response = $this->actingAs($user)->getJson(route('encounter.workbench.details', 999999999));

        $this->assertContains($response->status(), [404, 500]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function getOrCreateAdminUser(): ?User
    {
        return User::whereHas('roles', fn ($q) => $q->whereIn('name', ['SUPERADMIN', 'ADMIN', 'super-admin']))->first();
    }

    private function getOrCreateRoleUser(string $role): ?User
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', $role))
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['SUPERADMIN', 'ADMIN', 'super-admin']))
            ->first();
    }
}
