<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignedDocument extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'tenant_id',
        'signing_process_id',
        'signer_id',
        'original_document_id',
        'storage_disk',
        'signed_path',
        'signed_name',
        'file_size',
        'content_hash',
        'original_hash',
        'hash_algorithm',
        'pkcs7_signature',
        'certificate_subject',
        'certificate_issuer',
        'certificate_serial',
        'certificate_fingerprint',
        'pades_level',
        'has_tsa_token',
        'tsa_token_id',
        'has_validation_data',
        'signature_position',
        'signature_visible',
        'signature_appearance',
        'embedded_metadata',
        'verification_code_id',
        'qr_code_embedded',
        'evidence_package_id',
        'adobe_validated',
        'adobe_validation_date',
        'validation_errors',
        'is_encrypted',
        'encrypted_at',
        'encryption_key_version',
        'status',
        'error_message',
        'signed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'file_size' => 'integer',
        'has_tsa_token' => 'boolean',
        'has_validation_data' => 'boolean',
        'signature_visible' => 'boolean',
        'signature_position' => 'array',
        'signature_appearance' => 'array',
        'embedded_metadata' => 'array',
        'qr_code_embedded' => 'boolean',
        'adobe_validated' => 'boolean',
        'adobe_validation_date' => 'datetime',
        'validation_errors' => 'array',
        'signed_at' => 'datetime',
    ];

    /**
     * Get the tenant that owns the signed document.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the signing process this document belongs to.
     */
    public function signingProcess(): BelongsTo
    {
        return $this->belongsTo(SigningProcess::class);
    }

    /**
     * Get the signer who signed this document.
     */
    public function signer(): BelongsTo
    {
        return $this->belongsTo(Signer::class);
    }

    /**
     * Get the original document.
     */
    public function originalDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'original_document_id');
    }

    /**
     * Get the TSA token for this signature.
     */
    public function tsaToken(): BelongsTo
    {
        return $this->belongsTo(TsaToken::class);
    }

    /**
     * Get the verification code for this signed document.
     */
    public function verificationCode(): BelongsTo
    {
        return $this->belongsTo(VerificationCode::class);
    }

    /**
     * Get the evidence package for this signing.
     */
    public function evidencePackage(): BelongsTo
    {
        return $this->belongsTo(EvidencePackage::class);
    }

    /**
     * Check if signature is valid (signed status).
     */
    public function isSigned(): bool
    {
        return $this->status === 'signed';
    }

    /**
     * Check if signature has error.
     */
    public function hasError(): bool
    {
        return $this->status === 'error';
    }

    /**
     * Check if signature is still in progress.
     */
    public function isSigning(): bool
    {
        return $this->status === 'signing';
    }

    /**
     * Check if this is a PAdES-B-LT signature.
     */
    public function isPadesLongTerm(): bool
    {
        return in_array($this->pades_level, ['B-LT', 'B-LTA']);
    }

    /**
     * Check if this signature has been validated in Adobe Reader.
     */
    public function isAdobeValidated(): bool
    {
        return $this->adobe_validated === true;
    }

    /**
     * Get the full path to the signed PDF file.
     */
    public function getFullPath(): string
    {
        return storage_path('app/'.$this->signed_path);
    }

    /**
     * Verify integrity by checking hash.
     */
    public function verifyIntegrity(): bool
    {
        if (! file_exists($this->getFullPath())) {
            return false;
        }

        $currentHash = hash_file('sha256', $this->getFullPath());

        return hash_equals($this->content_hash, $currentHash);
    }

    /**
     * Scope to filter by signing process.
     */
    public function scopeForProcess($query, int $processId)
    {
        return $query->where('signing_process_id', $processId);
    }

    /**
     * Scope to filter by signer.
     */
    public function scopeForSigner($query, int $signerId)
    {
        return $query->where('signer_id', $signerId);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get only successfully signed documents.
     */
    public function scopeSigned($query)
    {
        return $query->where('status', 'signed');
    }

    /**
     * Scope to filter by PAdES level.
     */
    public function scopeWithPadesLevel($query, string $level)
    {
        return $query->where('pades_level', $level);
    }
}
