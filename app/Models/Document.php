<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Document model representing uploaded PDF documents.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property int $user_id
 * @property string $original_filename
 * @property string $original_extension
 * @property string $mime_type
 * @property int $file_size
 * @property int|null $page_count
 * @property string $storage_disk
 * @property string $storage_path
 * @property string $stored_filename
 * @property bool $is_encrypted
 * @property string|null $encryption_key_id
 * @property string $sha256_hash
 * @property string $hash_algorithm
 * @property \Carbon\Carbon|null $hash_verified_at
 * @property int|null $upload_tsa_token_id
 * @property string|null $thumbnail_path
 * @property \Carbon\Carbon|null $thumbnail_generated_at
 * @property array|null $pdf_metadata
 * @property string|null $pdf_version
 * @property bool $is_pdf_a
 * @property bool $has_signatures
 * @property bool $has_encryption
 * @property bool $has_javascript
 * @property string $status
 * @property string|null $error_message
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * @property-read Tenant $tenant
 * @property-read User $user
 * @property-read TsaToken|null $uploadTsaToken
 * @property-read \Illuminate\Database\Eloquent\Collection<EvidencePackage> $evidencePackages
 */
class Document extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'tenant_id',
        'user_id',
        'original_filename',
        'original_extension',
        'mime_type',
        'file_size',
        'page_count',
        'storage_disk',
        'storage_path',
        'stored_filename',
        'is_encrypted',
        'encryption_key_id',
        'sha256_hash',
        'hash_algorithm',
        'hash_verified_at',
        'upload_tsa_token_id',
        'thumbnail_path',
        'thumbnail_generated_at',
        'pdf_metadata',
        'pdf_version',
        'is_pdf_a',
        'has_signatures',
        'has_encryption',
        'has_javascript',
        'status',
        'error_message',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'file_size' => 'integer',
        'page_count' => 'integer',
        'is_encrypted' => 'boolean',
        'is_pdf_a' => 'boolean',
        'has_signatures' => 'boolean',
        'has_encryption' => 'boolean',
        'has_javascript' => 'boolean',
        'pdf_metadata' => 'array',
        'hash_verified_at' => 'datetime',
        'thumbnail_generated_at' => 'datetime',
    ];

    /**
     * Accessor for content_hash (alias for sha256_hash).
     */
    public function getContentHashAttribute(): string
    {
        return $this->sha256_hash;
    }

    /**
     * Mutator for content_hash (alias for sha256_hash).
     */
    public function setContentHashAttribute(string $value): void
    {
        $this->attributes['sha256_hash'] = $value;
    }

    /**
     * Accessor for stored_path (alias for storage_path).
     */
    public function getStoredPathAttribute(): string
    {
        return $this->storage_path;
    }

    /**
     * Mutator for stored_path (alias for storage_path).
     */
    public function setStoredPathAttribute(string $value): void
    {
        $this->attributes['storage_path'] = $value;
    }

    /**
     * Accessor for original_name (alias for original_filename).
     */
    public function getOriginalNameAttribute(): string
    {
        return $this->original_filename;
    }

    /**
     * Mutator for original_name (alias for original_filename).
     */
    public function setOriginalNameAttribute(string $value): void
    {
        $this->attributes['original_filename'] = $value;
    }

    /**
     * Status constants.
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_ERROR = 'error';

    public const STATUS_DELETED = 'deleted';

    /**
     * Get the tenant that owns the document.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the user who uploaded the document.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the TSA token for the upload timestamp.
     */
    public function uploadTsaToken(): BelongsTo
    {
        return $this->belongsTo(TsaToken::class, 'upload_tsa_token_id');
    }

    /**
     * Get the evidence packages associated with this document.
     */
    public function evidencePackages(): HasMany
    {
        return $this->hasMany(EvidencePackage::class, 'signable_id')
            ->where('signable_type', self::class);
    }

    /**
     * Get signatures associated with this document.
     * This will be implemented when the Signature model is created.
     */
    public function signatures(): HasMany
    {
        // TODO: Implement when Signature model exists
        return $this->hasMany(EvidencePackage::class, 'signable_id')
            ->where('signable_type', self::class)
            ->whereNotNull('id'); // Placeholder
    }

    /**
     * Get the verification code for this document.
     */
    public function verificationCode(): HasOne
    {
        // This will be linked when VerificationCode model is created
        return $this->hasOne(EvidencePackage::class, 'signable_id')
            ->where('signable_type', self::class); // Placeholder until VerificationCode exists
    }

    /**
     * Scope to only include ready documents.
     */
    public function scopeReady(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_READY);
    }

    /**
     * Scope to filter by user.
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get recent documents.
     */
    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'desc');
    }

    /**
     * Scope to filter by status.
     */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter documents with errors.
     */
    public function scopeWithErrors(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ERROR);
    }

    /**
     * Check if the document is ready for use.
     */
    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    /**
     * Check if the document is processing.
     */
    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    /**
     * Check if the document has an error.
     */
    public function hasError(): bool
    {
        return $this->status === self::STATUS_ERROR;
    }

    /**
     * Check if the document has a thumbnail.
     */
    public function hasThumbnail(): bool
    {
        return $this->thumbnail_path !== null && $this->thumbnail_generated_at !== null;
    }

    /**
     * Get a signed URL for downloading the document.
     */
    public function getDownloadUrl(int $expirationMinutes = 60): string
    {
        return URL::temporarySignedRoute(
            'documents.download',
            now()->addMinutes($expirationMinutes),
            ['document' => $this->uuid]
        );
    }

    /**
     * Get a signed URL for the thumbnail.
     */
    public function getThumbnailUrl(int $expirationMinutes = 60): ?string
    {
        if (! $this->hasThumbnail()) {
            return null;
        }

        return URL::temporarySignedRoute(
            'documents.thumbnail',
            now()->addMinutes($expirationMinutes),
            ['document' => $this->uuid]
        );
    }

    /**
     * Get the file size in human-readable format.
     */
    public function getFormattedFileSize(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    /**
     * Get the document's title from PDF metadata.
     */
    public function getPdfTitle(): ?string
    {
        return $this->pdf_metadata['title'] ?? null;
    }

    /**
     * Get the document's author from PDF metadata.
     */
    public function getPdfAuthor(): ?string
    {
        return $this->pdf_metadata['author'] ?? null;
    }

    /**
     * Get the document's creation date from PDF metadata.
     */
    public function getPdfCreationDate(): ?string
    {
        return $this->pdf_metadata['creation_date'] ?? null;
    }

    /**
     * Check if the document exists in storage.
     */
    public function existsInStorage(): bool
    {
        return Storage::disk($this->storage_disk)->exists($this->storage_path);
    }

    /**
     * Mark the document as processing.
     */
    public function markAsProcessing(): bool
    {
        return $this->update(['status' => self::STATUS_PROCESSING]);
    }

    /**
     * Mark the document as ready.
     */
    public function markAsReady(): bool
    {
        return $this->update([
            'status' => self::STATUS_READY,
            'error_message' => null,
        ]);
    }

    /**
     * Mark the document as having an error.
     */
    public function markAsError(string $message): bool
    {
        return $this->update([
            'status' => self::STATUS_ERROR,
            'error_message' => $message,
        ]);
    }

    /**
     * Get route key name for route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
