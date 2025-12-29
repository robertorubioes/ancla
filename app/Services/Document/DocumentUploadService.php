<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Models\Document;
use App\Models\User;
use App\Services\Evidence\AuditTrailService;
use App\Services\Evidence\HashingService;
use App\Services\Evidence\TsaService;
use App\Services\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Service for uploading and processing PDF documents.
 *
 * Handles the complete document upload workflow including:
 * - PDF validation
 * - Hash calculation
 * - Encryption and storage
 * - TSA timestamp acquisition
 * - Thumbnail generation
 * - Audit trail logging
 */
class DocumentUploadService
{
    public function __construct(
        private readonly PdfValidationService $validator,
        private readonly HashingService $hashingService,
        private readonly TsaService $tsaService,
        private readonly AuditTrailService $auditService,
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * Upload and process a PDF document.
     *
     * @throws DocumentUploadException If validation fails
     * @throws DuplicateDocumentException If document already exists
     */
    public function upload(UploadedFile $file, User $user): Document
    {
        // 1. Validate the PDF
        $validation = $this->validatePdf($file);

        if (! $validation->isValid()) {
            throw new DocumentUploadException(
                'PDF validation failed',
                $validation->errors
            );
        }

        // 2. Calculate hash before any processing
        $contentHash = $this->hashingService->hashUploadedFile($file);

        // 3. Get tenant context
        $tenant = $this->tenantContext->get();
        if (! $tenant) {
            throw new DocumentUploadException('No tenant context available');
        }

        // 4. Check for duplicate
        $existingDocument = Document::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('sha256_hash', $contentHash)
            ->whereNull('deleted_at')
            ->first();

        if ($existingDocument) {
            throw new DuplicateDocumentException(
                'A document with identical content already exists in this organization',
                $existingDocument->uuid
            );
        }

        // 5. Sanitize filename
        $originalFilename = $this->validator->sanitizeFilename(
            $file->getClientOriginalName()
        );

        return DB::transaction(function () use ($file, $user, $tenant, $contentHash, $originalFilename, $validation) {
            // 6. Generate UUID for document
            $uuid = (string) Str::uuid();

            // 7. Create document record with pending status and placeholder storage path
            $document = Document::create([
                'uuid' => $uuid,
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'original_filename' => $originalFilename,
                'original_extension' => 'pdf',
                'mime_type' => $file->getMimeType() ?? 'application/pdf',
                'file_size' => $file->getSize(),
                'sha256_hash' => $contentHash,
                'storage_disk' => config('documents.storage_disk', 'local'),
                'storage_path' => 'pending/'.$uuid.'.pdf',
                'stored_filename' => $uuid.'.pdf',
                'status' => Document::STATUS_PENDING,
            ]);

            try {
                // 7. Mark as processing
                $document->markAsProcessing();

                // 8. Store file (with encryption if enabled)
                $storageResult = $this->encryptAndStore($file, $document);

                // 9. Update storage info
                $document->update([
                    'storage_disk' => $storageResult['disk'],
                    'storage_path' => $storageResult['path'],
                    'stored_filename' => $storageResult['filename'],
                    'is_encrypted' => $storageResult['encrypted'],
                    'encryption_key_id' => $storageResult['key_id'] ?? null,
                ]);

                // 10. Extract and store metadata
                $metadata = $validation->metadata;
                $document->update([
                    'page_count' => $metadata['page_count'] ?? null,
                    'pdf_metadata' => $metadata,
                    'pdf_version' => $metadata['pdf_version'] ?? null,
                    'is_pdf_a' => $metadata['is_pdf_a'] ?? false,
                    'has_signatures' => $metadata['has_signatures'] ?? false,
                    'has_encryption' => $metadata['has_encryption'] ?? false,
                    'has_javascript' => $metadata['has_javascript'] ?? false,
                ]);

                // 11. Generate thumbnail
                $thumbnailPath = $this->generateThumbnail($document);
                if ($thumbnailPath) {
                    $document->update([
                        'thumbnail_path' => $thumbnailPath,
                        'thumbnail_generated_at' => now(),
                    ]);
                }

                // 12. Get TSA timestamp
                $tsaToken = $this->tsaService->requestTimestamp($contentHash);
                $document->update(['upload_tsa_token_id' => $tsaToken->id]);

                // 13. Mark as ready
                $document->markAsReady();

                // 14. Log to audit trail
                $this->auditService->record(
                    $document,
                    'document.uploaded',
                    [
                        'original_filename' => $originalFilename,
                        'file_size' => $document->file_size,
                        'page_count' => $document->page_count,
                        'sha256_hash' => $contentHash,
                        'has_javascript' => $document->has_javascript,
                        'has_signatures' => $document->has_signatures,
                        'is_pdf_a' => $document->is_pdf_a,
                    ]
                );

                return $document->fresh();

            } catch (\Exception $e) {
                // Mark as error if something fails
                $document->markAsError($e->getMessage());

                Log::error('Document upload failed', [
                    'document_id' => $document->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw $e;
            }
        });
    }

    /**
     * Validate a PDF file.
     */
    public function validatePdf(UploadedFile $file): ValidationResult
    {
        return $this->validator->validate($file);
    }

    /**
     * Extract metadata from a PDF file.
     *
     * @return array<string, mixed>
     */
    public function extractMetadata(string $path): array
    {
        $content = Storage::disk(config('documents.storage_disk', 'local'))->get($path);

        if ($content === null) {
            return [];
        }

        $metadata = [];

        // Detect encryption
        $metadata['has_encryption'] = $this->validator->detectEncryption($content);

        // Detect JavaScript
        $metadata['has_javascript'] = $this->validator->detectJavaScript($content);

        // Detect signatures
        $metadata['has_signatures'] = $this->validator->detectSignatures($content);

        // Detect PDF/A
        $metadata['is_pdf_a'] = $this->validator->detectPdfA($content);

        // Estimate page count
        $metadata['page_count'] = $this->validator->estimatePageCount($content);

        // Extract basic metadata
        $basicMetadata = $this->validator->extractBasicMetadata($content);
        $metadata = array_merge($metadata, $basicMetadata);

        return $metadata;
    }

    /**
     * Generate a thumbnail for the document.
     */
    public function generateThumbnail(Document $document): ?string
    {
        if (! config('documents.thumbnail.enabled', true)) {
            return null;
        }

        try {
            // Get the decrypted content
            $content = $this->getDecryptedContent($document);

            // Create temp file for processing
            $tempPdf = tempnam(sys_get_temp_dir(), 'pdf_thumb_');
            file_put_contents($tempPdf, $content);

            // Check if Imagick is available
            if (! extension_loaded('imagick')) {
                Log::info('Imagick extension not available, skipping thumbnail generation', [
                    'document_id' => $document->id,
                ]);
                unlink($tempPdf);

                return null;
            }

            // Generate thumbnail path
            $thumbnailPath = sprintf(
                '%s/%s/%s/%s.%s',
                config('documents.thumbnail.prefix', 'thumbnails'),
                $document->tenant_id,
                now()->format('Y/m'),
                $document->uuid,
                config('documents.thumbnail.format', 'png')
            );

            // Create thumbnail using Imagick
            $imagick = new \Imagick;
            $imagick->setResolution(
                config('documents.thumbnail.dpi', 150),
                config('documents.thumbnail.dpi', 150)
            );
            $imagick->readImage($tempPdf.'[0]'); // First page
            $imagick->setImageFormat(config('documents.thumbnail.format', 'png'));
            $imagick->thumbnailImage(
                config('documents.thumbnail.width', 200),
                0 // Maintain aspect ratio
            );

            // Store thumbnail
            Storage::disk(config('documents.storage_disk', 'local'))->put(
                $thumbnailPath,
                $imagick->getImageBlob()
            );

            // Cleanup
            $imagick->clear();
            $imagick->destroy();
            unlink($tempPdf);

            return $thumbnailPath;

        } catch (\Exception $e) {
            Log::warning('Thumbnail generation failed', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Encrypt and store a file.
     *
     * @return array{disk: string, path: string, filename: string, encrypted: bool, key_id?: string}
     */
    public function encryptAndStore(UploadedFile $file, Document $document): array
    {
        $disk = config('documents.storage_disk', 'local');
        $encrypt = config('documents.encryption.enabled', true);

        // Generate storage path
        $storedFilename = sprintf(
            '%s_%s.pdf%s',
            $document->uuid,
            Str::random(8),
            $encrypt ? '.enc' : ''
        );

        $path = sprintf(
            '%s/%s/%s/%s',
            config('documents.storage_prefix', 'documents'),
            $document->tenant_id,
            now()->format('Y/m'),
            $storedFilename
        );

        // Read file content
        $content = file_get_contents($file->getRealPath());

        if ($content === false) {
            throw new DocumentUploadException('Cannot read uploaded file');
        }

        // Encrypt if enabled
        if ($encrypt) {
            $content = encrypt($content);
        }

        // Store file
        Storage::disk($disk)->put($path, $content);

        return [
            'disk' => $disk,
            'path' => $path,
            'filename' => $storedFilename,
            'encrypted' => $encrypt,
            'key_id' => $encrypt ? config('app.key') : null,
        ];
    }

    /**
     * Get decrypted content of a document.
     */
    public function getDecryptedContent(Document $document): string
    {
        $content = Storage::disk($document->storage_disk)->get($document->storage_path);

        if ($content === null) {
            throw new DocumentUploadException('Document file not found in storage');
        }

        if ($document->is_encrypted) {
            $content = decrypt($content);
        }

        return $content;
    }

    /**
     * Verify document integrity by comparing stored hash.
     */
    public function verifyIntegrity(Document $document): bool
    {
        try {
            $content = $this->getDecryptedContent($document);
            $currentHash = $this->hashingService->hashString($content);

            $isValid = hash_equals($document->sha256_hash, $currentHash);

            // Update verification timestamp
            $document->update(['hash_verified_at' => now()]);

            if ($isValid) {
                $this->auditService->record(
                    $document,
                    'document.integrity_verified',
                    ['result' => 'valid']
                );
            } else {
                $this->auditService->record(
                    $document,
                    'document.integrity_failed',
                    [
                        'expected_hash' => $document->sha256_hash,
                        'actual_hash' => $currentHash,
                    ]
                );
            }

            return $isValid;

        } catch (\Exception $e) {
            Log::error('Document integrity verification failed', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Delete a document (soft delete).
     */
    public function delete(Document $document): bool
    {
        $this->auditService->record(
            $document,
            'document.deleted',
            [
                'original_filename' => $document->original_filename,
                'sha256_hash' => $document->sha256_hash,
            ]
        );

        return $document->delete();
    }

    /**
     * Permanently delete a document and its files.
     */
    public function forceDelete(Document $document): bool
    {
        // Delete the stored file
        if ($document->storage_path) {
            Storage::disk($document->storage_disk)->delete($document->storage_path);
        }

        // Delete thumbnail if exists
        if ($document->thumbnail_path) {
            Storage::disk($document->storage_disk)->delete($document->thumbnail_path);
        }

        $this->auditService->record(
            $document,
            'document.permanently_deleted',
            [
                'original_filename' => $document->original_filename,
                'sha256_hash' => $document->sha256_hash,
            ]
        );

        return $document->forceDelete();
    }
}
