<?php

namespace App\Interoperability\Http\Middleware;

use App\Interoperability\Services\SdkTokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AuthenticateSdkToken
{
    protected SdkTokenService $tokenService;

    public function __construct(SdkTokenService $tokenService)
    {
        $this->tokenService = $tokenService;
    }

    /**
     * Handle an incoming request for SDK endpoints.
     * Validates the short-lived signed JWT session token.
     */
    public function handle(Request $request, Closure $next, ?string $requiredScope = null)
    {
        $rawToken = $request->bearerToken()
            ?: $request->header('Authorization')
            ?: $request->query('token')
            ?: $request->input('sdk_token');

        if (!$rawToken) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [
                    [
                        'severity' => 'error',
                        'code' => 'security',
                        'diagnostics' => 'Authentication required. Missing SDK session token in Authorization header or token parameter.',
                    ],
                ],
                'message' => 'Unauthenticated SDK session. Provide a valid SKT_ bearer token.',
            ], 401, [
                'Content-Type' => 'application/json; charset=UTF-8',
                'WWW-Authenticate' => 'Bearer realm="CoreHealth AI Scribe SDK"',
            ]);
        }

        $claims = $this->tokenService->validateToken($rawToken);
        if (!$claims) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [
                    [
                        'severity' => 'error',
                        'code' => 'security',
                        'diagnostics' => 'Invalid or expired SDK session token.',
                    ],
                ],
                'message' => 'Invalid or expired SDK session token.',
            ], 401, [
                'Content-Type' => 'application/json; charset=UTF-8',
            ]);
        }

        // Check required scope if specified
        if ($requiredScope) {
            $grantedScopes = $claims['scope'] ?? [];
            if (!in_array('*', $grantedScopes, true) && !in_array($requiredScope, $grantedScopes, true)) {
                return response()->json([
                    'resourceType' => 'OperationOutcome',
                    'issue' => [
                        [
                            'severity' => 'error',
                            'code' => 'forbidden',
                            'diagnostics' => "Forbidden. Token lacks required scope: {$requiredScope}",
                        ],
                    ],
                    'message' => "Insufficient permissions. Required scope: {$requiredScope}",
                ], 403, [
                    'Content-Type' => 'application/json; charset=UTF-8',
                ]);
            }
        }

        // Apply per-client rate limit
        $clientId = $claims['client_id'] ?? 'unknown';
        $clientModel = $claims['client_model'] ?? null;
        $maxAttempts = $clientModel ? $clientModel->rate_limit_rpm : 120;
        $rateLimitKey = 'sdk_limit:' . $clientId;

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [
                    [
                        'severity' => 'error',
                        'code' => 'throttled',
                        'diagnostics' => "Too many requests. Rate limit exceeded for client: {$clientId}. Retry in {$seconds} seconds.",
                    ],
                ],
                'message' => 'Rate limit exceeded.',
            ], 429, [
                'Retry-After' => $seconds,
            ]);
        }
        RateLimiter::hit($rateLimitKey, 60);

        // Inject claims into request attributes
        $request->attributes->set('sdk_claims', $claims);
        $request->attributes->set('sdk_client', $clientModel);

        return $next($request);
    }
}
