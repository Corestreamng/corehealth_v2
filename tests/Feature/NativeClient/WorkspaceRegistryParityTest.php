<?php

namespace Tests\Feature\NativeClient;

use App\Models\User;
use App\Support\Navigation\WorkspaceRegistry;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The native client must show exactly what the web sidebar shows, behind the same guards.
 * These tests read the sidebar Blade partials and fail when the registry drifts from them.
 */
class WorkspaceRegistryParityTest extends TestCase
{
    protected function partialPath(string $partial): string
    {
        return resource_path("views/admin/partials/sidebar/{$partial}.blade.php");
    }

    /** Roles in the first (outer) role guard of a sidebar partial, or [] when it has none. */
    protected function outerRoles(string $partial): array
    {
        $blade = file_get_contents($this->partialPath($partial));
        if (preg_match("/^@hasanyrole\('([^']*)'\)/m", $blade, $m)) {
            return explode('|', $m[1]);
        }
        if (preg_match("/^@if\s*\(\s*Auth::user\(\)\s*&&\s*Auth::user\(\)->hasAnyRole\(\[([^\]]*)\]\)/m", $blade, $m)) {
            return array_map(fn ($r) => trim($r, " '\""), explode(',', $m[1]));
        }

        return [];
    }

    /** @test */
    public function test_sections_follow_sidebar_include_order()
    {
        $sidebar = file_get_contents(resource_path('views/admin/partials/sidebar.blade.php'));
        preg_match_all("/@include\('admin\.partials\.sidebar\.(_nav-[a-z]+)'\)/", $sidebar, $m);
        $registryOrder = array_column(WorkspaceRegistry::sections(), 'partial');

        $this->assertSame($m[1], $registryOrder, 'Registry sections must be in the same order as the web sidebar');
    }

    /** @test */
    public function test_every_section_has_the_same_role_guard_as_its_sidebar_partial()
    {
        foreach (WorkspaceRegistry::sections() as $section) {
            $expected = $this->outerRoles($section['partial']);
            $actual = $section['guard']['roles'] ?? [];
            sort($expected);
            sort($actual);
            $this->assertSame($expected, $actual, "Role guard of '{$section['key']}' differs from {$section['partial']}");
        }
    }

    /** @test */
    public function test_nested_role_guards_match_the_sidebar()
    {
        $nested = [
            ['_nav-nursing', 'maternity-workbench.index', 'nursing.maternity'],
            ['_nav-doctor', 'maternity-workbench.index', 'doctor.maternity'],
        ];
        $items = collect(WorkspaceRegistry::sections())->flatMap(fn ($s) => $s['items'])->keyBy('key');
        foreach ($nested as [$partial, $route, $itemKey]) {
            $blade = file_get_contents($this->partialPath($partial));
            $pattern = "/@hasanyrole\('([^']*)'\)(?:(?!@endhasanyrole).)*route\('" . preg_quote($route, '/') . "'/s";
            $this->assertMatchesRegularExpression($pattern, $blade);
            preg_match_all("/@hasanyrole\('([^']*)'\)/", $blade, $all, PREG_OFFSET_CAPTURE);
            $routePos = strpos($blade, "route('{$route}'");
            $guard = null;
            foreach ($all[1] as [$roles, $pos]) {
                if ($pos < $routePos) {
                    $guard = $roles;
                }
            }
            $expected = explode('|', $guard);
            $actual = $items[$itemKey]['guard']['roles'];
            sort($expected);
            sort($actual);
            $this->assertSame($expected, $actual, "Nested guard of {$itemKey} differs from {$partial}");
        }
    }

    /** @test */
    public function test_every_registry_route_exists_and_every_sidebar_route_is_mirrored()
    {
        $registryRoutes = [];
        foreach (WorkspaceRegistry::sections() as $section) {
            foreach ($section['items'] as $item) {
                $this->assertTrue(Route::has($item['route']), "Route {$item['route']} ({$item['key']}) does not exist");
                $registryRoutes[$section['partial']][] = $item['route'];
            }
        }
        foreach (WorkspaceRegistry::sections() as $section) {
            preg_match_all("/route\('([^']+)'/", file_get_contents($this->partialPath($section['partial'])), $m);
            foreach (array_unique($m[1]) as $route) {
                $this->assertContains($route, $registryRoutes[$section['partial']] ?? [], "Sidebar route {$route} in {$section['partial']} is missing from the registry");
            }
        }
    }

    /** @test */
    public function test_visible_workspaces_follow_roles()
    {
        $admin = User::where('email', 'sysadmin@mail.com')->first();
        if ($admin) {
            $keys = array_column(WorkspaceRegistry::visibleFor($admin), 'key');
            foreach (['reception', 'billing', 'doctor', 'admin'] as $k) {
                $this->assertContains($k, $keys);
            }
        }

        if (!Role::where('name', 'RECEPTIONIST')->exists()) {
            $this->markTestSkipped('RECEPTIONIST role missing.');
        }
        $receptionist = User::factory()->create(['status' => 1]);
        $receptionist->assignRole('RECEPTIONIST');
        $keys = array_column(WorkspaceRegistry::visibleFor($receptionist), 'key');
        $this->assertContains('reception', $keys);
        $this->assertNotContains('doctor', $keys);
        $this->assertNotContains('admin', $keys);
        $receptionItems = collect(WorkspaceRegistry::visibleFor($receptionist))->firstWhere('key', 'reception')['items'];
        $this->assertNotContains('reception.nhmis', array_column($receptionItems, 'key'), 'NHMIS needs a unit/department head, as on the web');
    }
}
