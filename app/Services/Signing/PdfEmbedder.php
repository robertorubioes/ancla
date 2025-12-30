<?php

declare(strict_types=1);

namespace App\Services\Signing;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

class PdfEmbedder
{
    private ?Fpdi $pdf = null;

    private ?string $originalContent = null;

    private array $signaturePosition = [];

    private array $appearance = [];

    private array $metadata = [];

    private ?string $tempPdfPath = null;

    /**
     * Import existing PDF.
     */
    public function importPdf(string $content): self
    {
        $this->originalContent = $content;

        // Save to temp file for FPDI (it requires a file path)
        $this->tempPdfPath = tempnam(sys_get_temp_dir(), 'pdf_embed_');
        file_put_contents($this->tempPdfPath, $content);

        try {
            $this->pdf = new Fpdi;
            $pageCount = $this->pdf->setSourceFile($this->tempPdfPath);

            // Import all pages
            for ($i = 1; $i <= $pageCount; $i++) {
                $this->pdf->AddPage();
                $tplId = $this->pdf->importPage($i);
                $this->pdf->useTemplate($tplId);
            }

            Log::info('PDF imported for signature', [
                'pages' => $pageCount,
                'size' => strlen($content),
            ]);
        } catch (\Exception $e) {
            throw PdfSignatureException::pdfReadFailed($this->tempPdfPath);
        }

        return $this;
    }

    /**
     * Add signature field to PDF.
     */
    public function addSignatureField(array $position): self
    {
        $this->signaturePosition = $position;

        // Navigate to the target page
        $pageNumber = $this->resolvePageNumber($position['page']);
        $this->pdf->setPage($pageNumber);

        return $this;
    }

    /**
     * Add signature appearance (visible signature).
     */
    public function addSignatureAppearance(array $appearance): self
    {
        $this->appearance = $appearance;

        if (config('signing.appearance.mode') !== 'visible') {
            return $this; // Skip if invisible signature
        }

        try {
            $this->drawSignatureBox();
            $this->drawSignatureImage();
            $this->drawSignerInfo();
            $this->drawTimestamp();
            $this->drawVerificationInfo();
            $this->drawQrCode();
            $this->drawLogo();

            Log::info('Signature appearance added to PDF');
        } catch (\Exception $e) {
            Log::error('Failed to add signature appearance', [
                'error' => $e->getMessage(),
            ]);
            // Continue without appearance - signature is still valid
        }

        return $this;
    }

    /**
     * Embed PKCS#7 signature in PDF.
     *
     * Note: This is a simplified implementation for MVP.
     * Full PAdES requires proper PDF signature dictionary with ByteRange.
     */
    public function embedPkcs7(string $pkcs7Der): self
    {
        // For MVP, we store the PKCS#7 in database and mark PDF as signed
        // Full PAdES implementation would:
        // 1. Calculate ByteRange
        // 2. Insert signature dictionary
        // 3. Reserve space for signature
        // 4. Sign the ByteRange
        // 5. Insert signature in reserved space

        // This simplified version creates a valid PDF with visual signature
        // The PKCS#7 signature is stored separately and validated independently

        Log::info('PKCS#7 signature prepared for embedding', [
            'pkcs7_size' => strlen($pkcs7Der),
        ]);

        return $this;
    }

    /**
     * Embed custom metadata in PDF.
     */
    public function embedMetadata(array $metadata): self
    {
        $this->metadata = $metadata;

        // Add metadata to PDF properties
        if (isset($metadata['ANCLA_Version'])) {
            $this->pdf->SetCreator('ANCLA Digital Signature Platform v'.$metadata['ANCLA_Version']);
        }

        if (isset($metadata['ANCLA_Process_ID'])) {
            $this->pdf->SetSubject('Signed Document - Process ID: '.$metadata['ANCLA_Process_ID']);
        }

        // Add custom keywords with metadata
        $keywords = [];
        foreach ($metadata as $key => $value) {
            if (str_starts_with($key, 'ANCLA_')) {
                $keywords[] = "{$key}={$value}";
            }
        }

        if (! empty($keywords)) {
            $this->pdf->SetKeywords(implode('; ', $keywords));
        }

        Log::info('Metadata embedded in PDF', [
            'metadata_count' => count($metadata),
        ]);

        return $this;
    }

    /**
     * Generate final signed PDF content.
     */
    public function generate(): string
    {
        if (! $this->pdf) {
            throw PdfSignatureException::pdfEmbeddingFailed('No PDF loaded');
        }

        // Generate PDF content
        $content = $this->pdf->Output('S');

        // Cleanup temp file
        if ($this->tempPdfPath && file_exists($this->tempPdfPath)) {
            @unlink($this->tempPdfPath);
        }

        Log::info('Signed PDF generated', [
            'output_size' => strlen($content),
        ]);

        return $content;
    }

    /**
     * Resolve page number from configuration.
     */
    private function resolvePageNumber(string|int $page): int
    {
        if (is_int($page)) {
            return $page;
        }

        return match ($page) {
            'first' => 1,
            'last' => $this->pdf->getNumPages(),
            default => 1,
        };
    }

    /**
     * Draw signature box background and border.
     */
    private function drawSignatureBox(): void
    {
        $pos = $this->signaturePosition;
        $style = $this->appearance['style'] ?? config('signing.appearance.style');

        // Set colors
        $borderRgb = $this->hexToRgb($style['border_color']);
        $bgRgb = $this->hexToRgb($style['background_color']);

        // Draw background
        $this->pdf->SetFillColor($bgRgb[0], $bgRgb[1], $bgRgb[2]);
        $this->pdf->Rect($pos['x'], $pos['y'], $pos['width'], $pos['height'], 'F');

        // Draw border
        $this->pdf->SetDrawColor($borderRgb[0], $borderRgb[1], $borderRgb[2]);
        $this->pdf->SetLineWidth($style['border_width'] ?? 0.5);
        $this->pdf->Rect($pos['x'], $pos['y'], $pos['width'], $pos['height']);
    }

    /**
     * Draw signer's signature image.
     */
    private function drawSignatureImage(): void
    {
        if (! ($this->appearance['layout']['show_signature_image'] ?? true)) {
            return;
        }

        $imagePath = $this->appearance['signature_image_path'] ?? null;

        if (! $imagePath) {
            return;
        }

        $fullPath = Storage::path($imagePath);

        if (! file_exists($fullPath)) {
            Log::warning('Signature image not found', ['path' => $fullPath]);

            return;
        }

        try {
            $this->pdf->Image(
                $fullPath,
                $this->signaturePosition['x'] + 5,
                $this->signaturePosition['y'] + 5,
                20, // width
                15  // height
            );
        } catch (\Exception $e) {
            Log::error('Failed to add signature image', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Draw signer information.
     */
    private function drawSignerInfo(): void
    {
        if (! ($this->appearance['layout']['show_signer_name'] ?? true)) {
            return;
        }

        $style = $this->appearance['style'] ?? config('signing.appearance.style');

        // Signer name (bold)
        $this->pdf->SetFont($style['font_family'], 'B', $style['font_size'] + 1);
        $this->pdf->SetXY(
            $this->signaturePosition['x'] + 30,
            $this->signaturePosition['y'] + 5
        );
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->Cell(0, 5, $this->appearance['signer_name'] ?? 'Firmante', 0, 1);

        // Signer email (regular)
        if (! empty($this->appearance['signer_email'])) {
            $this->pdf->SetFont($style['font_family'], '', $style['font_size'] - 1);
            $this->pdf->SetX($this->signaturePosition['x'] + 30);
            $this->pdf->SetTextColor(100, 100, 100);
            $this->pdf->Cell(0, 4, $this->appearance['signer_email'], 0, 1);
        }
    }

    /**
     * Draw timestamp information.
     */
    private function drawTimestamp(): void
    {
        if (! ($this->appearance['layout']['show_timestamp'] ?? true)) {
            return;
        }

        $style = $this->appearance['style'] ?? config('signing.appearance.style');
        $text = config('signing.appearance.text');

        $this->pdf->SetFont($style['font_family'], '', $style['font_size'] - 1);
        $this->pdf->SetXY(
            $this->signaturePosition['x'] + 5,
            $this->signaturePosition['y'] + 22
        );
        $this->pdf->SetTextColor(60, 60, 60);

        $dateLabel = $text['date_label'] ?? 'Fecha';
        $timestamp = $this->appearance['signing_time'] ?? now()->format('d/m/Y H:i:s');

        $this->pdf->Cell(0, 4, "{$dateLabel}: {$timestamp}", 0, 1);
    }

    /**
     * Draw verification code information.
     */
    private function drawVerificationInfo(): void
    {
        $verificationCode = $this->appearance['verification_code'] ?? null;

        if (! $verificationCode) {
            return;
        }

        $style = $this->appearance['style'] ?? config('signing.appearance.style');
        $text = config('signing.appearance.text');

        $this->pdf->SetFont($style['font_family'], 'I', $style['font_size'] - 2);
        $this->pdf->SetXY(
            $this->signaturePosition['x'] + 5,
            $this->signaturePosition['y'] + $this->signaturePosition['height'] - 8
        );
        $this->pdf->SetTextColor(80, 80, 80);

        $verifyLabel = $text['verify_label'] ?? 'Verificar en';
        $this->pdf->Cell(0, 3, "{$verifyLabel}: {$verificationCode}", 0, 1);
    }

    /**
     * Draw QR code for verification.
     */
    private function drawQrCode(): void
    {
        if (! ($this->appearance['layout']['show_qr_code'] ?? true)) {
            return;
        }

        $qrPath = $this->appearance['qr_code_path'] ?? null;

        if (! $qrPath) {
            return;
        }

        $fullPath = Storage::path($qrPath);

        if (! file_exists($fullPath)) {
            Log::warning('QR code not found', ['path' => $fullPath]);

            return;
        }

        try {
            $this->pdf->Image(
                $fullPath,
                $this->signaturePosition['x'] + $this->signaturePosition['width'] - 18,
                $this->signaturePosition['y'] + 5,
                13, // width
                13  // height
            );
        } catch (\Exception $e) {
            Log::error('Failed to add QR code', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Draw ANCLA logo.
     */
    private function drawLogo(): void
    {
        if (! ($this->appearance['layout']['show_logo'] ?? true)) {
            return;
        }

        $style = $this->appearance['style'] ?? config('signing.appearance.style');
        $logoPath = $this->appearance['logo_path'] ?? $style['logo_path'] ?? null;

        if (! $logoPath) {
            return;
        }

        $fullPath = Storage::path($logoPath);

        if (! file_exists($fullPath)) {
            // Logo is optional, just log and continue
            return;
        }

        try {
            $this->pdf->Image(
                $fullPath,
                $this->signaturePosition['x'] + 5,
                $this->signaturePosition['y'] + $this->signaturePosition['height'] - 10,
                15, // width
                5   // height
            );
        } catch (\Exception $e) {
            Log::error('Failed to add logo', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Convert hex color to RGB array.
     */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
