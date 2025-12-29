<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DeviceFingerprint extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'signable_type',
        'signable_id',
        'signer_id',
        'signer_email',
        'user_agent_raw',
        'browser_name',
        'browser_version',
        'os_name',
        'os_version',
        'device_type',
        'screen_width',
        'screen_height',
        'color_depth',
        'pixel_ratio',
        'timezone',
        'timezone_offset',
        'language',
        'languages',
        'platform',
        'hardware_concurrency',
        'device_memory',
        'touch_support',
        'touch_points',
        'webgl_vendor',
        'webgl_renderer',
        'canvas_hash',
        'audio_hash',
        'fonts_hash',
        'fingerprint_hash',
        'fingerprint_version',
        'raw_data',
        'captured_at',
    ];

    protected $casts = [
        'languages' => 'array',
        'raw_data' => 'array',
        'touch_support' => 'boolean',
        'pixel_ratio' => 'decimal:2',
        'device_memory' => 'decimal:2',
        'captured_at' => 'datetime',
    ];

    /**
     * Relación polimórfica con el objeto firmable.
     */
    public function signable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Relación con el firmante (si es usuario registrado).
     */
    public function signer()
    {
        return $this->belongsTo(User::class, 'signer_id');
    }

    /**
     * Scope para buscar por hash de fingerprint.
     */
    public function scopeByHash($query, string $hash)
    {
        return $query->where('fingerprint_hash', $hash);
    }

    /**
     * Scope para buscar por email de firmante.
     */
    public function scopeBySigner($query, string $email)
    {
        return $query->where('signer_email', $email);
    }

    /**
     * Scope para dispositivos móviles.
     */
    public function scopeMobile($query)
    {
        return $query->whereIn('device_type', ['mobile', 'tablet']);
    }

    /**
     * Scope para dispositivos de escritorio.
     */
    public function scopeDesktop($query)
    {
        return $query->where('device_type', 'desktop');
    }

    /**
     * Verificar si es un dispositivo móvil.
     */
    public function isMobile(): bool
    {
        return in_array($this->device_type, ['mobile', 'tablet']);
    }

    /**
     * Verificar si tiene soporte táctil.
     */
    public function hasTouch(): bool
    {
        return $this->touch_support === true;
    }

    /**
     * Obtener resolución de pantalla formateada.
     */
    public function getScreenResolutionAttribute(): ?string
    {
        if ($this->screen_width && $this->screen_height) {
            return "{$this->screen_width}x{$this->screen_height}";
        }

        return null;
    }

    /**
     * Obtener información del navegador formateada.
     */
    public function getBrowserInfoAttribute(): ?string
    {
        if ($this->browser_name) {
            return $this->browser_version
                ? "{$this->browser_name} {$this->browser_version}"
                : $this->browser_name;
        }

        return null;
    }

    /**
     * Obtener información del SO formateada.
     */
    public function getOsInfoAttribute(): ?string
    {
        if ($this->os_name) {
            return $this->os_version
                ? "{$this->os_name} {$this->os_version}"
                : $this->os_name;
        }

        return null;
    }
}
