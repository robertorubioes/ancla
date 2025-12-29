<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class GeolocationRecord extends Model
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
        'capture_method',
        'permission_status',
        'latitude',
        'longitude',
        'accuracy_meters',
        'altitude_meters',
        'ip_latitude',
        'ip_longitude',
        'ip_city',
        'ip_region',
        'ip_country',
        'ip_country_name',
        'ip_timezone',
        'ip_isp',
        'formatted_address',
        'raw_gps_data',
        'raw_ip_data',
        'captured_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'accuracy_meters' => 'decimal:2',
        'altitude_meters' => 'decimal:2',
        'ip_latitude' => 'decimal:8',
        'ip_longitude' => 'decimal:8',
        'raw_gps_data' => 'array',
        'raw_ip_data' => 'array',
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
     * Scope para registros con GPS.
     */
    public function scopeWithGps($query)
    {
        return $query->where('capture_method', 'gps');
    }

    /**
     * Scope para registros con IP fallback.
     */
    public function scopeWithIpFallback($query)
    {
        return $query->where('capture_method', 'ip');
    }

    /**
     * Scope para registros donde el usuario rechazó GPS.
     */
    public function scopeRefused($query)
    {
        return $query->where('capture_method', 'refused');
    }

    /**
     * Scope por país.
     */
    public function scopeFromCountry($query, string $countryCode)
    {
        return $query->where('ip_country', strtoupper($countryCode));
    }

    /**
     * Verificar si se capturó por GPS.
     */
    public function isGps(): bool
    {
        return $this->capture_method === 'gps';
    }

    /**
     * Verificar si fue fallback a IP.
     */
    public function isIpFallback(): bool
    {
        return $this->capture_method === 'ip';
    }

    /**
     * Verificar si el usuario rechazó.
     */
    public function wasRefused(): bool
    {
        return $this->capture_method === 'refused';
    }

    /**
     * Obtener la latitud efectiva (GPS o IP).
     */
    public function getEffectiveLatitudeAttribute(): ?float
    {
        return $this->latitude ?? $this->ip_latitude;
    }

    /**
     * Obtener la longitud efectiva (GPS o IP).
     */
    public function getEffectiveLongitudeAttribute(): ?float
    {
        return $this->longitude ?? $this->ip_longitude;
    }

    /**
     * Obtener coordenadas formateadas.
     */
    public function getCoordinatesAttribute(): ?string
    {
        $lat = $this->effective_latitude;
        $lng = $this->effective_longitude;

        if ($lat !== null && $lng !== null) {
            return sprintf('%.6f, %.6f', $lat, $lng);
        }

        return null;
    }

    /**
     * Obtener la ubicación legible.
     */
    public function getLocationAttribute(): ?string
    {
        if ($this->formatted_address) {
            return $this->formatted_address;
        }

        $parts = array_filter([
            $this->ip_city,
            $this->ip_region,
            $this->ip_country_name,
        ]);

        return ! empty($parts) ? implode(', ', $parts) : null;
    }

    /**
     * Obtener nivel de precisión descriptivo.
     */
    public function getPrecisionLevelAttribute(): string
    {
        if ($this->isGps() && $this->accuracy_meters !== null) {
            if ($this->accuracy_meters <= 10) {
                return 'high';
            }
            if ($this->accuracy_meters <= 100) {
                return 'medium';
            }

            return 'low';
        }

        if ($this->isIpFallback()) {
            return 'approximate';
        }

        return 'unknown';
    }
}
