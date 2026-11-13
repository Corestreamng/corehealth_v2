<?php

namespace App\Interoperability\Services;

use App\Interoperability\Models\SdkClient;
use App\Interoperability\Models\SdkTokenAudit;
use Carbon\Carbon;
use Illuminate\Support\Str;

class SdkTokenService
{
    /**
     * Generate a signed JWT session token for an authenticated SDK client.
     */
    public function generateToken(
        SdkClient $client,
        array $claims = [],
        int $ttlSeconds = 3600,
        ?string $ipAddress = null
    ): string {
        $jti = 'jti_' . Str::random(32);
        $now = Carbon::now();
        $expiresAt = $now->copy()->addSeconds($ttlSeconds);

        $requestedScopes = isset($claims['scope'])
            ? (is_array($claims['scope']) ? $claims['scope'] : explode(' ', (string)$claims['scope']))
            : ['scribe:process', 'scribe:sign', 'interop:compositions'];

        // Filter against allowed scopes
        $allowedScopes = $client->allowed_scopes ?? ['*'];
        $grantedScopes = in_array('*', $allowedScopes, true)
            ? $requestedScopes
            : array_values(array_intersect($requestedScopes, $allowedScopes));

        $payload = [
            'iss' => config('app.url', 'https://corehealth.ng'),
            'sub' => $client->client_id,
            'client_id' => $client->client_id,
            'patient_id' => $claims['patient_id'] ?? null,
            'encounter_id' => $claims['encounter_id'] ?? null,
            'clinician_id' => $claims['clinician_id'] ?? null,
            'locale' => $claims['locale'] ?? 'en-NG',
            'scope' => $grantedScopes,
            'jti' => $jti,
            'iat' => $now->timestamp,
            'exp' => $expiresAt->timestamp,
        ];

        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256',
        ];

        $encodedHeader = $this->base64UrlEncode(json_encode($header));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload));
        $signature = $this->sign("$encodedHeader.$encodedPayload", $client->client_secret);
        $encodedSignature = $this->base64UrlEncode($signature);

        $jwt = "$encodedHeader.$encodedPayload.$encodedSignature";
        $tokenString = 'SKT_' . $jwt;

        // Record in audit table
        SdkTokenAudit::create([
            'client_id' => $client->client_id,
            'patient_id' => $claims['patient_id'] ?? null,
            'encounter_id' => $claims['encounter_id'] ?? null,
            'clinician_id' => $claims['clinician_id'] ?? null,
            'token_jti' => $jti,
            'scopes' => $grantedScopes,
            'issued_at' => $now,
            'expires_at' => $expiresAt,
            'ip_address' => $ipAddress,
        ]);

        return $tokenString;
    }

    /**
     * Validate an incoming SDK token string and return decoded claims or null.
     */
    public function validateToken(string $rawToken): ?array
    {
        $token = trim($rawToken);
        if (str_starts_with($token, 'Bearer ')) {
            $token = substr($token, 7);
        }
        if (str_starts_with($token, 'SKT_')) {
            $token = substr($token, 4);
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        $headerJson = $this->base64UrlDecode($encodedHeader);
        $payloadJson = $this->base64UrlDecode($encodedPayload);
        $signature = $this->base64UrlDecode($encodedSignature);

        if (!$headerJson || !$payloadJson) {
            return null;
        }

        $header = json_decode($headerJson, true);
        $payload = json_decode($payloadJson, true);

        if (!$header || !$payload || ($header['alg'] ?? '') !== 'HS256') {
            return null;
        }

        // Verify expiration
        if (!isset($payload['exp']) || $payload['exp'] < Carbon::now()->timestamp) {
            return null;
        }

        $clientId = $payload['client_id'] ?? $payload['sub'] ?? null;
        if (!$clientId) {
            return null;
        }

        $client = SdkClient::where('client_id', $clientId)->where('is_active', true)->first();
        if (!$client) {
            return null;
        }

        // Verify cryptographic signature
        $expectedSignature = $this->sign("$encodedHeader.$encodedPayload", $client->client_secret);
        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        // Check revocation
        $jti = $payload['jti'] ?? null;
        if ($jti) {
            $audit = SdkTokenAudit::where('token_jti', $jti)->first();
            if ($audit && $audit->isRevoked()) {
                return null;
            }
        }

        $payload['client_model'] = $client;

        return $payload;
    }

    /**
     * Revoke a token by its jti or raw token string.
     */
    public function revokeToken(string $tokenOrJti): bool
    {
        $jti = $tokenOrJti;
        if (str_contains($tokenOrJti, '.')) {
            $claims = $this->validateToken($tokenOrJti);
            $jti = $claims['jti'] ?? null;
        }

        if (!$jti) {
            return false;
        }

        $audit = SdkTokenAudit::where('token_jti', $jti)->first();
        if ($audit) {
            $audit->update(['revoked_at' => Carbon::now()]);

            return true;
        }

        return false;
    }

    protected function sign(string $data, string $secret): string
    {
        $key = hash('sha256', config('app.key') . $secret, true);

        return hash_hmac('sha256', $data, $key, true);
    }

    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    protected function base64UrlDecode(string $data): ?string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $padLen = 4 - $remainder;
            $data .= str_repeat('=', $padLen);
        }
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        return $decoded !== false ? $decoded : null;
    }
}
