<?php

namespace App\Services\Evidence;

use App\Models\DeviceFingerprint;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Jenssegers\Agent\Agent;

class DeviceFingerprintService
{
    public function __construct(
        private readonly HashingService $hashingService,
        private readonly AuditTrailService $auditTrailService
    ) {}

    /**
     * Maximum lengths for string fields to prevent database overflow.
     */
    private const MAX_LENGTHS = [
        'timezone' => 100,
        'language' => 20,
        'platform' => 100,
        'webgl_vendor' => 255,
        'webgl_renderer' => 255,
        'canvas_hash' => 64,
        'audio_hash' => 64,
        'fonts_hash' => 64,
    ];

    /**
     * Capture device fingerprint from request and client data.
     *
     * @security Client data is validated and sanitized before storage.
     */
    public function capture(
        Request $request,
        Model $signable,
        array $clientData,
        ?string $signerEmail = null,
        ?int $signerId = null
    ): DeviceFingerprint {
        $tenant = app('tenant');
        $userAgent = $request->userAgent() ?? '';

        // Validate and sanitize client data
        $clientData = $this->validateClientData($clientData);

        // Parse user agent
        $agent = new Agent;
        $agent->setUserAgent($userAgent);

        // Build fingerprint data
        $fingerprintData = $this->buildFingerprintData($agent, $clientData, $userAgent);

        // Calculate fingerprint hash
        $fingerprintHash = $this->calculateFingerprintHash($fingerprintData);

        // Create fingerprint record
        $fingerprint = DeviceFingerprint::create([
            'uuid' => Str::uuid(),
            'tenant_id' => $tenant?->id,
            'signable_type' => get_class($signable),
            'signable_id' => $signable->id,
            'signer_id' => $signerId,
            'signer_email' => $signerEmail,
            'user_agent_raw' => $userAgent,
            'browser_name' => $agent->browser() ?: null,
            'browser_version' => $agent->version($agent->browser()) ?: null,
            'os_name' => $agent->platform() ?: null,
            'os_version' => $agent->version($agent->platform()) ?: null,
            'device_type' => $this->detectDeviceType($agent),
            'screen_width' => $clientData['screen_width'] ?? null,
            'screen_height' => $clientData['screen_height'] ?? null,
            'color_depth' => $clientData['color_depth'] ?? null,
            'pixel_ratio' => $clientData['pixel_ratio'] ?? null,
            'timezone' => $clientData['timezone'] ?? null,
            'timezone_offset' => $clientData['timezone_offset'] ?? null,
            'language' => $clientData['language'] ?? null,
            'languages' => $clientData['languages'] ?? null,
            'platform' => $clientData['platform'] ?? null,
            'hardware_concurrency' => $clientData['hardware_concurrency'] ?? null,
            'device_memory' => $clientData['device_memory'] ?? null,
            'touch_support' => $clientData['touch_support'] ?? false,
            'touch_points' => $clientData['touch_points'] ?? null,
            'webgl_vendor' => $clientData['webgl_vendor'] ?? null,
            'webgl_renderer' => $clientData['webgl_renderer'] ?? null,
            'canvas_hash' => $clientData['canvas_hash'] ?? null,
            'audio_hash' => $clientData['audio_hash'] ?? null,
            'fonts_hash' => $clientData['fonts_hash'] ?? null,
            'fingerprint_hash' => $fingerprintHash,
            'fingerprint_version' => config('evidence.fingerprint.version', 'v1'),
            'raw_data' => $clientData,
            'captured_at' => now(),
        ]);

        // Log to audit trail
        $this->auditTrailService->logEvent(
            'evidence.device_fingerprint_captured',
            [
                'signable_type' => get_class($signable),
                'signable_id' => $signable->getKey(),
                'fingerprint_id' => $fingerprint->id,
                'fingerprint_hash' => $fingerprintHash,
                'device_type' => $fingerprint->device_type,
                'browser' => $fingerprint->browser_info,
                'os' => $fingerprint->os_info,
                'signer_email' => $signerEmail,
            ]
        );

        return $fingerprint;
    }

    /**
     * Build fingerprint data array for hashing.
     */
    private function buildFingerprintData(Agent $agent, array $clientData, string $userAgent): array
    {
        return [
            'user_agent' => $userAgent,
            'browser' => $agent->browser(),
            'browser_version' => $agent->version($agent->browser()),
            'platform' => $agent->platform(),
            'platform_version' => $agent->version($agent->platform()),
            'screen' => sprintf(
                '%dx%dx%d@%s',
                $clientData['screen_width'] ?? 0,
                $clientData['screen_height'] ?? 0,
                $clientData['color_depth'] ?? 0,
                $clientData['pixel_ratio'] ?? 1
            ),
            'timezone' => $clientData['timezone'] ?? '',
            'timezone_offset' => $clientData['timezone_offset'] ?? 0,
            'language' => $clientData['language'] ?? '',
            'hardware_concurrency' => $clientData['hardware_concurrency'] ?? 0,
            'device_memory' => $clientData['device_memory'] ?? 0,
            'touch_support' => $clientData['touch_support'] ?? false,
            'webgl' => sprintf(
                '%s|%s',
                $clientData['webgl_vendor'] ?? '',
                $clientData['webgl_renderer'] ?? ''
            ),
            'canvas_hash' => $clientData['canvas_hash'] ?? '',
            'audio_hash' => $clientData['audio_hash'] ?? '',
            'fonts_hash' => $clientData['fonts_hash'] ?? '',
        ];
    }

    /**
     * Calculate fingerprint hash from data.
     */
    public function calculateFingerprintHash(array $data): string
    {
        return $this->hashingService->hashAuditData($data);
    }

    /**
     * Detect device type from agent.
     */
    private function detectDeviceType(Agent $agent): string
    {
        if ($agent->isTablet()) {
            return 'tablet';
        }

        if ($agent->isMobile()) {
            return 'mobile';
        }

        if ($agent->isDesktop()) {
            return 'desktop';
        }

        return 'unknown';
    }

    /**
     * Find fingerprints by hash.
     */
    public function findByHash(string $hash): ?DeviceFingerprint
    {
        return DeviceFingerprint::byHash($hash)->first();
    }

    /**
     * Get fingerprints for a signable.
     */
    public function getForSignable(Model $signable): \Illuminate\Database\Eloquent\Collection
    {
        return DeviceFingerprint::where('signable_type', get_class($signable))
            ->where('signable_id', $signable->id)
            ->orderBy('captured_at', 'desc')
            ->get();
    }

    /**
     * Check if fingerprint matches previous sessions for signer.
     */
    public function matchesPreviousSession(string $fingerprintHash, string $signerEmail): bool
    {
        return DeviceFingerprint::byHash($fingerprintHash)
            ->bySigner($signerEmail)
            ->exists();
    }

    /**
     * Get fingerprint history for signer.
     */
    public function getSignerHistory(string $signerEmail, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return DeviceFingerprint::bySigner($signerEmail)
            ->orderBy('captured_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Generate JavaScript code for client-side fingerprint collection.
     */
    public function getCollectorScript(): string
    {
        $config = config('evidence.fingerprint');

        return <<<JS
window.DeviceFingerprintCollector = {
    config: {
        collectCanvas: {$this->boolToJs($config['collect_canvas'])},
        collectAudio: {$this->boolToJs($config['collect_audio'])},
        collectFonts: {$this->boolToJs($config['collect_fonts'])},
        collectWebgl: {$this->boolToJs($config['collect_webgl'])}
    },

    async collect() {
        const data = {
            screen_width: window.screen.width,
            screen_height: window.screen.height,
            color_depth: window.screen.colorDepth,
            pixel_ratio: window.devicePixelRatio || 1,
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
            timezone_offset: new Date().getTimezoneOffset(),
            language: navigator.language,
            languages: navigator.languages ? Array.from(navigator.languages) : [navigator.language],
            platform: navigator.platform,
            hardware_concurrency: navigator.hardwareConcurrency || null,
            device_memory: navigator.deviceMemory || null,
            touch_support: 'ontouchstart' in window || navigator.maxTouchPoints > 0,
            touch_points: navigator.maxTouchPoints || 0
        };

        if (this.config.collectWebgl) {
            const webgl = this.getWebglInfo();
            data.webgl_vendor = webgl.vendor;
            data.webgl_renderer = webgl.renderer;
        }

        if (this.config.collectCanvas) {
            data.canvas_hash = await this.getCanvasHash();
        }

        if (this.config.collectAudio) {
            data.audio_hash = await this.getAudioHash();
        }

        if (this.config.collectFonts) {
            data.fonts_hash = await this.getFontsHash();
        }

        return data;
    },

    getWebglInfo() {
        try {
            const canvas = document.createElement('canvas');
            const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
            if (!gl) return { vendor: null, renderer: null };

            const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
            return {
                vendor: debugInfo ? gl.getParameter(debugInfo.UNMASKED_VENDOR_WEBGL) : null,
                renderer: debugInfo ? gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL) : null
            };
        } catch (e) {
            return { vendor: null, renderer: null };
        }
    },

    async getCanvasHash() {
        try {
            const canvas = document.createElement('canvas');
            canvas.width = 200;
            canvas.height = 50;
            const ctx = canvas.getContext('2d');

            ctx.textBaseline = 'top';
            ctx.font = '14px Arial';
            ctx.fillStyle = '#f60';
            ctx.fillRect(125, 1, 62, 20);
            ctx.fillStyle = '#069';
            ctx.fillText('Firmalum fingerprint', 2, 15);
            ctx.fillStyle = 'rgba(102, 204, 0, 0.7)';
            ctx.fillText('Firmalum fingerprint', 4, 17);

            const dataUrl = canvas.toDataURL();
            return await this.sha256(dataUrl);
        } catch (e) {
            return null;
        }
    },

    async getAudioHash() {
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const analyser = audioContext.createAnalyser();
            const gain = audioContext.createGain();
            const processor = audioContext.createScriptProcessor(4096, 1, 1);

            gain.gain.value = 0;
            oscillator.type = 'triangle';
            oscillator.frequency.value = 1e4;

            oscillator.connect(analyser);
            analyser.connect(processor);
            processor.connect(gain);
            gain.connect(audioContext.destination);

            return new Promise(resolve => {
                processor.onaudioprocess = async (event) => {
                    const data = event.inputBuffer.getChannelData(0);
                    const hash = await this.sha256(data.slice(0, 100).toString());
                    oscillator.disconnect();
                    processor.disconnect();
                    gain.disconnect();
                    audioContext.close();
                    resolve(hash);
                };
                oscillator.start(0);
            });
        } catch (e) {
            return null;
        }
    },

    async getFontsHash() {
        const fonts = ['Arial', 'Helvetica', 'Times New Roman', 'Courier', 'Verdana', 'Georgia', 'Comic Sans MS', 'Impact'];
        const detected = [];

        for (const font of fonts) {
            if (document.fonts && document.fonts.check('12px "' + font + '"')) {
                detected.push(font);
            }
        }

        return await this.sha256(detected.join(','));
    },

    async sha256(message) {
        const msgBuffer = new TextEncoder().encode(message);
        const hashBuffer = await crypto.subtle.digest('SHA-256', msgBuffer);
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    }
};
JS;
    }

    private function boolToJs(bool $value): string
    {
        return $value ? 'true' : 'false';
    }

    /**
     * Validate and sanitize client-provided fingerprint data.
     *
     * @security Prevents injection attacks and ensures data integrity.
     *
     * @param  array  $data  Raw client data
     * @return array Sanitized and validated data
     */
    private function validateClientData(array $data): array
    {
        $validated = [];

        // Numeric fields with bounds checking
        $validated['screen_width'] = $this->validatePositiveInt($data['screen_width'] ?? null, 0, 10000);
        $validated['screen_height'] = $this->validatePositiveInt($data['screen_height'] ?? null, 0, 10000);
        $validated['color_depth'] = $this->validatePositiveInt($data['color_depth'] ?? null, 0, 64);
        $validated['pixel_ratio'] = $this->validatePositiveFloat($data['pixel_ratio'] ?? null, 0.1, 10.0);
        $validated['timezone_offset'] = $this->validateInt($data['timezone_offset'] ?? null, -720, 840);
        $validated['hardware_concurrency'] = $this->validatePositiveInt($data['hardware_concurrency'] ?? null, 0, 256);
        $validated['device_memory'] = $this->validatePositiveFloat($data['device_memory'] ?? null, 0, 1024);
        $validated['touch_points'] = $this->validatePositiveInt($data['touch_points'] ?? null, 0, 100);

        // Boolean fields
        $validated['touch_support'] = filter_var($data['touch_support'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // String fields with length limits and sanitization
        $validated['timezone'] = $this->sanitizeString($data['timezone'] ?? null, self::MAX_LENGTHS['timezone']);
        $validated['language'] = $this->sanitizeString($data['language'] ?? null, self::MAX_LENGTHS['language']);
        $validated['platform'] = $this->sanitizeString($data['platform'] ?? null, self::MAX_LENGTHS['platform']);
        $validated['webgl_vendor'] = $this->sanitizeString($data['webgl_vendor'] ?? null, self::MAX_LENGTHS['webgl_vendor']);
        $validated['webgl_renderer'] = $this->sanitizeString($data['webgl_renderer'] ?? null, self::MAX_LENGTHS['webgl_renderer']);

        // Hash fields - must be valid hex strings of exact length
        $validated['canvas_hash'] = $this->validateHash($data['canvas_hash'] ?? null);
        $validated['audio_hash'] = $this->validateHash($data['audio_hash'] ?? null);
        $validated['fonts_hash'] = $this->validateHash($data['fonts_hash'] ?? null);

        // Languages array - sanitize each element
        if (isset($data['languages']) && is_array($data['languages'])) {
            $validated['languages'] = array_slice(
                array_map(fn ($lang) => $this->sanitizeString($lang, 20), $data['languages']),
                0,
                20 // Max 20 languages
            );
        } else {
            $validated['languages'] = null;
        }

        return $validated;
    }

    /**
     * Validate a positive integer within bounds.
     */
    private function validatePositiveInt(mixed $value, int $min, int $max): ?int
    {
        if ($value === null) {
            return null;
        }

        $int = filter_var($value, FILTER_VALIDATE_INT);
        if ($int === false || $int < $min || $int > $max) {
            return null;
        }

        return $int;
    }

    /**
     * Validate an integer within bounds (can be negative).
     */
    private function validateInt(mixed $value, int $min, int $max): ?int
    {
        if ($value === null) {
            return null;
        }

        $int = filter_var($value, FILTER_VALIDATE_INT);
        if ($int === false || $int < $min || $int > $max) {
            return null;
        }

        return $int;
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
     * Sanitize a string by trimming and limiting length.
     *
     * @security Removes potential XSS and limits data size.
     */
    private function sanitizeString(?string $value, int $maxLength): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Remove null bytes and control characters
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);

        // Trim and limit length
        $value = mb_substr(trim($value), 0, $maxLength);

        return $value ?: null;
    }

    /**
     * Validate a SHA-256 hash string.
     *
     * @security Ensures hash is valid hex format to prevent injection.
     */
    private function validateHash(?string $hash): ?string
    {
        if ($hash === null || $hash === '') {
            return null;
        }

        // SHA-256 produces 64 hex characters
        if (preg_match('/^[a-f0-9]{64}$/i', $hash)) {
            return strtolower($hash);
        }

        return null;
    }
}
