<?php

namespace App\Services\Evidence;

use App\Models\GeolocationRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GeolocationService
{
    public function __construct(
        private readonly HashingService $hashingService,
        private readonly AuditTrailService $auditTrailService
    ) {}

    /**
     * Capture geolocation from GPS data or fallback to IP.
     *
     * @security GPS data is validated before storage.
     */
    public function capture(
        Request $request,
        Model $signable,
        ?array $gpsData = null,
        string $permissionStatus = 'unavailable',
        ?string $signerEmail = null,
        ?int $signerId = null
    ): GeolocationRecord {
        $tenant = app('tenant');
        $ipAddress = $this->getRealIp($request);

        // Validate GPS data if provided
        if ($gpsData !== null) {
            $gpsData = $this->validateGpsData($gpsData);
        }

        // Determine capture method
        $captureMethod = $this->determineCaptureMethod($gpsData, $permissionStatus);

        // Get IP-based geolocation as fallback or primary
        $ipGeoData = null;
        if ($captureMethod !== 'gps' || config('evidence.geolocation.request_gps')) {
            $ipGeoData = $this->getIpGeolocation($ipAddress);
        }

        // Create geolocation record
        $record = GeolocationRecord::create([
            'uuid' => Str::uuid(),
            'tenant_id' => $tenant?->id,
            'signable_type' => get_class($signable),
            'signable_id' => $signable->id,
            'signer_id' => $signerId,
            'signer_email' => $signerEmail,
            'capture_method' => $captureMethod,
            'permission_status' => $permissionStatus,
            // GPS data
            'latitude' => $gpsData['latitude'] ?? null,
            'longitude' => $gpsData['longitude'] ?? null,
            'accuracy_meters' => $gpsData['accuracy'] ?? null,
            'altitude_meters' => $gpsData['altitude'] ?? null,
            // IP geolocation data
            'ip_latitude' => $ipGeoData['latitude'] ?? null,
            'ip_longitude' => $ipGeoData['longitude'] ?? null,
            'ip_city' => $ipGeoData['city'] ?? null,
            'ip_region' => $ipGeoData['region'] ?? null,
            'ip_country' => $ipGeoData['country_code'] ?? null,
            'ip_country_name' => $ipGeoData['country_name'] ?? null,
            'ip_timezone' => $ipGeoData['timezone'] ?? null,
            'ip_isp' => $ipGeoData['isp'] ?? null,
            // Formatted address
            'formatted_address' => $this->formatAddress($gpsData, $ipGeoData),
            // Raw data
            'raw_gps_data' => $gpsData,
            'raw_ip_data' => $ipGeoData,
            'captured_at' => now(),
        ]);

        // Log to audit trail
        $this->auditTrailService->logEvent(
            'evidence.geolocation_captured',
            [
                'signable_type' => get_class($signable),
                'signable_id' => $signable->getKey(),
                'geolocation_id' => $record->id,
                'capture_method' => $captureMethod,
                'permission_status' => $permissionStatus,
                'coordinates' => $record->coordinates,
                'location' => $record->location,
                'precision_level' => $record->precision_level,
                'signer_email' => $signerEmail,
            ]
        );

        return $record;
    }

    /**
     * Determine capture method based on GPS data availability.
     */
    private function determineCaptureMethod(?array $gpsData, string $permissionStatus): string
    {
        if ($permissionStatus === 'denied') {
            return 'refused';
        }

        if ($permissionStatus === 'unavailable') {
            return 'unavailable';
        }

        if ($gpsData && isset($gpsData['latitude'], $gpsData['longitude'])) {
            return 'gps';
        }

        return 'ip';
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
                    // X-Forwarded-For can contain multiple IPs
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
     * Get geolocation from IP address.
     */
    public function getIpGeolocation(string $ipAddress): ?array
    {
        // Don't lookup private/localhost IPs
        if ($this->isPrivateIp($ipAddress)) {
            return [
                'latitude' => null,
                'longitude' => null,
                'city' => 'Local',
                'region' => null,
                'country_code' => null,
                'country_name' => 'Local Network',
                'timezone' => null,
                'isp' => 'Private Network',
            ];
        }

        // Check cache first
        $cacheKey = "geolocation:ip:{$ipAddress}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $provider = config('evidence.geolocation.ip_provider', 'ipapi');

        try {
            $data = match ($provider) {
                'ipinfo' => $this->fetchFromIpInfo($ipAddress),
                default => $this->fetchFromIpApi($ipAddress),
            };

            // Cache for 1 hour
            Cache::put($cacheKey, $data, 3600);

            return $data;
        } catch (\Exception $e) {
            report($e);

            return null;
        }
    }

    /**
     * Fetch geolocation from ipapi.co.
     */
    private function fetchFromIpApi(string $ipAddress): array
    {
        $response = Http::timeout(10)
            ->get("https://ipapi.co/{$ipAddress}/json/");

        if (! $response->successful()) {
            throw new \RuntimeException("ipapi.co request failed: {$response->status()}");
        }

        $data = $response->json();

        return [
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'city' => $data['city'] ?? null,
            'region' => $data['region'] ?? null,
            'country_code' => $data['country_code'] ?? null,
            'country_name' => $data['country_name'] ?? null,
            'timezone' => $data['timezone'] ?? null,
            'isp' => $data['org'] ?? null,
            'asn' => $data['asn'] ?? null,
        ];
    }

    /**
     * Fetch geolocation from ipinfo.io.
     */
    private function fetchFromIpInfo(string $ipAddress): array
    {
        $token = config('evidence.geolocation.ipinfo_token');

        $request = Http::timeout(10);
        if ($token) {
            $request = $request->withToken($token);
        }

        $response = $request->get("https://ipinfo.io/{$ipAddress}/json");

        if (! $response->successful()) {
            throw new \RuntimeException("ipinfo.io request failed: {$response->status()}");
        }

        $data = $response->json();

        // Parse location (format: "lat,lng")
        $location = explode(',', $data['loc'] ?? '');

        return [
            'latitude' => $location[0] ?? null,
            'longitude' => $location[1] ?? null,
            'city' => $data['city'] ?? null,
            'region' => $data['region'] ?? null,
            'country_code' => $data['country'] ?? null,
            'country_name' => $this->getCountryName($data['country'] ?? null),
            'timezone' => $data['timezone'] ?? null,
            'isp' => $data['org'] ?? null,
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
     * Format address from GPS or IP data.
     */
    private function formatAddress(?array $gpsData, ?array $ipGeoData): ?string
    {
        // If we have GPS data, we could reverse geocode it
        // For now, use IP geolocation address
        if ($ipGeoData) {
            $parts = array_filter([
                $ipGeoData['city'] ?? null,
                $ipGeoData['region'] ?? null,
                $ipGeoData['country_name'] ?? null,
            ]);

            return ! empty($parts) ? implode(', ', $parts) : null;
        }

        return null;
    }

    /**
     * Get country name from country code.
     */
    private function getCountryName(?string $code): ?string
    {
        if (! $code) {
            return null;
        }

        $countries = [
            'ES' => 'Spain',
            'US' => 'United States',
            'GB' => 'United Kingdom',
            'DE' => 'Germany',
            'FR' => 'France',
            'IT' => 'Italy',
            'PT' => 'Portugal',
            'MX' => 'Mexico',
            'AR' => 'Argentina',
            'CO' => 'Colombia',
            'CL' => 'Chile',
            'PE' => 'Peru',
            // Add more as needed
        ];

        return $countries[$code] ?? $code;
    }

    /**
     * Get geolocations for a signable.
     */
    public function getForSignable(Model $signable): \Illuminate\Database\Eloquent\Collection
    {
        return GeolocationRecord::where('signable_type', get_class($signable))
            ->where('signable_id', $signable->id)
            ->orderBy('captured_at', 'desc')
            ->get();
    }

    /**
     * Generate JavaScript code for client-side geolocation collection.
     */
    public function getCollectorScript(): string
    {
        $timeout = config('evidence.geolocation.gps_timeout', 10000);
        $highAccuracy = config('evidence.geolocation.high_accuracy', true) ? 'true' : 'false';

        return <<<JS
window.GeolocationCollector = {
    config: {
        timeout: {$timeout},
        highAccuracy: {$highAccuracy}
    },

    async collect() {
        const result = {
            gps_data: null,
            permission_status: 'unavailable'
        };

        if (!navigator.geolocation) {
            result.permission_status = 'unavailable';
            return result;
        }

        try {
            // Check permission status
            if (navigator.permissions) {
                const permission = await navigator.permissions.query({ name: 'geolocation' });
                result.permission_status = permission.state;

                if (permission.state === 'denied') {
                    return result;
                }
            }

            // Request position
            const position = await this.getCurrentPosition();
            result.gps_data = {
                latitude: position.coords.latitude,
                longitude: position.coords.longitude,
                accuracy: position.coords.accuracy,
                altitude: position.coords.altitude,
                altitude_accuracy: position.coords.altitudeAccuracy,
                heading: position.coords.heading,
                speed: position.coords.speed,
                timestamp: position.timestamp
            };
            result.permission_status = 'granted';

        } catch (error) {
            if (error.code === 1) { // PERMISSION_DENIED
                result.permission_status = 'denied';
            } else if (error.code === 2) { // POSITION_UNAVAILABLE
                result.permission_status = 'unavailable';
            } else { // TIMEOUT or other
                result.permission_status = 'prompt';
            }
        }

        return result;
    },

    getCurrentPosition() {
        return new Promise((resolve, reject) => {
            navigator.geolocation.getCurrentPosition(
                resolve,
                reject,
                {
                    enableHighAccuracy: this.config.highAccuracy,
                    timeout: this.config.timeout,
                    maximumAge: 0
                }
            );
        });
    }
};
JS;
    }

    /**
     * Validate GPS data from client.
     *
     * @security Ensures coordinates are within valid ranges and values are sanitized.
     *
     * @param  array  $data  Raw GPS data from client
     * @return array Validated GPS data (invalid values set to null)
     */
    private function validateGpsData(array $data): array
    {
        $validated = [];

        // Validate latitude: must be between -90 and 90
        $validated['latitude'] = $this->validateCoordinate(
            $data['latitude'] ?? null,
            -90.0,
            90.0
        );

        // Validate longitude: must be between -180 and 180
        $validated['longitude'] = $this->validateCoordinate(
            $data['longitude'] ?? null,
            -180.0,
            180.0
        );

        // Validate accuracy: must be positive, reasonable upper bound (100km)
        $validated['accuracy'] = $this->validatePositiveFloat(
            $data['accuracy'] ?? null,
            0.0,
            100000.0
        );

        // Validate altitude: reasonable bounds (-1000m to 50000m)
        $validated['altitude'] = $this->validateFloat(
            $data['altitude'] ?? null,
            -1000.0,
            50000.0
        );

        // Copy through altitude_accuracy, heading, speed if present
        $validated['altitude_accuracy'] = $this->validatePositiveFloat(
            $data['altitude_accuracy'] ?? null,
            0.0,
            10000.0
        );

        $validated['heading'] = $this->validateFloat(
            $data['heading'] ?? null,
            0.0,
            360.0
        );

        $validated['speed'] = $this->validatePositiveFloat(
            $data['speed'] ?? null,
            0.0,
            1000.0 // ~3600 km/h max
        );

        // Timestamp should be a positive integer
        if (isset($data['timestamp'])) {
            $timestamp = filter_var($data['timestamp'], FILTER_VALIDATE_INT);
            $validated['timestamp'] = ($timestamp !== false && $timestamp > 0) ? $timestamp : null;
        }

        return $validated;
    }

    /**
     * Validate a coordinate value within bounds.
     */
    private function validateCoordinate(mixed $value, float $min, float $max): ?float
    {
        if ($value === null) {
            return null;
        }

        $float = filter_var($value, FILTER_VALIDATE_FLOAT);
        if ($float === false || $float < $min || $float > $max) {
            return null;
        }

        return round($float, 8); // 8 decimal places = ~1mm precision
    }

    /**
     * Validate a positive float within bounds.
     */
    private function validatePositiveFloat(mixed $value, float $min, float $max): ?float
    {
        if ($value === null) {
            return null;
        }

        $float = filter_var($value, FILTER_VALIDATE_FLOAT);
        if ($float === false || $float < $min || $float > $max) {
            return null;
        }

        return $float;
    }

    /**
     * Validate a float within bounds (can be negative).
     */
    private function validateFloat(mixed $value, float $min, float $max): ?float
    {
        if ($value === null) {
            return null;
        }

        $float = filter_var($value, FILTER_VALIDATE_FLOAT);
        if ($float === false || $float < $min || $float > $max) {
            return null;
        }

        return $float;
    }
}
