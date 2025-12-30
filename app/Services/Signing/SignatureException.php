<?php

declare(strict_types=1);

namespace App\Services\Signing;

use Exception;

/**
 * Exception thrown when signature processing fails.
 */
class SignatureException extends Exception
{
    public const CODE_CONSENT_REQUIRED = 1001;

    public const CODE_INVALID_TYPE = 1002;

    public const CODE_INVALID_DATA = 1003;

    public const CODE_CANVAS_EMPTY = 1004;

    public const CODE_TEXT_TOO_SHORT = 1005;

    public const CODE_TEXT_TOO_LONG = 1006;

    public const CODE_INVALID_FORMAT = 1007;

    public const CODE_FILE_TOO_LARGE = 1008;

    public const CODE_INVALID_DIMENSIONS = 1009;

    public const CODE_CORRUPTED_IMAGE = 1010;

    public const CODE_OTP_NOT_VERIFIED = 1011;

    public const CODE_SIGNER_NOT_READY = 1012;

    /**
     * Create a consent required exception.
     */
    public static function consentRequired(): self
    {
        return new self('Explicit consent is required to sign the document.', self::CODE_CONSENT_REQUIRED);
    }

    /**
     * Create an invalid type exception.
     */
    public static function invalidType(string $type): self
    {
        return new self("Invalid signature type: {$type}. Must be 'draw', 'type', or 'upload'.", self::CODE_INVALID_TYPE);
    }

    /**
     * Create an invalid data exception.
     */
    public static function invalidData(string $reason): self
    {
        return new self("Invalid signature data: {$reason}", self::CODE_INVALID_DATA);
    }

    /**
     * Create a canvas empty exception.
     */
    public static function canvasEmpty(): self
    {
        return new self('Canvas signature cannot be empty. Please draw your signature.', self::CODE_CANVAS_EMPTY);
    }

    /**
     * Create a text too short exception.
     */
    public static function textTooShort(): self
    {
        return new self('Typed signature must be at least 2 characters.', self::CODE_TEXT_TOO_SHORT);
    }

    /**
     * Create a text too long exception.
     */
    public static function textTooLong(): self
    {
        return new self('Typed signature cannot exceed 100 characters.', self::CODE_TEXT_TOO_LONG);
    }

    /**
     * Create an invalid format exception.
     */
    public static function invalidFormat(): self
    {
        return new self('Invalid image format. Only PNG and JPEG are accepted.', self::CODE_INVALID_FORMAT);
    }

    /**
     * Create a file too large exception.
     */
    public static function fileTooLarge(): self
    {
        return new self('Image file size cannot exceed 2MB.', self::CODE_FILE_TOO_LARGE);
    }

    /**
     * Create an invalid dimensions exception.
     */
    public static function invalidDimensions(): self
    {
        return new self('Image dimensions cannot exceed 4000x4000 pixels.', self::CODE_INVALID_DIMENSIONS);
    }

    /**
     * Create a corrupted image exception.
     */
    public static function corruptedImage(): self
    {
        return new self('The uploaded image appears to be corrupted.', self::CODE_CORRUPTED_IMAGE);
    }

    /**
     * Create an OTP not verified exception.
     */
    public static function otpNotVerified(): self
    {
        return new self('OTP verification is required before signing.', self::CODE_OTP_NOT_VERIFIED);
    }

    /**
     * Create a signer not ready exception.
     */
    public static function signerNotReady(string $reason): self
    {
        return new self("Signer is not ready to sign: {$reason}", self::CODE_SIGNER_NOT_READY);
    }
}
