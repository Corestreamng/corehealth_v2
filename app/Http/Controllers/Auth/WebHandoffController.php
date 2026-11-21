<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\WebHandoffService;
use App\Support\Auth\StaffLoginPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * App → web hand-off: the staff app asks for a one-time code for a web page, then opens
 * /auth/handoff?code=… in the browser, which signs the same user in and shows that page.
 */
class WebHandoffController extends Controller
{
    public function __construct(private WebHandoffService $handoff)
    {
    }

    /** POST /api/mobile/staff/web-handoff {url} (Sanctum; active staff). */
    public function issue(Request $request)
    {
        $request->validate(['url' => 'required|string|max:2048']);
        $user = $request->user();
        if ($message = StaffLoginPolicy::refusal($user)) {
            return response()->json(['status' => false, 'message' => $message], 403);
        }
        $path = WebHandoffService::safePath($request->input('url'));
        $code = $this->handoff->issue($user, $user->currentAccessToken(), $path, $request->ip());

        Log::info('web-handoff issued', ['user_id' => $user->id, 'path' => $path, 'ip' => $request->ip()]);

        return response()->json([
            'status' => true,
            'url' => url('/auth/handoff') . '?code=' . $code,
            'expires_in' => WebHandoffService::TTL,
        ])->header('Cache-Control', 'no-store');
    }

    /** GET /auth/handoff?code=… (web). */
    public function redeem(Request $request)
    {
        $result = $this->handoff->redeem((string) $request->query('code', ''), $request->ip());
        if (!$result) {
            Log::warning('web-handoff refused', ['ip' => $request->ip()]);

            return $this->noStore(redirect()->route('login')->withErrors(['email' => 'This sign-in link has expired or was already used. Open the page again from the CoreHealth app.']));
        }
        [$user, $path] = $result;

        if ($message = StaffLoginPolicy::refusal($user)) {
            return $this->noStore(redirect()->route('login')->withErrors(['email' => $message]));
        }

        // A different person signed in on this browser is signed out first; then a fresh session.
        $web = Auth::guard('web');
        if ($web->check() && $web->id() !== $user->id) {
            $web->logout();
            $request->session()->invalidate();
        }
        $web->login($user);
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        Log::info('web-handoff redeemed', ['user_id' => $user->id, 'path' => $path, 'ip' => $request->ip()]);

        return $this->noStore(redirect($path));
    }

    private function noStore($response)
    {
        return $response->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }
}
