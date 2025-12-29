<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'domain',
        'logo_path',
        'primary_color',
        'secondary_color',
        'status',
        'plan',
        'trial_ends_at',
        'settings',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'trial_ends_at' => 'datetime',
        ];
    }

    /**
     * Boot del modelo.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Tenant $tenant): void {
            $tenant->uuid = $tenant->uuid ?? Str::uuid()->toString();
        });
    }

    /**
     * Invalidar cache al actualizar.
     */
    protected static function booted(): void
    {
        static::saved(function (Tenant $tenant): void {
            cache()->forget("tenant:slug:{$tenant->slug}");
            cache()->forget("tenant:id:{$tenant->id}");
            if ($tenant->domain) {
                cache()->forget("tenant:domain:{$tenant->domain}");
            }
        });

        static::deleted(function (Tenant $tenant): void {
            cache()->forget("tenant:slug:{$tenant->slug}");
            cache()->forget("tenant:id:{$tenant->id}");
            if ($tenant->domain) {
                cache()->forget("tenant:domain:{$tenant->domain}");
            }
        });
    }

    /**
     * Usuarios del tenant.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Verificar si el tenant está activo.
     */
    public function isActive(): bool
    {
        return $this->status === 'active' ||
            ($this->status === 'trial' && $this->trial_ends_at?->isFuture());
    }

    /**
     * Verificar si el tenant está en período de prueba.
     */
    public function isOnTrial(): bool
    {
        return $this->status === 'trial' && $this->trial_ends_at?->isFuture();
    }

    /**
     * Verificar si el trial ha expirado.
     */
    public function hasTrialExpired(): bool
    {
        return $this->status === 'trial' && $this->trial_ends_at?->isPast();
    }

    /**
     * Verificar si el tenant está suspendido.
     */
    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /**
     * Obtener URL base del tenant.
     */
    public function getUrlAttribute(): string
    {
        if ($this->domain) {
            $scheme = app()->environment('production') ? 'https' : 'http';

            return "{$scheme}://{$this->domain}";
        }

        $baseDomain = config('app.base_domain', 'ancla.app');
        $scheme = app()->environment('production') ? 'https' : 'http';

        return "{$scheme}://{$this->slug}.{$baseDomain}";
    }

    /**
     * Obtener URL del logo del tenant.
     */
    public function getLogoUrl(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return Storage::disk('public')->url($this->logo_path);
    }

    /**
     * Obtener color primario.
     */
    public function getPrimaryColor(): string
    {
        return $this->primary_color ?? '#3B82F6';
    }

    /**
     * Obtener color secundario.
     */
    public function getSecondaryColor(): string
    {
        return $this->secondary_color ?? '#1E40AF';
    }

    /**
     * Obtener una configuración específica del tenant.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Establecer una configuración del tenant.
     */
    public function setSetting(string $key, mixed $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;
        $this->save();
    }

    /**
     * Scope para tenants activos.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->orWhere(function ($q) {
                $q->where('status', 'trial')
                    ->where('trial_ends_at', '>', now());
            });
    }

    /**
     * Scope para buscar por slug.
     */
    public function scopeBySlug($query, string $slug)
    {
        return $query->where('slug', $slug);
    }

    /**
     * Scope para buscar por dominio.
     */
    public function scopeByDomain($query, string $domain)
    {
        return $query->where('domain', $domain);
    }
}
