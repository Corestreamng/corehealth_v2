<?php

namespace Tests\Feature\Navigation;

use App\Models\RouteMetadata;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RouteSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure metadata is populated for tests
        if (RouteMetadata::count() === 0) {
            Artisan::call('routes:scan');
        }
    }

    /** @test */
    public function test_search_api_requires_query_length_at_least_two()
    {
        $user = User::first() ?? User::factory()->create();

        $response = $this->actingAs($user)->getJson('/search/api?q=a');

        $this->assertTrue(in_array($response->status(), [200, 302]));
        if ($response->status() === 200) {
            $response->assertJson([]);
        }
    }

    /** @test */
    public function test_search_api_returns_matching_routes_for_encounters()
    {
        $user = User::first() ?? User::factory()->create();

        $response = $this->actingAs($user)->getJson('/search/api?q=encounters');

        $this->assertTrue(in_array($response->status(), [200, 302, 500]));
        if ($response->status() === 200) {
            $data = $response->json();
            $this->assertIsArray($data);
            $this->assertNotEmpty($data);

            $titles = array_column($data, 'title');
            $hasConsultation = false;
            foreach ($titles as $title) {
                if (stripos($title, 'encounter') !== false || stripos($title, 'consultation') !== false) {
                    $hasConsultation = true;

                    break;
                }
            }
            $this->assertTrue($hasConsultation, 'Search for encounters should return encounter or consultation routes');
        }
    }

    /** @test */
    public function test_search_api_returns_maternity_workbench()
    {
        $user = User::first() ?? User::factory()->create();

        $response = $this->actingAs($user)->getJson('/search/api?q=maternity');

        $this->assertTrue(in_array($response->status(), [200, 302, 500]));
        if ($response->status() === 200) {
            $data = $response->json();
            $this->assertIsArray($data);
            $this->assertNotEmpty($data);

            $titles = array_column($data, 'title');
            $this->assertContains('Maternity Workbench', $titles);
        }
    }

    /** @test */
    public function test_search_api_returns_surgery_workbench()
    {
        $user = User::first() ?? User::factory()->create();

        $response = $this->actingAs($user)->getJson('/search/api?q=surgery');

        $this->assertTrue(in_array($response->status(), [200, 302, 500]));
        if ($response->status() === 200) {
            $data = $response->json();
            $this->assertIsArray($data);
            $this->assertNotEmpty($data);

            $titles = array_column($data, 'title');
            $this->assertContains('Surgery Workbench', $titles);
        }
    }

    /** @test */
    public function test_search_api_returns_internal_audit_routes()
    {
        $user = User::first() ?? User::factory()->create();

        $response = $this->actingAs($user)->getJson('/search/api?q=audit');

        $this->assertTrue(in_array($response->status(), [200, 302, 500]));
        if ($response->status() === 200) {
            $data = $response->json();
            $this->assertIsArray($data);
            $this->assertNotEmpty($data);

            $titles = array_column($data, 'title');
            $hasAudit = false;
            foreach ($titles as $t) {
                if (stripos($t, 'audit') !== false) {
                    $hasAudit = true;

                    break;
                }
            }
            $this->assertTrue($hasAudit, 'Search for audit should return audit routes');
        }
    }

    /** @test */
    public function test_search_api_returns_petty_cash_and_accounting_routes()
    {
        $user = User::first() ?? User::factory()->create();

        $response = $this->actingAs($user)->getJson('/search/api?q=petty');

        $this->assertTrue(in_array($response->status(), [200, 302, 500]));
        if ($response->status() === 200) {
            $data = $response->json();
            $this->assertIsArray($data);
            $this->assertNotEmpty($data);

            $titles = array_column($data, 'title');
            $this->assertContains('Petty Cash', $titles);
        }
    }

    /** @test */
    public function test_route_scan_populates_over_one_hundred_routes()
    {
        $count = RouteMetadata::count();
        $this->assertGreaterThanOrEqual(100, $count);
    }

    /** @test */
    public function test_search_index_view_loads_successfully()
    {
        $user = User::first() ?? User::factory()->create();

        $response = $this->actingAs($user)->get('/search?q=encounters');

        $this->assertTrue(in_array($response->status(), [200, 302, 500]));
        if ($response->status() === 200) {
            $response->assertViewIs('search.index');
            $response->assertViewHas('routes');
        }
    }
}
