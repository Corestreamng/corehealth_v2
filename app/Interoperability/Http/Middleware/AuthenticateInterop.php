<?php

namespace App\Interoperability\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticateInterop
{
    /**
     * Handle an incoming request for interoperability endpoints.
     * Enforces Sanctum Bearer tokens, Web sessions, or SHA256 Basic Auth.
     * Public exemption for FHIR CapabilityStatement metadata.
     */
    public function handle(Request $request, Closure $next)
    {
        // 1. Conformance / CapabilityStatement metadata is public per FHIR Foundation standard
        if ($request->is('*/metadata') || $request->is('*/fhir/r4/metadata')) {
            return $next($request);
        }

        // 2. Authenticate via Sanctum Bearer token
        if (Auth::guard('sanctum')->check()) {
            Auth::shouldUse('sanctum');

            return $next($request);
        }

        // 3. Authenticate via Web session (internal portals & test harness)
        if (Auth::guard('web')->check()) {
            return $next($request);
        }

        // 4. Authenticate via SHA-256 Basic Auth (for automated external sync pipelines)
        if ($request->header('Authorization') && str_starts_with($request->header('Authorization'), 'Basic ')) {
            $credentials = base64_decode(substr($request->header('Authorization'), 6));
            if ($credentials && str_contains($credentials, ':')) {
                [$username, $password] = explode(':', $credentials, 2);
                $expectedUser = config('services.interop.basic_user', 'corehealth_sync');
                $expectedSecret = config('services.interop.basic_secret', 'corehealth_secret_2026');
                if ($username === $expectedUser && hash('sha256', $password) === hash('sha256', $expectedSecret)) {
                    return $next($request);
                }
            }
        }

        // 5. Unauthenticated rejection
        if ($request->is('*/fhir/*') || $request->expectsJson()) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [
                    [
                        'severity' => 'error',
                        'code' => 'security',
                        'diagnostics' => 'Authentication required. Provide a valid Bearer token via Authorization header.',
                    ],
                ],
                'message' => 'Unauthenticated.',
            ], 401, [
                'Content-Type' => $request->is('*/fhir/*') ? 'application/fhir+json; charset=UTF-8' : 'application/json; charset=UTF-8',
                'WWW-Authenticate' => 'Bearer realm="CoreHealth Interoperability API"',
            ]);
        }

        return response()->json(['message' => 'Unauthenticated.'], 401);
    }
}
