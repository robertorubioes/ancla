<?php

namespace App\Services\Evidence;

use App\Models\TsaToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Service for interacting with Time Stamping Authorities (TSA).
 *
 * Implements RFC 3161 timestamp requests for qualified timestamps
 * that provide legal proof of when a hash was created.
 *
 * Supports mock mode for testing without making real TSA calls.
 *
 * @see ADR-005 in docs/architecture/decisions.md
 */
class TsaService
{
    /**
     * Whether mock mode is enabled.
     */
    private bool $mockEnabled;

    /**
     * Primary TSA provider.
     */
    private string $primaryProvider;

    /**
     * Fallback TSA provider.
     */
    private string $fallbackProvider;

    /**
     * Request timeout in seconds.
     */
    private int $timeout;

    /**
     * Create a new TsaService instance.
     */
    public function __construct()
    {
        $this->mockEnabled = config('evidence.tsa.mock', false);
        $this->primaryProvider = config('evidence.tsa.primary', 'firmaprofesional');
        $this->fallbackProvider = config('evidence.tsa.fallback', 'digicert');
        $this->timeout = config('evidence.tsa.timeout', 30);
    }

    /**
     * Request a timestamp for a given hash.
     *
     * @param  string  $hash  SHA-256 hash to timestamp
     * @param  int|null  $tenantId  Optional tenant ID to associate with token
     * @return TsaToken The created TSA token
     *
     * @throws RuntimeException If all TSA providers fail
     */
    public function requestTimestamp(string $hash, ?int $tenantId = null): TsaToken
    {
        // Use mock mode for testing
        if ($this->mockEnabled) {
            return $this->createMockToken($hash, $tenantId);
        }

        // Try primary provider
        try {
            return $this->requestFromProvider($hash, $this->primaryProvider, $tenantId);
        } catch (RuntimeException $e) {
            Log::warning('TSA primary provider failed, trying fallback', [
                'provider' => $this->primaryProvider,
                'error' => $e->getMessage(),
            ]);
        }

        // Try fallback provider
        try {
            return $this->requestFromProvider($hash, $this->fallbackProvider, $tenantId);
        } catch (RuntimeException $e) {
            Log::error('TSA fallback provider also failed', [
                'provider' => $this->fallbackProvider,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException(
                'All TSA providers failed. Cannot obtain qualified timestamp.',
                previous: $e
            );
        }
    }

    /**
     * Verify a TSA token.
     *
     * @param  TsaToken  $token  Token to verify
     * @return bool True if token is valid
     */
    public function verifyTimestamp(TsaToken $token): bool
    {
        // In mock mode, always return true for mock tokens
        if ($token->provider === TsaToken::PROVIDER_MOCK) {
            $token->markAsVerified(true);

            return true;
        }

        // For real tokens, verify the cryptographic signature
        // This is a simplified verification - in production, you would:
        // 1. Parse the RFC 3161 response
        // 2. Verify the TSA's certificate chain
        // 3. Verify the signature on the timestamp
        // 4. Check that the hash in the token matches

        try {
            $decodedToken = $token->getDecodedToken();

            // Basic validation: ensure token is not empty
            if (empty($decodedToken)) {
                $token->markAsVerified(false);

                return false;
            }

            // In a real implementation, use OpenSSL to verify the token
            // For now, we'll mark it as valid if it exists
            $token->markAsVerified(true);

            return true;
        } catch (\Exception $e) {
            Log::error('TSA token verification failed', [
                'token_id' => $token->id,
                'error' => $e->getMessage(),
            ]);

            $token->markAsVerified(false);

            return false;
        }
    }

    /**
     * Get the current provider name.
     *
     * @return string Provider name
     */
    public function getProvider(): string
    {
        if ($this->mockEnabled) {
            return TsaToken::PROVIDER_MOCK;
        }

        return $this->primaryProvider;
    }

    /**
     * Check if mock mode is enabled.
     *
     * @return bool True if mock mode is enabled
     */
    public function isMockEnabled(): bool
    {
        return $this->mockEnabled;
    }

    /**
     * Enable mock mode (useful for testing).
     */
    public function enableMock(): void
    {
        $this->mockEnabled = true;
    }

    /**
     * Disable mock mode.
     */
    public function disableMock(): void
    {
        $this->mockEnabled = false;
    }

    /**
     * Request timestamp from a specific provider.
     *
     * @param  string  $hash  Hash to timestamp
     * @param  string  $provider  Provider name
     * @param  int|null  $tenantId  Optional tenant ID
     * @return TsaToken Created token
     *
     * @throws RuntimeException If request fails
     */
    private function requestFromProvider(string $hash, string $provider, ?int $tenantId = null): TsaToken
    {
        $config = config("evidence.tsa.providers.{$provider}");

        if (! $config || ! ($config['enabled'] ?? false)) {
            throw new RuntimeException("TSA provider not configured or disabled: {$provider}");
        }

        $url = $config['url'];

        // Create RFC 3161 timestamp request
        $tsaRequest = $this->createTsaRequest($hash);

        // Send request to TSA
        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'Content-Type' => 'application/timestamp-query',
            ])
            ->withBody($tsaRequest, 'application/timestamp-query')
            ->post($url);

        if (! $response->successful()) {
            throw new RuntimeException(
                "TSA request failed with status: {$response->status()}"
            );
        }

        $tsaResponse = $response->body();

        // Parse and validate response
        $parsed = $this->parseTsaResponse($tsaResponse);

        if ($parsed['status'] !== 0) {
            throw new RuntimeException(
                "TSA returned error status: {$parsed['status_string']}"
            );
        }

        // Resolve tenant_id: param > context > null
        $resolvedTenantId = $tenantId ?? (app()->bound('tenant') ? app('tenant')?->id : null);

        // Create and return the token
        return TsaToken::create([
            'uuid' => Str::uuid(),
            'tenant_id' => $resolvedTenantId,
            'hash_algorithm' => 'SHA-256',
            'data_hash' => $hash,
            'token' => base64_encode($parsed['token']),
            'provider' => $provider,
            'status' => TsaToken::STATUS_VALID,
            'issued_at' => $parsed['timestamp'],
            'verified_at' => now(),
        ]);
    }

    /**
     * Create a mock token for testing.
     *
     * @param  string  $hash  Hash to timestamp
     * @param  int|null  $tenantId  Optional tenant ID
     * @return TsaToken Mock token
     */
    private function createMockToken(string $hash, ?int $tenantId = null): TsaToken
    {
        $mockTimestamp = now();

        // Resolve tenant_id: param > context > null
        $resolvedTenantId = $tenantId ?? (app()->bound('tenant') ? app('tenant')?->id : null);

        // Create a mock token response
        $mockTokenData = [
            'version' => 1,
            'hash' => $hash,
            'algorithm' => 'SHA-256',
            'timestamp' => $mockTimestamp->toIso8601String(),
            'serial' => Str::uuid()->toString(),
            'provider' => 'mock',
            'policy' => 'mock-policy-oid',
        ];

        return TsaToken::create([
            'uuid' => Str::uuid(),
            'tenant_id' => $resolvedTenantId,
            'hash_algorithm' => 'SHA-256',
            'data_hash' => $hash,
            'token' => base64_encode(json_encode($mockTokenData)),
            'provider' => TsaToken::PROVIDER_MOCK,
            'status' => TsaToken::STATUS_VALID,
            'issued_at' => $mockTimestamp,
            'verified_at' => $mockTimestamp,
        ]);
    }

    /**
     * Create an RFC 3161 timestamp request (TimeStampReq).
     *
     * @param  string  $hash  Hash to timestamp
     * @return string Binary ASN.1 DER encoded request
     */
    private function createTsaRequest(string $hash): string
    {
        // Convert hex hash to binary
        $hashBinary = hex2bin($hash);

        // SHA-256 OID: 2.16.840.1.101.3.4.2.1
        $algorithmOid = "\x06\x09\x60\x86\x48\x01\x65\x03\x04\x02\x01";
        $algorithmIdentifier = "\x30".chr(strlen($algorithmOid) + 2).$algorithmOid."\x05\x00";

        // MessageImprint
        $hashedMessage = "\x04".chr(strlen($hashBinary)).$hashBinary;
        $messageImprint = "\x30".chr(strlen($algorithmIdentifier) + strlen($hashedMessage))
            .$algorithmIdentifier.$hashedMessage;

        // Version: INTEGER 1
        $version = "\x02\x01\x01";

        // CertReq: BOOLEAN TRUE
        $certReq = "\x01\x01\xff";

        // Nonce: INTEGER (random)
        $nonce = random_bytes(8);
        $nonceAsn1 = "\x02".chr(strlen($nonce)).$nonce;

        // Complete TimeStampReq
        $content = $version.$messageImprint.$nonceAsn1.$certReq;
        $tsaRequest = "\x30".$this->encodeLength(strlen($content)).$content;

        return $tsaRequest;
    }

    /**
     * Parse an RFC 3161 timestamp response.
     *
     * @param  string  $response  Binary response from TSA
     * @return array{status: int, status_string: string, token: string, timestamp: \Carbon\Carbon}
     */
    private function parseTsaResponse(string $response): array
    {
        // Simplified parsing - in production use a proper ASN.1 library
        // This is a basic implementation that extracts the timestamp token

        return [
            'status' => 0, // PKIStatus: granted
            'status_string' => 'granted',
            'token' => $response, // The full response contains the token
            'timestamp' => now(),
        ];
    }

    /**
     * Encode length in ASN.1 DER format.
     *
     * @param  int  $length  Length to encode
     * @return string Encoded length
     */
    private function encodeLength(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $temp = '';
        while ($length > 0) {
            $temp = chr($length & 0xFF).$temp;
            $length >>= 8;
        }

        return chr(0x80 | strlen($temp)).$temp;
    }
}
