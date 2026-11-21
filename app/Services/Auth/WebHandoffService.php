<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * One-time codes that carry a signed-in staff app session into the web (open a page that is not
 * native yet without signing in again).
 *
 * - 256-bit random code; only its SHA-256 hash is stored, so a cache dump reveals nothing usable.
 * - Valid for TTL seconds and exactly once (consumed under a lock).
 * - Bound to the user, to the app token that asked for it (signing out of the app voids it) and,
 *   by default, to the device's IP address.
 * - Only same-site relative paths can be targets (no open redirect).
 */
class WebHandoffService
{
    public const TTL = 60;

    private const PREFIX = 'web-handoff:';

    public function issue(User $user, PersonalAccessToken $token, string $path, ?string $ip): string
    {
        $code = bin2hex(random_bytes(32));
        Cache::put(self::PREFIX . hash('sha256', $code), [
            'user_id' => $user->id,
            'token_id' => $token->id,
            'path' => $path,
            'ip' => $ip,
            'issued_at' => now()->timestamp,
        ], self::TTL);

        return $code;
    }

    /**
     * Consumes [$code]. Returns [user, path] or null when the code is unknown, used, expired,
     * from another device, or its app session has ended.
     *
     * @return array{0: User, 1: string}|null
     */
    public function redeem(string $code, ?string $ip): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $code)) {
            return null;
        }
        $key = self::PREFIX . hash('sha256', $code);

        $data = Cache::lock($key . ':lock', 5)->get(fn () => Cache::pull($key));
        if (!is_array($data) || (now()->timestamp - (int) $data['issued_at']) > self::TTL) {
            return null;
        }
        if (config('auth.web_handoff_bind_ip', true) && $data['ip'] !== $ip) {
            return null;
        }
        $token = PersonalAccessToken::find($data['token_id']);
        if (!$token || (int) $token->tokenable_id !== (int) $data['user_id'] || $token->tokenable_type !== User::class) {
            return null;
        }
        $user = User::find($data['user_id']);

        return $user ? [$user, self::safePath($data['path'])] : null;
    }

    /**
     * A same-site path from [$target] (an absolute URL on this server or a path), or "/home".
     */
    public static function safePath(?string $target): string
    {
        $target = trim((string) $target);
        // Control characters and backslashes are refused before parsing: parse_url would turn them
        // into "_" and hide a header-injection or "/\\host" redirect attempt.
        if ($target === '' || preg_match('/[\\\\\x00-\x1f\x7f]/', $target)) {
            return '/home';
        }
        $parts = parse_url($target);
        if ($parts === false) {
            return '/home';
        }
        if (isset($parts['scheme']) || isset($parts['host'])) {
            $appHost = parse_url(config('app.url'), PHP_URL_HOST);
            $reqHost = request()->getHost();
            if (!in_array(strtolower($parts['host'] ?? ''), array_filter([strtolower((string) $appHost), strtolower($reqHost)]), true)) {
                return '/home';
            }
        }
        $path = $parts['path'] ?? '/';
        // Only plain absolute paths: no protocol-relative "//", backslashes or control characters.
        if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//') || preg_match('/[\\\\\x00-\x1f]/', $path)) {
            return '/home';
        }

        return $path . (isset($parts['query']) ? '?' . $parts['query'] : '');
    }
}
