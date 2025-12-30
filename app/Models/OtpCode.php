<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * OTP Code model for signer verification.
 *
 * @property int $id
 * @property int $signer_id
 * @property string $code_hash
 * @property \Carbon\Carbon $expires_at
 * @property int $attempts
 * @property bool $verified
 * @property \Carbon\Carbon|null $verified_at
 * @property \Carbon\Carbon|null $sent_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Signer $signer
 */
class OtpCode extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'signer_id',
        'code_hash',
        'expires_at',
        'attempts',
        'verified',
        'verified_at',
        'sent_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'signer_id' => 'integer',
        'expires_at' => 'datetime',
        'attempts' => 'integer',
        'verified' => 'boolean',
        'verified_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    /**
     * Get the signer that owns the OTP code.
     */
    public function signer(): BelongsTo
    {
        return $this->belongsTo(Signer::class);
    }

    /**
     * Check if the OTP code is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if the OTP code is verified.
     */
    public function isVerified(): bool
    {
        return $this->verified;
    }

    /**
     * Check if the OTP code has exceeded max attempts.
     */
    public function hasExceededMaxAttempts(): bool
    {
        return $this->attempts >= config('otp.max_attempts', 5);
    }

    /**
     * Check if the OTP code can be used for verification.
     */
    public function canBeUsed(): bool
    {
        return ! $this->isExpired()
            && ! $this->isVerified()
            && ! $this->hasExceededMaxAttempts();
    }

    /**
     * Increment the attempts counter.
     */
    public function incrementAttempts(): bool
    {
        $this->attempts++;

        return $this->save();
    }

    /**
     * Mark the OTP code as verified.
     */
    public function markAsVerified(): bool
    {
        $this->verified = true;
        $this->verified_at = now();

        return $this->save();
    }

    /**
     * Mark the OTP code as sent.
     */
    public function markAsSent(): bool
    {
        $this->sent_at = now();

        return $this->save();
    }
}
