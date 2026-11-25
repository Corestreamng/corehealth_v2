<?php

namespace Tests\Feature\NativeClient;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The staff app makes many small calls; the old 60 a minute limit made every list fail with 429 after a minute of use.
 */
class RateLimitTest extends TestCase
{
    public function test_a_signed_in_staff_member_is_not_cut_off_after_sixty_calls()
    {
        $u = User::factory()->create(['status' => 1]);
        $u->assignRole('SUPERADMIN');
        Sanctum::actingAs($u);

        $last = 200;
        for ($i = 0; $i < 130; $i++) {
            $last = $this->getJson('/api/mobile/stock/context', ['X-Requested-With' => 'XMLHttpRequest'])->status();
            if ($last === 429) {
                break;
            }
        }
        $this->assertNotSame(429, $last, 'call number ' . ($i + 1) . ' was refused');
    }

    public function test_the_limits_are_generous_for_staff_and_low_for_anonymous_callers()
    {
        $limiter = \Illuminate\Support\Facades\RateLimiter::limiter('api');
        $anon = $limiter(\Illuminate\Http\Request::create('/api/x'));
        $this->assertSame(60, $anon->maxAttempts);

        $u = User::factory()->create(['status' => 1]);
        $signedIn = \Illuminate\Http\Request::create('/api/x');
        $signedIn->setUserResolver(fn () => $u);
        $limit = $limiter($signedIn);
        $this->assertSame(1200, $limit->maxAttempts);
        $this->assertSame('user:' . $u->id, $limit->key);
    }
}
