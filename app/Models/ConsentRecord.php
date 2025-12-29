<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ConsentRecord extends Model
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
        'consent_type',
        'consent_version',
        'legal_text_hash',
        'legal_text_content',
        'legal_text_language',
        'action',
        'action_timestamp',
        'screenshot_path',
        'screenshot_hash',
        'screenshot_captured_at',
        'ui_element_id',
        'ui_visible_duration_ms',
        'scroll_to_bottom',
        'verification_hash',
        'tsa_token_id',
        'created_at',
    ];

    protected $casts = [
        'action_timestamp' => 'datetime',
        'screenshot_captured_at' => 'datetime',
        'scroll_to_bottom' => 'boolean',
        'created_at' => 'datetime',
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
    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signer_id');
    }

    /**
     * Relación con el token TSA.
     */
    public function tsaToken(): BelongsTo
    {
        return $this->belongsTo(TsaToken::class);
    }

    /**
     * Scope para consentimientos aceptados.
     */
    public function scopeAccepted($query)
    {
        return $query->where('action', 'accepted');
    }

    /**
     * Scope para consentimientos rechazados.
     */
    public function scopeRejected($query)
    {
        return $query->where('action', 'rejected');
    }

    /**
     * Scope para consentimientos revocados.
     */
    public function scopeRevoked($query)
    {
        return $query->where('action', 'revoked');
    }

    /**
     * Scope por tipo de consentimiento.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('consent_type', $type);
    }

    /**
     * Scope para consentimientos de firma.
     */
    public function scopeForSignature($query)
    {
        return $query->where('consent_type', 'signature');
    }

    /**
     * Scope por email de firmante.
     */
    public function scopeBySigner($query, string $email)
    {
        return $query->where('signer_email', $email);
    }

    /**
     * Scope con screenshot.
     */
    public function scopeWithScreenshot($query)
    {
        return $query->whereNotNull('screenshot_path');
    }

    /**
     * Scope con TSA.
     */
    public function scopeWithTsa($query)
    {
        return $query->whereNotNull('tsa_token_id');
    }

    /**
     * Verificar si fue aceptado.
     */
    public function isAccepted(): bool
    {
        return $this->action === 'accepted';
    }

    /**
     * Verificar si fue rechazado.
     */
    public function isRejected(): bool
    {
        return $this->action === 'rejected';
    }

    /**
     * Verificar si fue revocado.
     */
    public function isRevoked(): bool
    {
        return $this->action === 'revoked';
    }

    /**
     * Verificar si tiene screenshot.
     */
    public function hasScreenshot(): bool
    {
        return $this->screenshot_path !== null;
    }

    /**
     * Verificar si tiene TSA.
     */
    public function hasTsa(): bool
    {
        return $this->tsa_token_id !== null;
    }

    /**
     * Obtener tiempo de visualización en segundos.
     */
    public function getVisibleDurationSecondsAttribute(): ?float
    {
        if ($this->ui_visible_duration_ms === null) {
            return null;
        }

        return $this->ui_visible_duration_ms / 1000;
    }

    /**
     * Obtener descripción del tipo de consentimiento.
     */
    public function getConsentTypeDescriptionAttribute(): string
    {
        return match ($this->consent_type) {
            'signature' => 'Consentimiento de firma electrónica',
            'terms' => 'Aceptación de términos y condiciones',
            'privacy' => 'Aceptación de política de privacidad',
            'biometric' => 'Consentimiento de datos biométricos',
            'communication' => 'Consentimiento de comunicaciones',
            default => 'Consentimiento',
        };
    }

    /**
     * Obtener descripción de la acción.
     */
    public function getActionDescriptionAttribute(): string
    {
        return match ($this->action) {
            'accepted' => 'Aceptado',
            'rejected' => 'Rechazado',
            'revoked' => 'Revocado',
            default => $this->action,
        };
    }
}
