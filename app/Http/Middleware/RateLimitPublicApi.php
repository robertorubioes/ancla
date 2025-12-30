<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to apply rate limiting to public verification API endpoints.
 *
 * Implements per-minute and per-day rate limits based on IP address.
 * Adds rate limit headers to responses.
 *
 * @see ADR-007 in docs/architecture/adr-007-sprint3-retention-verification-upload.md
 */
class RateLimitPublicApi
{
    /**
     * The rate limiter instance.
     */
    protected RateLimiter $limiter;

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $type = 'verification'): Response
    {
        $key = $this->resolveRequestSignature($request);

        // Get rate limits based on type
        [$perMinuteLimit, $perDayLimit] = $this->getLimits($type);

        // Check per-minute limit
        $minuteKey = $key.':minute';
        if ($this->limiter->tooManyAttempts($minuteKey, $perMinuteLimit)) {
            return $this->buildRateLimitResponse($request, $minuteKey, $perMinuteLimit, 60);
        }

        // Check per-day limit
        $dayKey = $key.':day';
        if ($this->limiter->tooManyAttempts($dayKey, $perDayLimit)) {
            return $this->buildRateLimitResponse($request, $dayKey, $perDayLimit, 86400);
        }

        // Increment both counters
        $this->limiter->hit($minuteKey, 60);
        $this->limiter->hit($dayKey, 86400);

        $response = $next($request);

        // Add rate limit headers
        return $this->addHeaders(
            $response,
            $minuteKey,
            $perMinuteLimit,
            $dayKey,
            $perDayLimit
        );
    }

    /**
     * Get rate limits for the given type.
     *
     * @return array{int, int} [per_minute, per_day]
     */
    protected function getLimits(string $type): array
    {
        return match ($type) {
            'download' => [
                config('verification.rate_limit.download_per_minute', 10),
                config('verification.rate_limit.download_per_day', 100),
            ],
            'signing' => [
                config('signing.rate_limit.per_minute', 10),
                config('signing.rate_limit.per_day', 200),
            ],
            default => [
                config('verification.rate_limit.per_minute', 60),
                config('verification.rate_limit.per_day', 1000),
            ],
        };
    }

    /**
     * Resolve the request signature for rate limiting.
     */
    protected function resolveRequestSignature(Request $request): string
    {
        return 'public_verification:'.$request->ip();
    }

    /**
     * Build a rate limit exceeded response.
     */
    protected function buildRateLimitResponse(
        Request $request,
        string $key,
        int $maxAttempts,
        int $decaySeconds
    ): Response {
        $retryAfter = $this->limiter->availableIn($key);

        $headers = [
            'X-RateLimit-Limit' => (string) $maxAttempts,
            'X-RateLimit-Remaining' => '0',
            'X-RateLimit-Reset' => (string) (time() + $retryAfter),
            'Retry-After' => (string) $retryAfter,
        ];

        $message = $decaySeconds === 60
            ? 'Too many verification requests per minute. Please try again later.'
            : 'Daily verification limit exceeded. Please try again tomorrow.';

        return response()->json([
            'error' => 'rate_limit_exceeded',
            'message' => $message,
            'retry_after' => $retryAfter,
        ], 429, $headers);
    }

    /**
     * Add rate limit headers to the response.
     */
    protected function addHeaders(
        Response $response,
        string $minuteKey,
        int $minuteLimit,
        string $dayKey,
        int $dayLimit
    ): Response {
        $minuteRemaining = $this->limiter->remaining($minuteKey, $minuteLimit);
        $dayRemaining = $this->limiter->remaining($dayKey, $dayLimit);

        // Use the more restrictive remaining count
        $remaining = min($minuteRemaining, $dayRemaining);
        $limit = $minuteLimit;

        // Calculate when the limit resets (use the earlier reset time)
        $minuteReset = time() + ($this->limiter->availableIn($minuteKey) ?: 60);
        $dayReset = time() + ($this->limiter->availableIn($dayKey) ?: 86400);

        // If minute limit is the constraining factor, show minute reset
        // Otherwise show day reset
        $reset = $minuteRemaining < $dayRemaining ? $minuteReset : $dayReset;

        $response->headers->set('X-RateLimit-Limit', (string) $limit);
        $response->headers->set('X-RateLimit-Remaining', (string) max(0, $remaining));
        $response->headers->set('X-RateLimit-Reset', (string) $reset);

        // Add detailed rate limit info
        $response->headers->set('X-RateLimit-Limit-Minute', (string) $minuteLimit);
        $response->headers->set('X-RateLimit-Remaining-Minute', (string) max(0, $minuteRemaining));
        $response->headers->set('X-RateLimit-Limit-Day', (string) $dayLimit);
        $response->headers->set('X-RateLimit-Remaining-Day', (string) max(0, $dayRemaining));

        return $response;
    }
}
