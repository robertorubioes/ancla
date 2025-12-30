<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UserInvitation extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'tenant_id',
        'email',
        'name',
        'role',
        'token',
        'expires_at',
        'accepted_at',
        'invited_by',
        'message',
        'resend_count',
        'last_resent_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'last_resent_at' => 'datetime',
        ];
    }

    /**
     * Get the user who sent the invitation.
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Check if invitation is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if invitation is accepted.
     */
    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    /**
     * Check if invitation is pending.
     */
    public function isPending(): bool
    {
        return ! $this->isAccepted() && ! $this->isExpired();
    }

    /**
     * Check if invitation can be resent.
     */
    public function canResend(): bool
    {
        return ! $this->isAccepted() && $this->resend_count < 3;
    }

    /**
     * Mark invitation as accepted.
     */
    public function markAsAccepted(): void
    {
        $this->update(['accepted_at' => now()]);
    }

    /**
     * Resend invitation with new token and expiration.
     */
    public function resend(): void
    {
        if (! $this->canResend()) {
            throw new \RuntimeException('Invitation cannot be resent. Maximum resend limit reached.');
        }

        $this->update([
            'token' => self::generateToken(),
            'expires_at' => now()->addDays(7),
            'resend_count' => $this->resend_count + 1,
            'last_resent_at' => now(),
        ]);
    }

    /**
     * Generate a secure random token.
     */
    public static function generateToken(): string
    {
        return Str::random(64);
    }

    /**
     * Create a new invitation.
     */
    public static function createInvitation(
        int $tenantId,
        string $email,
        string $name,
        UserRole $role,
        int $invitedBy,
        ?string $message = null
    ): self {
        return self::create([
            'tenant_id' => $tenantId,
            'email' => $email,
            'name' => $name,
            'role' => $role,
            'token' => self::generateToken(),
            'expires_at' => now()->addDays(7),
            'invited_by' => $invitedBy,
            'message' => $message,
        ]);
    }

    /**
     * Find invitation by token.
     */
    public static function findByToken(string $token): ?self
    {
        return self::where('token', $token)->first();
    }

    /**
     * Find valid invitation by token.
     */
    public static function findValidByToken(string $token): ?self
    {
        return self::where('token', $token)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Scope for pending invitations.
     */
    public function scopePending($query)
    {
        return $query->whereNull('accepted_at')
            ->where('expires_at', '>', now());
    }

    /**
     * Scope for accepted invitations.
     */
    public function scopeAccepted($query)
    {
        return $query->whereNotNull('accepted_at');
    }

    /**
     * Scope for expired invitations.
     */
    public function scopeExpired($query)
    {
        return $query->whereNull('accepted_at')
            ->where('expires_at', '<=', now());
    }

    /**
     * Get role badge color.
     */
    public function getRoleBadgeColorAttribute(): string
    {
        return match ($this->role) {
            UserRole::ADMIN => 'red',
            UserRole::OPERATOR => 'blue',
            UserRole::VIEWER => 'gray',
            default => 'gray',
        };
    }

    /**
     * Get status.
     */
    public function getStatusAttribute(): string
    {
        if ($this->isAccepted()) {
            return 'accepted';
        }

        if ($this->isExpired()) {
            return 'expired';
        }

        return 'pending';
    }

    /**
     * Get status badge color.
     */
    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            'accepted' => 'green',
            'expired' => 'red',
            'pending' => 'yellow',
            default => 'gray',
        };
    }
}
