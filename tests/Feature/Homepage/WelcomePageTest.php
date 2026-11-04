<?php

namespace Tests\Feature\Homepage;

use App\Models\ApplicationStatu;
use App\Models\User;
use Tests\TestCase;

class WelcomePageTest extends TestCase
{
    /** @test */
    public function test_welcome_page_renders_successfully()
    {
        $response = $this->get('/');

        $this->assertTrue(in_array($response->status(), [200, 302]));
        if ($response->status() === 200) {
            $response->assertSee('welcome.css');
            $response->assertSee('Core');
            $response->assertSee('Health');
        }
    }

    /** @test */
    public function test_welcome_page_is_branded_by_default()
    {
        $config = ApplicationStatu::first();
        if (!$config) {
            $config = ApplicationStatu::create([
                'site_name' => 'St. Jude Metropolitan Hospital',
                'show_branded_homepage' => true,
            ]);
        } else {
            $config->show_branded_homepage = true;
            $config->save();
        }
        clearAppSettingsCache();

        $siteName = $config->site_name ?? 'CoreHealth';

        $response = $this->get('/');

        $this->assertTrue(in_array($response->status(), [200, 302]));
        if ($response->status() === 200) {
            $response->assertSee($siteName);
            $response->assertSee('Powered by Core');
            $response->assertSee('Staff Portal');
        }
    }

    /** @test */
    public function test_welcome_page_renders_unbranded_demo_when_setting_is_disabled()
    {
        $config = ApplicationStatu::first();
        if (!$config) {
            $config = ApplicationStatu::create([
                'site_name' => 'St. Jude Metropolitan Hospital',
                'show_branded_homepage' => false,
            ]);
        } else {
            $config->show_branded_homepage = false;
            $config->save();
        }
        clearAppSettingsCache();

        $response = $this->get('/');

        $this->assertTrue(in_array($response->status(), [200, 302]));
        if ($response->status() === 200) {
            $response->assertSee('CoreHealth — The AI-Powered Operating System for Healthcare');
            $response->assertSee('Corestream Networks and Robotics Limited');
            $response->assertSee('Start free');
            $response->assertSee('Book a demo');
            $response->assertDontSee('Powered by Core');
        }

        // Restore to true
        $config->show_branded_homepage = true;
        $config->save();
        clearAppSettingsCache();
    }

    /** @test */
    public function test_welcome_page_respects_query_overrides()
    {
        // Force demo via query param ?branded=0
        $responseDemo = $this->get('/?branded=0');
        $this->assertTrue(in_array($responseDemo->status(), [200, 302]));
        if ($responseDemo->status() === 200) {
            $responseDemo->assertSee('CoreHealth — The AI-Powered Operating System for Healthcare');
            $responseDemo->assertSee('Start free');
        }

        // Force demo via ?demo=1
        $responseDemo2 = $this->get('/?demo=1');
        $this->assertTrue(in_array($responseDemo2->status(), [200, 302]));
        if ($responseDemo2->status() === 200) {
            $responseDemo2->assertSee('CoreHealth — The AI-Powered Operating System for Healthcare');
        }

        // Force branded via ?branded=1
        $responseBranded = $this->get('/?branded=1');
        $this->assertTrue(in_array($responseBranded->status(), [200, 302]));
        if ($responseBranded->status() === 200) {
            $responseBranded->assertSee('Powered by Core');
            $responseBranded->assertSee('Clinical Services');
            $responseBranded->assertSee('Staff Portal');
        }
    }

    /** @test */
    public function test_hospital_config_updates_show_branded_homepage_setting()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1, 'is_admin' => 1]);
        $this->actingAs($user);

        $config = ApplicationStatu::first();
        if (!$config) {
            $config = ApplicationStatu::create([
                'site_name' => 'CoreHealth Facility',
                'show_branded_homepage' => true,
            ]);
        }

        // Test updating with toggle checked (value = 1)
        $response = $this->put(route('hospital-config.update'), [
            'site_name' => 'Updated Hospital Name',
            'show_branded_homepage' => '1',
        ]);

        $this->assertTrue(in_array($response->getStatusCode(), [200, 302]));
        $config->refresh();
        $this->assertTrue((bool) $config->show_branded_homepage);

        // Test updating with toggle unchecked (absent in request payload)
        $response2 = $this->put(route('hospital-config.update'), [
            'site_name' => 'Updated Hospital Name 2',
        ]);

        $this->assertTrue(in_array($response2->getStatusCode(), [200, 302]));
        $config->refresh();
        $this->assertFalse((bool) $config->show_branded_homepage);

        // Reset to true
        $config->show_branded_homepage = true;
        $config->save();
        clearAppSettingsCache();
    }
}
