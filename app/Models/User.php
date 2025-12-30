<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Traits\BelongsToTenant;
use App\Traits\HasPermissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    use BelongsToTenant;
    use HasFactory;
    use HasPermissions;
    use Notifiable;
    use SoftDeletes;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'role',
        'status',
        'name',
        'email',
        'password',
        'last_login_at',
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
            'role' => UserRole::class,
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
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

    /**
     * Scope para usuarios activos.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope para usuarios inactivos.
     */
    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    /**
     * Verificar si el usuario está activo.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Verificar si el usuario está inactivo.
     */
    public function isInactive(): bool
    {
        return $this->status === 'inactive';
    }

    /**
     * Activar usuario.
     */
    public function activate(): void
    {
        $this->update(['status' => 'active']);
    }

    /**
     * Desactivar usuario.
     */
    public function deactivate(): void
    {
        $this->update(['status' => 'inactive']);
    }

    /**
     * Actualizar último login.
     */
    public function updateLastLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }

    /**
     * Get role badge color.
     */
    public function getRoleBadgeColorAttribute(): string
    {
        return match ($this->role) {
            UserRole::SUPER_ADMIN => 'purple',
            UserRole::ADMIN => 'red',
            UserRole::OPERATOR => 'blue',
            UserRole::VIEWER => 'gray',
            default => 'gray',
        };
    }

    /**
     * Get status badge color.
     */
    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            'active' => 'green',
            'inactive' => 'gray',
            'invited' => 'yellow',
            default => 'gray',
        };
    }

    /**
     * Check if user has active signing processes.
     */
    public function hasActiveSigningProcesses(): bool
    {
        return $this->signingProcesses()
            ->whereIn('status', ['pending', 'in_progress'])
            ->exists();
    }

    /**
     * Relación con procesos de firma creados.
     */
    public function signingProcesses()
    {
        return $this->hasMany(SigningProcess::class, 'created_by');
    }
}
