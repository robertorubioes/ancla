<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Models\SigningProcess;

/**
 * Result object for final document generation.
 */
class FinalDocumentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $storagePath,
        public readonly string $contentHash,
        public readonly int $fileSize,
        public readonly int $pageCount,
        public readonly SigningProcess $signingProcess,
        public readonly ?string $errorMessage = null
    ) {}

    /**
     * Create a failed result.
     */
    public static function failed(string $errorMessage): self
    {
        return new self(
            success: false,
            storagePath: '',
            contentHash: '',
            fileSize: 0,
            pageCount: 0,
            signingProcess: new SigningProcess,
            errorMessage: $errorMessage
        );
    }

    /**
     * Check if generation was successful.
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Check if generation failed.
     */
    public function isFailed(): bool
    {
        return ! $this->success;
    }

    /**
     * Get the full storage path.
     */
    public function getFullPath(): string
    {
        return storage_path('app/'.$this->storagePath);
    }

    /**
     * Get human-readable file size.
     */
    public function getFormattedSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->fileSize;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 2).' '.$units[$unitIndex];
    }
}
