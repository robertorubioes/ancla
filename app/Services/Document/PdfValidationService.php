<?php

declare(strict_types=1);

namespace App\Services\Document;

use Illuminate\Http\UploadedFile;

/**
 * Service for validating PDF files.
 *
 * Performs comprehensive validation including:
 * - Magic bytes verification
 * - MIME type validation
 * - File size limits
 * - PDF structure analysis
 * - JavaScript detection
 * - Encryption detection
 */
class PdfValidationService
{
    /**
     * PDF magic bytes signature.
     */
    private const PDF_MAGIC = '%PDF-';

    /**
     * PDF end marker.
     */
    private const PDF_EOF = '%%EOF';

    /**
     * Validate an uploaded PDF file.
     */
    public function validate(UploadedFile $file): ValidationResult
    {
        $errors = [];
        $warnings = [];
        $metadata = [];

        // 1. File extension check
        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension !== 'pdf') {
            $errors[] = 'File must have .pdf extension';
        }

        // 2. File size check
        $maxSize = config('documents.max_size', 50 * 1024 * 1024);
        if ($file->getSize() > $maxSize) {
            $maxSizeMb = $maxSize / 1024 / 1024;
            $errors[] = "File exceeds maximum size of {$maxSizeMb}MB";
        }

        // 3. MIME type check (from actual file content)
        $mimeType = $file->getMimeType();
        $allowedMimes = config('documents.allowed_mimes', ['application/pdf']);
        if (! in_array($mimeType, $allowedMimes, true)) {
            $errors[] = "Invalid MIME type: {$mimeType}. Expected: application/pdf";
        }

        // 4. Magic bytes check
        $magicBytesResult = $this->validateMagicBytes($file);
        if (! $magicBytesResult['valid']) {
            $errors[] = $magicBytesResult['error'];
        } else {
            $metadata['pdf_version'] = $magicBytesResult['version'] ?? null;
        }

        // 5. PDF structure analysis (only if basic checks pass)
        if (empty($errors)) {
            $structureResult = $this->analyzePdfStructure($file);

            if (isset($structureResult['errors'])) {
                $errors = array_merge($errors, $structureResult['errors']);
            }

            if (isset($structureResult['warnings'])) {
                $warnings = array_merge($warnings, $structureResult['warnings']);
            }

            $metadata = array_merge($metadata, $structureResult['metadata'] ?? []);

            // Check page count limit
            $maxPages = config('documents.max_pages', 500);
            if (isset($metadata['page_count']) && $metadata['page_count'] > $maxPages) {
                $errors[] = "PDF exceeds maximum of {$maxPages} pages";
            }
        }

        return new ValidationResult(
            valid: empty($errors),
            errors: $errors,
            warnings: $warnings,
            metadata: $metadata
        );
    }

    /**
     * Validate PDF magic bytes.
     *
     * @return array{valid: bool, version?: string, error?: string}
     */
    public function validateMagicBytes(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');
        if ($handle === false) {
            return ['valid' => false, 'error' => 'Cannot read file'];
        }

        // Read first 8 bytes for header
        $header = fread($handle, 8);
        fclose($handle);

        if ($header === false || strlen($header) < 5) {
            return ['valid' => false, 'error' => 'File is too small or cannot be read'];
        }

        // Check for PDF magic bytes
        if (! str_starts_with($header, self::PDF_MAGIC)) {
            return ['valid' => false, 'error' => 'File does not have valid PDF header (magic bytes)'];
        }

        // Extract PDF version (e.g., "1.4" from "%PDF-1.4")
        $version = substr($header, 5, 3);

        return [
            'valid' => true,
            'version' => trim($version),
        ];
    }

    /**
     * Analyze PDF structure for security and metadata.
     *
     * @return array{errors?: array<string>, warnings?: array<string>, metadata?: array<string, mixed>}
     */
    public function analyzePdfStructure(UploadedFile $file): array
    {
        $errors = [];
        $warnings = [];
        $metadata = [];

        $filePath = $file->getRealPath();
        $content = file_get_contents($filePath);

        if ($content === false) {
            return ['errors' => ['Cannot read PDF content']];
        }

        // Check for proper PDF termination
        if (! str_contains($content, self::PDF_EOF)) {
            $warnings[] = 'PDF may be truncated (missing %%EOF marker)';
        }

        // Detect encryption
        $hasEncryption = $this->detectEncryption($content);
        $metadata['has_encryption'] = $hasEncryption;
        if ($hasEncryption && config('documents.security.reject_encrypted', true)) {
            $errors[] = 'Encrypted PDFs are not supported. Please remove encryption first.';
        }

        // Detect JavaScript
        $hasJavaScript = $this->detectJavaScript($content);
        $metadata['has_javascript'] = $hasJavaScript;
        if ($hasJavaScript) {
            if (config('documents.security.reject_javascript', false)) {
                $errors[] = 'PDF contains JavaScript which is not allowed';
            } else {
                $warnings[] = 'PDF contains JavaScript which may be disabled';
            }
        }

        // Detect dangerous patterns
        $dangerousPatterns = $this->detectDangerousPatterns($content);
        if (! empty($dangerousPatterns)) {
            $warnings[] = 'PDF contains potentially dangerous elements: '.implode(', ', $dangerousPatterns);
        }

        // Detect existing signatures
        $metadata['has_signatures'] = $this->detectSignatures($content);

        // Detect PDF/A compliance
        $metadata['is_pdf_a'] = $this->detectPdfA($content);

        // Estimate page count
        $metadata['page_count'] = $this->estimatePageCount($content);

        // Extract basic metadata using patterns
        $extractedMetadata = $this->extractBasicMetadata($content);
        $metadata = array_merge($metadata, $extractedMetadata);

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'metadata' => $metadata,
        ];
    }

    /**
     * Detect if PDF is encrypted.
     */
    public function detectEncryption(string $content): bool
    {
        // Check for /Encrypt dictionary
        return (bool) preg_match('/\/Encrypt\s/', $content);
    }

    /**
     * Detect if PDF contains JavaScript.
     */
    public function detectJavaScript(string $content): bool
    {
        $patterns = [
            '/\/JS\s/',
            '/\/JavaScript\s/',
            '/\/S\s*\/JavaScript/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect dangerous patterns in PDF content.
     *
     * @return array<string>
     */
    public function detectDangerousPatterns(string $content): array
    {
        $dangerous = [];
        $patterns = config('documents.validation.dangerous_patterns', []);

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                // Extract pattern name from regex
                $name = preg_replace('/[\/\\\s]/', '', $pattern);
                $dangerous[] = $name;
            }
        }

        return $dangerous;
    }

    /**
     * Detect if PDF has digital signatures.
     */
    public function detectSignatures(string $content): bool
    {
        return (bool) preg_match('/\/Type\s*\/Sig\b|\/SigFlags\s/i', $content);
    }

    /**
     * Detect if PDF is PDF/A compliant.
     */
    public function detectPdfA(string $content): bool
    {
        return str_contains($content, 'pdfaid:part') ||
               str_contains($content, 'PDF/A');
    }

    /**
     * Estimate page count from PDF content.
     */
    public function estimatePageCount(string $content): int
    {
        // Count /Type /Page occurrences (excluding /Type /Pages)
        preg_match_all('/\/Type\s*\/Page[^s]/i', $content, $matches);

        return count($matches[0]);
    }

    /**
     * Extract basic metadata from PDF content.
     *
     * @return array<string, mixed>
     */
    public function extractBasicMetadata(string $content): array
    {
        $metadata = [];

        // Try to extract Title
        if (preg_match('/\/Title\s*\(([^)]+)\)/i', $content, $matches)) {
            $metadata['title'] = $this->decodePdfString($matches[1]);
        }

        // Try to extract Author
        if (preg_match('/\/Author\s*\(([^)]+)\)/i', $content, $matches)) {
            $metadata['author'] = $this->decodePdfString($matches[1]);
        }

        // Try to extract Creator
        if (preg_match('/\/Creator\s*\(([^)]+)\)/i', $content, $matches)) {
            $metadata['creator'] = $this->decodePdfString($matches[1]);
        }

        // Try to extract Producer
        if (preg_match('/\/Producer\s*\(([^)]+)\)/i', $content, $matches)) {
            $metadata['producer'] = $this->decodePdfString($matches[1]);
        }

        // Try to extract CreationDate
        if (preg_match('/\/CreationDate\s*\(([^)]+)\)/i', $content, $matches)) {
            $metadata['creation_date'] = $this->decodePdfDate($matches[1]);
        }

        // Try to extract ModDate
        if (preg_match('/\/ModDate\s*\(([^)]+)\)/i', $content, $matches)) {
            $metadata['modification_date'] = $this->decodePdfDate($matches[1]);
        }

        return $metadata;
    }

    /**
     * Decode a PDF string (handle basic escapes).
     */
    private function decodePdfString(string $str): string
    {
        // Handle PDF escape sequences
        $str = str_replace(['\\n', '\\r', '\\t', '\\\\', '\\(', '\\)'], ["\n", "\r", "\t", '\\', '(', ')'], $str);

        // Try to detect and convert UTF-16BE (common in PDF)
        if (str_starts_with($str, "\xFE\xFF")) {
            $str = mb_convert_encoding(substr($str, 2), 'UTF-8', 'UTF-16BE');
        }

        return trim($str);
    }

    /**
     * Decode a PDF date string.
     *
     * PDF dates are in format: D:YYYYMMDDHHmmSSOHH'mm'
     */
    private function decodePdfDate(string $dateStr): ?string
    {
        // Remove D: prefix if present
        $dateStr = preg_replace('/^D:/', '', $dateStr);

        if ($dateStr === null || strlen($dateStr) < 4) {
            return null;
        }

        // Extract components
        $year = substr($dateStr, 0, 4);
        $month = strlen($dateStr) >= 6 ? substr($dateStr, 4, 2) : '01';
        $day = strlen($dateStr) >= 8 ? substr($dateStr, 6, 2) : '01';
        $hour = strlen($dateStr) >= 10 ? substr($dateStr, 8, 2) : '00';
        $minute = strlen($dateStr) >= 12 ? substr($dateStr, 10, 2) : '00';
        $second = strlen($dateStr) >= 14 ? substr($dateStr, 12, 2) : '00';

        return "{$year}-{$month}-{$day} {$hour}:{$minute}:{$second}";
    }

    /**
     * Sanitize filename to prevent path traversal attacks.
     */
    public function sanitizeFilename(string $filename): string
    {
        // Remove path components
        $filename = basename($filename);

        // Remove null bytes
        $filename = str_replace("\0", '', $filename);

        // Remove directory separators
        $filename = str_replace(['/', '\\', '..'], '', $filename);

        // Remove non-printable characters
        $filename = preg_replace('/[\x00-\x1F\x7F]/', '', $filename);

        // Limit length
        if (strlen($filename) > 255) {
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $name = pathinfo($filename, PATHINFO_FILENAME);
            $maxNameLength = 255 - strlen($ext) - 1;
            $filename = substr($name, 0, $maxNameLength).'.'.$ext;
        }

        // Ensure it's not empty
        if (empty($filename) || $filename === '.pdf') {
            $filename = 'document.pdf';
        }

        return $filename;
    }
}
