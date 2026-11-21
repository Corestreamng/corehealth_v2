<?php

namespace Tests\Feature\NativeClient;

use App\Models\User;
use App\Services\Auth\WebHandoffService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Staff app → web hand-off (WebHandoffController): a one-time, short-lived code that signs the same
 * user in on the web and opens the requested page.
 */
class WebHandoffTest extends TestCase
{
    private function nurse(): array
    {
        if (!Role::where('name', 'NURSE')->exists()) {
            $this->markTestSkipped('NURSE role missing.');
        }
        $user = User::factory()->create(['status' => 1, 'password' => bcrypt('secret-pass')]);
        $user->assignRole('NURSE');
        $token = $user->createToken('mobile-staff')->plainTextToken;

        return [$user, $token];
    }

    private function issue(string $token, string $url = '/nursing-workbench'): string
    {
        $res = $this->withHeader('Authorization', "Bearer $token")->postJson('/api/mobile/staff/web-handoff', ['url' => $url]);
        $res->assertOk()->assertJsonPath('status', true);
        $this->assertStringContainsString('/auth/handoff?code=', $res->json('url'));
        // A fresh browser request: the web guard, as on a real redeem.
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');

        return $res->json('url');
    }

    private function codeFrom(string $url): string
    {
        parse_str(parse_url($url, PHP_URL_QUERY), $q);

        return $q['code'];
    }

    public function test_the_code_signs_the_same_user_in_and_opens_the_page()
    {
        [$user, $token] = $this->nurse();
        $url = $this->issue($token, 'http://localhost/nursing-workbench?tab=queue');

        $res = $this->get('/auth/handoff?code=' . $this->codeFrom($url));
        $res->assertRedirect('/nursing-workbench?tab=queue');
        $res->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_a_code_works_only_once()
    {
        [, $token] = $this->nurse();
        $code = $this->codeFrom($this->issue($token));

        $this->get('/auth/handoff?code=' . $code)->assertRedirect('/nursing-workbench');
        $this->app['auth']->guard('web')->logout();
        $this->flushSession();

        $this->get('/auth/handoff?code=' . $code)->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    public function test_a_code_expires()
    {
        [, $token] = $this->nurse();
        $code = $this->codeFrom($this->issue($token));
        $this->travel(WebHandoffService::TTL + 1)->seconds();

        $this->get('/auth/handoff?code=' . $code)->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    public function test_a_code_is_bound_to_the_requesting_device()
    {
        [, $token] = $this->nurse();
        $code = $this->codeFrom($this->issue($token));

        $this->withServerVariables(['REMOTE_ADDR' => '10.9.8.7'])->get('/auth/handoff?code=' . $code)->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    public function test_signing_out_of_the_app_voids_pending_codes()
    {
        [$user, $token] = $this->nurse();
        $code = $this->codeFrom($this->issue($token));
        $user->tokens()->delete();

        $this->get('/auth/handoff?code=' . $code)->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    public function test_a_deactivated_account_is_refused_at_redeem()
    {
        [$user, $token] = $this->nurse();
        $code = $this->codeFrom($this->issue($token));
        $user->forceFill(['status' => 0])->save();

        $this->get('/auth/handoff?code=' . $code)->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    public function test_targets_are_limited_to_this_site()
    {
        foreach (['https://evil.example/steal', '//evil.example/x', '/\\evil.example', 'javascript:alert(1)', "/ok\r\nSet-Cookie: x=1"] as $bad) {
            $this->assertSame('/home', WebHandoffService::safePath($bad), $bad);
        }
        $this->assertSame('/beds?ward=2', WebHandoffService::safePath('http://localhost/beds?ward=2'));
        $this->assertSame('/beds', WebHandoffService::safePath('/beds'));
    }

    public function test_malformed_and_unknown_codes_are_refused()
    {
        $this->nurse();
        foreach (['', 'abc', str_repeat('g', 64), bin2hex(random_bytes(32))] as $code) {
            $this->get('/auth/handoff?code=' . urlencode($code))->assertRedirect(route('login'));
        }
        $this->assertGuest('web');
    }

    public function test_another_user_on_the_browser_is_replaced()
    {
        [$user, $token] = $this->nurse();
        $other = User::factory()->create(['status' => 1]);
        $code = $this->codeFrom($this->issue($token));

        $this->actingAs($other, 'web')->get('/auth/handoff?code=' . $code)->assertRedirect('/nursing-workbench');
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_only_signed_in_staff_can_ask_for_a_code()
    {
        $this->postJson('/api/mobile/staff/web-handoff', ['url' => '/home'])->assertStatus(401);
    }
}
