<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class IpResolutionRecord extends Model
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
        'ip_address',
        'ip_version',
        'reverse_dns',
        'reverse_dns_verified',
        'asn',
        'asn_name',
        'isp',
        'organization',
        'is_proxy',
        'is_vpn',
        'is_tor',
        'is_datacenter',
        'proxy_type',
        'threat_score',
        'x_forwarded_for',
        'x_real_ip',
        'raw_data',
        'checked_at',
    ];

    protected $casts = [
        'reverse_dns_verified' => 'boolean',
        'is_proxy' => 'boolean',
        'is_vpn' => 'boolean',
        'is_tor' => 'boolean',
        'is_datacenter' => 'boolean',
        'raw_data' => 'array',
        'checked_at' => 'datetime',
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
     * Scope para IPs con VPN detectada.
     */
    public function scopeWithVpn($query)
    {
        return $query->where('is_vpn', true);
    }

    /**
     * Scope para IPs con proxy detectado.
     */
    public function scopeWithProxy($query)
    {
        return $query->where('is_proxy', true);
    }

    /**
     * Scope para IPs de Tor.
     */
    public function scopeFromTor($query)
    {
        return $query->where('is_tor', true);
    }

    /**
     * Scope para IPs de datacenter.
     */
    public function scopeFromDatacenter($query)
    {
        return $query->where('is_datacenter', true);
    }

    /**
     * Scope para IPs sospechosas (cualquier flag).
     */
    public function scopeSuspicious($query)
    {
        return $query->where(function ($q) {
            $q->where('is_vpn', true)
                ->orWhere('is_proxy', true)
                ->orWhere('is_tor', true)
                ->orWhere('is_datacenter', true);
        });
    }

    /**
     * Scope para IPs limpias (sin flags).
     */
    public function scopeClean($query)
    {
        return $query->where('is_vpn', false)
            ->where('is_proxy', false)
            ->where('is_tor', false)
            ->where('is_datacenter', false);
    }

    /**
     * Scope por dirección IP.
     */
    public function scopeByIp($query, string $ip)
    {
        return $query->where('ip_address', $ip);
    }

    /**
     * Verificar si es IPv4.
     */
    public function isIpv4(): bool
    {
        return $this->ip_version === 4;
    }

    /**
     * Verificar si es IPv6.
     */
    public function isIpv6(): bool
    {
        return $this->ip_version === 6;
    }

    /**
     * Verificar si tiene algún flag de sospecha.
     */
    public function isSuspicious(): bool
    {
        return $this->is_vpn || $this->is_proxy || $this->is_tor || $this->is_datacenter;
    }

    /**
     * Verificar si la IP es limpia (no tiene flags de sospecha).
     */
    public function hasCleanConnection(): bool
    {
        return ! $this->isSuspicious();
    }

    /**
     * Obtener lista de flags activos.
     */
    public function getActiveWarningsAttribute(): array
    {
        $warnings = [];

        if ($this->is_vpn) {
            $warnings[] = 'VPN detected';
        }
        if ($this->is_proxy) {
            $warnings[] = 'Proxy detected';
        }
        if ($this->is_tor) {
            $warnings[] = 'Tor exit node detected';
        }
        if ($this->is_datacenter) {
            $warnings[] = 'Datacenter IP detected';
        }

        return $warnings;
    }

    /**
     * Obtener nivel de riesgo basado en flags y threat_score.
     */
    public function getRiskLevelAttribute(): string
    {
        if ($this->is_tor) {
            return 'high';
        }

        if ($this->threat_score !== null) {
            if ($this->threat_score >= 70) {
                return 'high';
            }
            if ($this->threat_score >= 40) {
                return 'medium';
            }
        }

        if ($this->is_vpn || $this->is_proxy) {
            return 'medium';
        }

        if ($this->is_datacenter) {
            return 'low';
        }

        return 'none';
    }

    /**
     * Obtener información de red formateada.
     */
    public function getNetworkInfoAttribute(): ?string
    {
        $parts = array_filter([
            $this->isp,
            $this->asn ? "AS{$this->asn}" : null,
        ]);

        return ! empty($parts) ? implode(' - ', $parts) : null;
    }
}
