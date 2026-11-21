<?php

namespace Tests\Feature\NativeClient;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Every sidebar item the app shows (and opens on the web when it is not native yet) must point at a
 * real web page: a URL is present and the page loads for someone allowed to see it.
 */
class WebLinksTest extends TestCase
{
    public function test_every_menu_item_has_a_working_web_page()
    {
        $admin = User::where('email', 'sysadmin@mail.com')->first();
        if (!$admin) {
            $this->markTestSkipped('sysadmin account missing.');
        }
        Sanctum::actingAs($admin);
        $sections = $this->getJson('/api/mobile/staff/workspaces')->assertOk()->json('data.workspaces') ?? $this->getJson('/api/mobile/staff/workspaces')->json('data');
        $this->assertNotEmpty($sections);
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');

        $broken = [];
        $checked = 0;
        foreach ($sections as $section) {
            foreach ($section['items'] ?? [] as $item) {
                $url = $item['web_url'] ?? null;
                if (!$url) {
                    $broken[] = "{$item['key']}: no web URL";

                    continue;
                }
                $parts = parse_url($url);
                $path = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
                $status = $this->actingAs($admin, 'web')->get($path)->status();
                $checked++;
                if (in_array($status, [404, 405, 500, 503], true)) {
                    $broken[] = "{$item['key']} ({$item['label']}): {$path} → {$status}";
                }
            }
        }
        $this->assertGreaterThan(50, $checked, 'every sidebar item is checked');
        $this->assertSame([], $broken, "Broken web links:\n" . implode("\n", $broken));
    }
}
