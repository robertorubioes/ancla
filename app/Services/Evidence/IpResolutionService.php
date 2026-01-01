<?php

namespace App\Services\Evidence;

use App\Models\IpResolutionRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class IpResolutionService
{
    public function __construct(
        private readonly HashingService $hashingService,
        private readonly AuditTrailService $auditTrailService
    ) {}

    /**
     * Capture IP resolution data.
     */
    public function capture(
        Request $request,
        Model $signable,
        ?string $signerEmail = null,
        ?int $signerId = null
    ): IpResolutionRecord {
        $tenant = app()->bound('tenant') ? app('tenant') : null;
        $ipAddress = $this->getRealIp($request);
        $ipVersion = $this->detectIpVersion($ipAddress);

        // Get reverse DNS
        $reverseDns = $this->getReverseDns($ipAddress);
        $reverseDnsVerified = $this->verifyReverseDns($ipAddress, $reverseDns);

        // Get IP info (ISP, ASN, etc.)
        $ipInfo = $this->getIpInfo($ipAddress);

        // Detect VPN/Proxy
        $vpnDetection = $this->detectVpnProxy($ipAddress);

        // Create record
        $record = IpResolutionRecord::create([
            'uuid' => Str::uuid(),
            'tenant_id' => $tenant?->id,
            'signable_type' => get_class($signable),
            'signable_id' => $signable->id,
            'signer_id' => $signerId,
            'signer_email' => $signerEmail,
            'ip_address' => $ipAddress,
            'ip_version' => $ipVersion,
            'reverse_dns' => $reverseDns,
            'reverse_dns_verified' => $reverseDnsVerified,
            'asn' => $ipInfo['asn'] ?? null,
            'asn_name' => $ipInfo['asn_name'] ?? null,
            'isp' => $ipInfo['isp'] ?? null,
            'organization' => $ipInfo['organization'] ?? null,
            'is_proxy' => $vpnDetection['is_proxy'] ?? false,
            'is_vpn' => $vpnDetection['is_vpn'] ?? false,
            'is_tor' => $vpnDetection['is_tor'] ?? false,
            'is_datacenter' => $vpnDetection['is_datacenter'] ?? false,
            'proxy_type' => $vpnDetection['proxy_type'] ?? null,
            'threat_score' => $vpnDetection['threat_score'] ?? null,
            'x_forwarded_for' => $request->header('X-Forwarded-For'),
            'x_real_ip' => $request->header('X-Real-IP'),
            'raw_data' => [
                'ip_info' => $ipInfo,
                'vpn_detection' => $vpnDetection,
                'headers' => $this->getRelevantHeaders($request),
            ],
            'checked_at' => now(),
        ]);

        // Log to audit trail with warnings if needed
        $eventData = [
            'ip_resolution_id' => $record->id,
            'ip_address' => $ipAddress,
            'ip_version' => $ipVersion,
            'reverse_dns' => $reverseDns,
            'isp' => $record->isp,
            'risk_level' => $record->risk_level,
            'signer_email' => $signerEmail,
        ];

        // Add warnings if suspicious
        if ($record->isSuspicious()) {
            $eventData['warnings'] = $record->active_warnings;
        }

        $eventData['signable_type'] = get_class($signable);
        $eventData['signable_id'] = $signable->getKey();

        $this->auditTrailService->logEvent(
            'evidence.ip_resolution_captured',
            $eventData
        );

        return $record;
    }

    /**
     * Get real IP address from request.
     *
     * @security Only trusts proxy headers from trusted proxies.
     *           Always validates IP format to prevent spoofing.
     */
    private function getRealIp(Request $request): string
    {
        // Only trust proxy headers if request came through trusted proxy
        if ($request->isFromTrustedProxy()) {
            $headers = ['X-Real-IP', 'X-Forwarded-For', 'CF-Connecting-IP'];

            foreach ($headers as $header) {
                $ip = $request->header($header);
                if ($ip) {
                    $ips = explode(',', $ip);
                    $candidateIp = trim($ips[0]);

                    // Validate IP format before trusting
                    if ($this->validateIpAddress($candidateIp)) {
                        return $candidateIp;
                    }
                }
            }
        }

        $ip = $request->ip() ?? '127.0.0.1';

        return $this->validateIpAddress($ip) ? $ip : '127.0.0.1';
    }

    /**
     * Validate IP address format.
     *
     * @security Prevents IP spoofing and injection attacks.
     */
    private function validateIpAddress(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Detect IP version (4 or 6).
     */
    private function detectIpVersion(string $ip): int
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 6 : 4;
    }

    /**
     * Get reverse DNS (PTR record).
     */
    public function getReverseDns(string $ipAddress): ?string
    {
        if ($this->isPrivateIp($ipAddress)) {
            return 'localhost';
        }

        $cacheKey = "reverse_dns:{$ipAddress}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached ?: null;
        }

        try {
            $hostname = gethostbyaddr($ipAddress);

            // gethostbyaddr returns the IP if it fails
            if ($hostname === $ipAddress) {
                Cache::put($cacheKey, '', 3600);

                return null;
            }

            Cache::put($cacheKey, $hostname, 3600);

            return $hostname;
        } catch (\Exception $e) {
            Cache::put($cacheKey, '', 3600);

            return null;
        }
    }

    /**
     * Verify reverse DNS by forward lookup.
     */
    private function verifyReverseDns(string $ipAddress, ?string $hostname): bool
    {
        if (! $hostname) {
            return false;
        }

        try {
            $resolvedIps = gethostbynamel($hostname);

            return $resolvedIps && in_array($ipAddress, $resolvedIps);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get IP information (ISP, ASN, etc.).
     */
    public function getIpInfo(string $ipAddress): array
    {
        if ($this->isPrivateIp($ipAddress)) {
            return [
                'isp' => 'Private Network',
                'organization' => 'Local',
                'asn' => null,
                'asn_name' => null,
            ];
        }

        $cacheKey = "ip_info:{$ipAddress}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = Http::timeout(10)
                ->get("https://ipapi.co/{$ipAddress}/json/");

            if (! $response->successful()) {
                return [];
            }

            $data = $response->json();

            $info = [
                'isp' => $data['org'] ?? null,
                'organization' => $data['org'] ?? null,
                'asn' => $data['asn'] ?? null,
                'asn_name' => $data['asn'] ?? null,
                'country_code' => $data['country_code'] ?? null,
                'country_name' => $data['country_name'] ?? null,
            ];

            Cache::put($cacheKey, $info, 3600);

            return $info;
        } catch (\Exception $e) {
            report($e);

            return [];
        }
    }

    /**
     * Detect VPN/Proxy usage.
     */
    public function detectVpnProxy(string $ipAddress): array
    {
        if ($this->isPrivateIp($ipAddress)) {
            return [
                'is_proxy' => false,
                'is_vpn' => false,
                'is_tor' => false,
                'is_datacenter' => false,
                'proxy_type' => null,
                'threat_score' => 0,
            ];
        }

        // Check if detection is enabled
        if (! config('evidence.ip_info.detect_vpn') && ! config('evidence.ip_info.detect_proxy')) {
            return [];
        }

        $cacheKey = "vpn_detection:{$ipAddress}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $proxyCheckKey = config('evidence.ip_info.proxycheck_key');

        // If we have a proxycheck.io key, use it for better detection
        if ($proxyCheckKey) {
            $result = $this->detectWithProxyCheck($ipAddress, $proxyCheckKey);
        } else {
            // Basic detection using ipapi.co (limited)
            $result = $this->basicVpnDetection($ipAddress);
        }

        Cache::put($cacheKey, $result, config('evidence.ip_info.cache_ttl', 3600));

        return $result;
    }

    /**
     * Detect VPN/Proxy using proxycheck.io API.
     */
    private function detectWithProxyCheck(string $ipAddress, string $apiKey): array
    {
        try {
            $response = Http::timeout(10)
                ->get("https://proxycheck.io/v2/{$ipAddress}", [
                    'key' => $apiKey,
                    'vpn' => 1,
                    'asn' => 1,
                    'risk' => 1,
                ]);

            if (! $response->successful()) {
                return $this->basicVpnDetection($ipAddress);
            }

            $data = $response->json();
            $ipData = $data[$ipAddress] ?? [];

            return [
                'is_proxy' => ($ipData['proxy'] ?? 'no') === 'yes',
                'is_vpn' => ($ipData['type'] ?? '') === 'VPN',
                'is_tor' => ($ipData['type'] ?? '') === 'TOR',
                'is_datacenter' => ($ipData['type'] ?? '') === 'Hosting',
                'proxy_type' => $ipData['type'] ?? null,
                'threat_score' => $ipData['risk'] ?? null,
            ];
        } catch (\Exception $e) {
            report($e);

            return $this->basicVpnDetection($ipAddress);
        }
    }

    /**
     * Basic VPN detection using heuristics.
     */
    private function basicVpnDetection(string $ipAddress): array
    {
        // Get ISP info
        $ipInfo = $this->getIpInfo($ipAddress);
        $isp = strtolower($ipInfo['isp'] ?? '');
        $org = strtolower($ipInfo['organization'] ?? '');

        // Known VPN/hosting providers keywords
        $vpnKeywords = [
            'vpn', 'proxy', 'private', 'anonymous', 'mullvad', 'nordvpn',
            'expressvpn', 'surfshark', 'cyberghost', 'protonvpn',
        ];

        $datacenterKeywords = [
            'amazon', 'aws', 'google', 'microsoft', 'azure', 'digitalocean',
            'linode', 'vultr', 'ovh', 'hetzner', 'hosting', 'cloud', 'datacenter',
        ];

        $isVpn = false;
        $isDatacenter = false;

        foreach ($vpnKeywords as $keyword) {
            if (str_contains($isp, $keyword) || str_contains($org, $keyword)) {
                $isVpn = true;
                break;
            }
        }

        foreach ($datacenterKeywords as $keyword) {
            if (str_contains($isp, $keyword) || str_contains($org, $keyword)) {
                $isDatacenter = true;
                break;
            }
        }

        return [
            'is_proxy' => false, // Can't detect with basic method
            'is_vpn' => $isVpn,
            'is_tor' => false, // Can't detect with basic method
            'is_datacenter' => $isDatacenter,
            'proxy_type' => $isVpn ? 'VPN (suspected)' : ($isDatacenter ? 'Hosting' : null),
            'threat_score' => null,
        ];
    }

    /**
     * Check if IP is private/local.
     */
    private function isPrivateIp(string $ip): bool
    {
        return ! filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /**
     * Get relevant request headers.
     */
    private function getRelevantHeaders(Request $request): array
    {
        $headers = [];
        $relevantHeaders = [
            'X-Forwarded-For',
            'X-Real-IP',
            'X-Forwarded-Proto',
            'CF-Connecting-IP',
            'CF-IPCountry',
            'Via',
        ];

        foreach ($relevantHeaders as $header) {
            $value = $request->header($header);
            if ($value) {
                $headers[$header] = $value;
            }
        }

        return $headers;
    }

    /**
     * Get IP resolutions for a signable.
     */
    public function getForSignable(Model $signable): \Illuminate\Database\Eloquent\Collection
    {
        return IpResolutionRecord::where('signable_type', get_class($signable))
            ->where('signable_id', $signable->id)
            ->orderBy('checked_at', 'desc')
            ->get();
    }

    /**
     * Check if IP has suspicious flags.
     */
    public function isSuspiciousIp(string $ipAddress): bool
    {
        $detection = $this->detectVpnProxy($ipAddress);

        return ($detection['is_vpn'] ?? false)
            || ($detection['is_proxy'] ?? false)
            || ($detection['is_tor'] ?? false);
    }
}
