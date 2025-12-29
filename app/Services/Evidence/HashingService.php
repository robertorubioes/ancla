<?php

namespace App\Services\Evidence;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Service for document hashing and integrity verification.
 *
 * Uses SHA-256 as per eIDAS requirements for document integrity.
 * Supports both file and string hashing with chunked processing
 * for large files.
 *
 * @see ADR-005 in docs/architecture/decisions.md
 */
class HashingService
{
    /**
     * The hashing algorithm to use (SHA-256 for eIDAS compliance).
     */
    private const ALGORITHM = 'sha256';

    /**
     * Chunk size for processing large files (8KB).
     */
    private const CHUNK_SIZE = 8192;

    /**
     * Hash a file from a given path.
     *
     * @param  string  $path  Path to the file (relative to storage disk)
     * @param  string  $disk  Storage disk to use
     * @return string SHA-256 hash of the file
     *
     * @throws RuntimeException If file cannot be read
     */
    public function hashDocument(string $path, string $disk = 'local'): string
    {
        $fullPath = Storage::disk($disk)->path($path);

        if (! file_exists($fullPath)) {
            throw new RuntimeException("File not found: {$path}");
        }

        $hash = hash_file(self::ALGORITHM, $fullPath);

        if ($hash === false) {
            throw new RuntimeException("Failed to hash file: {$path}");
        }

        return $hash;
    }

    /**
     * Hash a string content.
     *
     * @param  string  $content  Content to hash
     * @return string SHA-256 hash of the content
     */
    public function hashString(string $content): string
    {
        return hash(self::ALGORITHM, $content);
    }

    /**
     * Alias for hashString - hash content (string or binary).
     *
     * @param  string  $content  Content to hash
     * @return string SHA-256 hash of the content
     */
    public function hashContent(string $content): string
    {
        return $this->hashString($content);
    }

    /**
     * Hash an uploaded file using chunked reading for memory efficiency.
     *
     * @param  UploadedFile  $file  The uploaded file
     * @return string SHA-256 hash of the file
     *
     * @throws RuntimeException If file cannot be opened
     */
    public function hashUploadedFile(UploadedFile $file): string
    {
        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            throw new RuntimeException('Cannot open uploaded file for hashing');
        }

        $context = hash_init(self::ALGORITHM);

        while (! feof($handle)) {
            $chunk = fread($handle, self::CHUNK_SIZE);
            if ($chunk !== false) {
                hash_update($context, $chunk);
            }
        }

        fclose($handle);

        return hash_final($context);
    }

    /**
     * Verify that a document's hash matches the expected hash.
     *
     * @param  string  $path  Path to the document
     * @param  string  $expectedHash  Expected SHA-256 hash
     * @param  string  $disk  Storage disk
     * @return bool True if hashes match
     */
    public function verifyDocumentHash(string $path, string $expectedHash, string $disk = 'local'): bool
    {
        try {
            $actualHash = $this->hashDocument($path, $disk);

            return hash_equals($expectedHash, $actualHash);
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Hash structured data (arrays/objects) in a deterministic way.
     *
     * Sorts keys recursively to ensure consistent hashing regardless
     * of the order data was created.
     *
     * @param  array<string, mixed>  $data  Data to hash
     * @return string SHA-256 hash
     */
    public function hashData(array $data): string
    {
        // Recursively sort the data for deterministic output
        $normalizedData = $this->normalizeData($data);

        // Create deterministic JSON
        $json = json_encode(
            $normalizedData,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        return $this->hashString($json);
    }

    /**
     * Alias for hashData - hash audit data deterministically.
     *
     * @param  array<string, mixed>  $data  Audit data to hash
     * @return string SHA-256 hash
     */
    public function hashAuditData(array $data): string
    {
        return $this->hashData($data);
    }

    /**
     * Recursively normalize data by sorting keys.
     *
     * @param  mixed  $data  Data to normalize
     * @return mixed Normalized data
     */
    private function normalizeData(mixed $data): mixed
    {
        if (is_array($data)) {
            // Check if it's an associative array
            if ($this->isAssociativeArray($data)) {
                ksort($data);
            }

            // Recursively normalize nested arrays
            return array_map(fn ($value) => $this->normalizeData($value), $data);
        }

        if (is_object($data)) {
            $array = (array) $data;
            ksort($array);

            return array_map(fn ($value) => $this->normalizeData($value), $array);
        }

        return $data;
    }

    /**
     * Check if an array is associative (has string keys).
     *
     * @param  array<mixed>  $array  Array to check
     * @return bool True if associative
     */
    private function isAssociativeArray(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Get the algorithm name used for hashing.
     *
     * @return string Algorithm name (SHA-256)
     */
    public function getAlgorithm(): string
    {
        return strtoupper(self::ALGORITHM);
    }

    /**
     * Get the expected hash length.
     *
     * @return int Hash length in characters (64 for SHA-256)
     */
    public function getHashLength(): int
    {
        return 64; // SHA-256 produces 64 hex characters
    }

    /**
     * Validate that a hash string is properly formatted.
     *
     * @param  string  $hash  Hash string to validate
     * @return bool True if valid SHA-256 hash format
     */
    public function isValidHash(string $hash): bool
    {
        return preg_match('/^[a-f0-9]{64}$/i', $hash) === 1;
    }
}
