<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class BasicAuthSha256
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $clientId = env('CLIENT_ID');
        $clientSecret = env('CLIENT_SECRET');
        $expectedHash = hash('sha256', $clientId . ':' . $clientSecret);

        // Check for Authorization header
        if (!$request->hasHeader('Authorization')) {
            return response('Unauthorized', 401, ['WWW-Authenticate' => 'Basic realm="Corehealth"']);
        }

        // Extract credentials from header
        $authorizationHeader = $request->header('Authorization');
        list($authType, $credentials) = explode(' ', $authorizationHeader, 2);

        // Check if it's Basic Auth
        if ($authType !== 'Basic') {
            return response('Invalid authorization type', 400);
        }

        // Decode credentials
        $decodedCredentials = base64_decode($credentials);

        // Check format (CLIENTID:CLIENTSECRET)
        list($providedClientId, $providedClientSecret) = explode(':', $decodedCredentials, 2);
        if (!$providedClientId || !$providedClientSecret) {
            return response('Invalid credentials format', 401, ['WWW-Authenticate' => 'Basic realm="Corehealth"']);
        }

        // Validate hash
        $providedHash = hash('sha256', $providedClientId . ':' . $providedClientSecret);
        if ($expectedHash !== $providedHash) {
            return response('Unauthorized', 401, ['WWW-Authenticate' => 'Basic realm="Corehealth"']);
        }

        // Valid credentials, continue processing request
        return $next($request);
    }
}
