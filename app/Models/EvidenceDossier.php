<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class EvidenceDossier extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /**
     * Dossier type constants.
     */
    public const TYPE_AUDIT_TRAIL = 'audit_trail';

    public const TYPE_FULL_EVIDENCE = 'full_evidence';

    public const TYPE_LEGAL_PROOF = 'legal_proof';

    public const TYPE_EXECUTIVE_SUMMARY = 'executive_summary';

    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'signable_type',
        'signable_id',
        'dossier_type',
        'file_path',
        'file_name',
        'file_size',
        'file_hash',
        'page_count',
        'includes_document',
        'includes_audit_trail',
        'includes_device_info',
        'includes_geolocation',
        'includes_ip_info',
        'includes_consents',
        'includes_tsa_tokens',
        'platform_signature',
        'signature_algorithm',
        'signed_at',
        'tsa_token_id',
        'verification_code',
        'verification_url',
        'verification_qr_path',
        'audit_entries_count',
        'devices_count',
        'geolocations_count',
        'consents_count',
        'generated_by',
        'generated_at',
        'download_count',
        'last_downloaded_at',
    ];

    protected $casts = [
        'includes_document' => 'boolean',
        'includes_audit_trail' => 'boolean',
        'includes_device_info' => 'boolean',
        'includes_geolocation' => 'boolean',
        'includes_ip_info' => 'boolean',
        'includes_consents' => 'boolean',
        'includes_tsa_tokens' => 'boolean',
        'signed_at' => 'datetime',
        'generated_at' => 'datetime',
        'last_downloaded_at' => 'datetime',
    ];

    /**
     * Relación polimórfica con el objeto firmable.
     */
    public function signable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Relación con el token TSA.
     */
    public function tsaToken(): BelongsTo
    {
        return $this->belongsTo(TsaToken::class);
    }

    /**
     * Relación con el usuario que generó el dossier.
     */
    public function generatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * Scope por tipo de dossier.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('dossier_type', $type);
    }

    /**
     * Scope para dossiers completos.
     */
    public function scopeFullEvidence($query)
    {
        return $query->where('dossier_type', 'full_evidence');
    }

    /**
     * Scope para dossiers firmados.
     */
    public function scopeSigned($query)
    {
        return $query->whereNotNull('platform_signature');
    }

    /**
     * Scope con TSA.
     */
    public function scopeWithTsa($query)
    {
        return $query->whereNotNull('tsa_token_id');
    }

    /**
     * Scope por código de verificación.
     */
    public function scopeByVerificationCode($query, string $code)
    {
        return $query->where('verification_code', $code);
    }

    /**
     * Verificar si está firmado.
     */
    public function isSigned(): bool
    {
        return $this->platform_signature !== null;
    }

    /**
     * Verificar si tiene TSA.
     */
    public function hasTsa(): bool
    {
        return $this->tsa_token_id !== null;
    }

    /**
     * Verificar si tiene QR.
     */
    public function hasQr(): bool
    {
        return $this->verification_qr_path !== null;
    }

    /**
     * Obtener contenido del archivo.
     */
    public function getContent(): string
    {
        return Storage::disk('local')->get($this->file_path);
    }

    /**
     * Obtener tamaño formateado.
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->file_size;

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2).' GB';
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' bytes';
    }

    /**
     * Obtener descripción del tipo.
     */
    public function getDossierTypeDescriptionAttribute(): string
    {
        return match ($this->dossier_type) {
            'audit_trail' => 'Trail de auditoría',
            'full_evidence' => 'Evidencias completas',
            'legal_proof' => 'Prueba legal',
            'executive_summary' => 'Resumen ejecutivo',
            default => $this->dossier_type,
        };
    }

    /**
     * Obtener URL de verificación completa.
     */
    public function getVerificationFullUrlAttribute(): string
    {
        return $this->verification_url ?? route('evidence.verify', $this->verification_code);
    }

    /**
     * Registrar una descarga.
     */
    public function recordDownload(): void
    {
        $this->increment('download_count');
        $this->update(['last_downloaded_at' => now()]);
    }

    /**
     * Obtener lista de contenido incluido.
     */
    public function getIncludedContentAttribute(): array
    {
        $content = [];

        if ($this->includes_document) {
            $content[] = 'Documento original';
        }
        if ($this->includes_audit_trail) {
            $content[] = 'Trail de auditoría';
        }
        if ($this->includes_device_info) {
            $content[] = 'Información de dispositivos';
        }
        if ($this->includes_geolocation) {
            $content[] = 'Geolocalización';
        }
        if ($this->includes_ip_info) {
            $content[] = 'Información de red';
        }
        if ($this->includes_consents) {
            $content[] = 'Consentimientos';
        }
        if ($this->includes_tsa_tokens) {
            $content[] = 'Tokens TSA';
        }

        return $content;
    }

    /**
     * Obtener resumen de estadísticas.
     */
    public function getStatsSummaryAttribute(): array
    {
        return [
            'pages' => $this->page_count,
            'audit_entries' => $this->audit_entries_count,
            'devices' => $this->devices_count,
            'geolocations' => $this->geolocations_count,
            'consents' => $this->consents_count,
            'downloads' => $this->download_count,
        ];
    }

    /**
     * Get all valid dossier types.
     *
     * @return array<string>
     */
    public static function getDossierTypes(): array
    {
        return [
            self::TYPE_AUDIT_TRAIL,
            self::TYPE_FULL_EVIDENCE,
            self::TYPE_LEGAL_PROOF,
            self::TYPE_EXECUTIVE_SUMMARY,
        ];
    }

    /**
     * Generate a unique verification code.
     */
    public static function generateVerificationCode(): string
    {
        return strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 12));
    }
}
