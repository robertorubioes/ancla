<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Traits\BelongsToTenant;
use App\Traits\HasPermissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    use BelongsToTenant;
    use HasFactory;
    use HasPermissions;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'role',
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Verificar si puede administrar usuarios.
     */
    public function canManageUsers(): bool
    {
        return $this->isAdmin() || $this->isSuperAdmin();
    }

    /**
     * Scope para admins.
     */
    public function scopeAdmins($query)
    {
        return $query->where('role', UserRole::ADMIN->value);
    }

    /**
     * Scope para operators.
     */
    public function scopeOperators($query)
    {
        return $query->where('role', UserRole::OPERATOR->value);
    }

    /**
     * Scope para viewers.
     */
    public function scopeViewers($query)
    {
        return $query->where('role', UserRole::VIEWER->value);
    }

    /**
     * Scope para super admins (sin tenant).
     */
    public function scopeSuperAdmins($query)
    {
        return $query->whereNull('tenant_id')
            ->where('role', UserRole::SUPER_ADMIN->value);
    }
}
