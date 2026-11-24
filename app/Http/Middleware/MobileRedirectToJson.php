<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Lets the staff app call web controllers that answer a form post with a redirect and a flash message.
 *
 * The controller stays as it is. When the caller asked for JSON and the controller redirected, the redirect becomes
 * the answer the app can read: `success` (flashed `error` means false), the flashed `message`, and the record id taken
 * from the redirect target (`/accounting/liabilities/5` gives `id: 5`). A redirect that carries validation errors is a
 * 422 with them.
 */
class MobileRedirectToJson
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if (!$response instanceof RedirectResponse || !$request->expectsJson()) {
            return $response;
        }

        $session = $response->getSession();
        $get = fn (string $key) => $session ? $session->get($key) : null;
        $errors = $get('errors');
        $error = $get('error');
        if ($errors && method_exists($errors, 'getBag') && $errors->getBag('default')->any()) {
            $bag = $errors->getBag('default');

            return new JsonResponse(['success' => false, 'message' => $bag->first(), 'errors' => $bag->toArray()], 422);
        }
        $message = $error ?: ($get('success') ?: ($get('message') ?: $get('info') ?: $get('warning')));
        $id = preg_match('#/(\d+)(?:/[a-z-]+)?/?$#', (string) parse_url($response->getTargetUrl(), PHP_URL_PATH), $m) ? (int) $m[1] : null;

        return new JsonResponse(array_filter([
            'success' => !$error,
            'message' => is_string($message) ? $message : null,
            'id' => $id,
        ], fn ($v) => $v !== null), $error ? 422 : 200);
    }
}
