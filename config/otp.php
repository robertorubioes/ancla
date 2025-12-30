<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | OTP Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for One-Time Password (OTP) verification system.
    | Used for signer verification before allowing document signatures.
    |
    */

    /**
     * Length of the OTP code (number of digits).
     */
    'length' => env('OTP_LENGTH', 6),

    /**
     * Expiration time in minutes.
     */
    'expires_minutes' => env('OTP_EXPIRES_MINUTES', 10),

    /**
     * Maximum verification attempts per code.
     */
    'max_attempts' => env('OTP_MAX_ATTEMPTS', 5),

    /**
     * Rate limit: maximum OTP requests per hour per signer.
     */
    'rate_limit_per_hour' => env('OTP_RATE_LIMIT_HOUR', 3),

];
