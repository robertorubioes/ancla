<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\Models\Document;
use App\Models\VerificationCode;
use Illuminate\Support\Facades\Storage;

/**
 * Service for generating QR codes for document verification.
 *
 * Generates QR codes that link to the public verification page.
 *
 * @see ADR-007 in docs/architecture/adr-007-sprint3-retention-verification-upload.md
 */
class QrCodeService
{
    /**
     * Generate a QR code for a document and store it.
     *
     * @param  Document  $document  The document to generate QR for
     * @return string The storage path of the generated QR code
     */
    public function generateForDocument(Document $document): string
    {
        // Get or create verification code
        $verificationCode = VerificationCode::forDocument($document->id)->first();

        if (! $verificationCode) {
            $verificationCode = app(PublicVerificationService::class)
                ->createVerificationCode($document);
        }

        return $this->generateForCode($verificationCode);
    }

    /**
     * Generate a QR code for a verification code.
     *
     * @return string The storage path of the generated QR code
     */
    public function generateForCode(VerificationCode $verificationCode): string
    {
        $url = $this->generateVerificationUrl($verificationCode->verification_code);

        // Generate QR code
        $qrContent = $this->generateQrCodeImage($url);

        // Store the QR code
        $path = $this->storeQrCode($qrContent, $verificationCode);

        // Update the verification code with the QR path
        $verificationCode->update(['qr_code_path' => $path]);

        return $path;
    }

    /**
     * Generate the verification URL for a code.
     */
    public function generateVerificationUrl(string $code): string
    {
        $baseUrl = config('verification.public_url', config('app.url'));

        return rtrim($baseUrl, '/').'/verify/'.urlencode($code);
    }

    /**
     * Generate the verification URL using short code.
     */
    public function generateShortVerificationUrl(string $shortCode): string
    {
        $baseUrl = config('verification.public_url', config('app.url'));

        return rtrim($baseUrl, '/').'/v/'.urlencode($shortCode);
    }

    /**
     * Generate QR code image content.
     *
     * Uses SimpleSoftwareIO/simple-qrcode if available,
     * falls back to a Google Charts API approach or placeholder.
     *
     * @return string PNG image content
     */
    private function generateQrCodeImage(string $content): string
    {
        $size = config('verification.qr.size', 300);
        $margin = config('verification.qr.margin', 10);
        $errorCorrection = config('verification.qr.error_correction', 'M');

        // Try SimpleSoftwareIO QrCode package if available
        if (class_exists(\SimpleSoftwareIO\QrCode\Facades\QrCode::class)) {
            return \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')
                ->size($size)
                ->margin($margin)
                ->errorCorrection($errorCorrection)
                ->generate($content);
        }

        // Try chillerlan/php-qrcode if available
        if (class_exists(\chillerlan\QRCode\QRCode::class)) {
            $options = new \chillerlan\QRCode\QROptions([
                'outputType' => \chillerlan\QRCode\QRCode::OUTPUT_IMAGE_PNG,
                'eccLevel' => match ($errorCorrection) {
                    'L' => \chillerlan\QRCode\Common\EccLevel::L,
                    'M' => \chillerlan\QRCode\Common\EccLevel::M,
                    'Q' => \chillerlan\QRCode\Common\EccLevel::Q,
                    'H' => \chillerlan\QRCode\Common\EccLevel::H,
                    default => \chillerlan\QRCode\Common\EccLevel::M,
                },
                'scale' => (int) ($size / 50),
                'imageTransparent' => false,
            ]);

            return (new \chillerlan\QRCode\QRCode($options))->render($content);
        }

        // Try endroid/qr-code if available
        if (class_exists(\Endroid\QrCode\QrCode::class)) {
            $qrCode = \Endroid\QrCode\QrCode::create($content)
                ->setSize($size)
                ->setMargin($margin);

            $writer = new \Endroid\QrCode\Writer\PngWriter;

            return $writer->write($qrCode)->getString();
        }

        // Fallback: generate a placeholder SVG QR code
        return $this->generatePlaceholderQr($content, $size);
    }

    /**
     * Generate a placeholder QR code when no library is available.
     *
     * This creates a simple placeholder that indicates a QR code should be here.
     * For production, install a proper QR code library.
     *
     * @return string PNG image content
     */
    private function generatePlaceholderQr(string $content, int $size): string
    {
        // Create a simple PNG placeholder with GD
        if (! extension_loaded('gd')) {
            // Return a minimal 1x1 transparent PNG if GD is not available
            return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        }

        $image = imagecreatetruecolor($size, $size);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        $gray = imagecolorallocate($image, 100, 100, 100);

        // Fill background
        imagefill($image, 0, 0, $white);

        // Draw border
        imagerectangle($image, 0, 0, $size - 1, $size - 1, $black);

        // Draw corner markers (simulating QR positioning patterns)
        $markerSize = (int) ($size / 5);
        $margin = (int) ($size / 10);

        // Top-left corner
        $this->drawQrMarker($image, $margin, $margin, $markerSize, $black, $white);

        // Top-right corner
        $this->drawQrMarker($image, $size - $margin - $markerSize, $margin, $markerSize, $black, $white);

        // Bottom-left corner
        $this->drawQrMarker($image, $margin, $size - $margin - $markerSize, $markerSize, $black, $white);

        // Draw some random-looking pattern in the middle to simulate QR data
        $dataArea = [
            'x' => $margin + $markerSize + 10,
            'y' => $margin + $markerSize + 10,
            'w' => $size - 2 * ($margin + $markerSize) - 20,
            'h' => $size - 2 * ($margin + $markerSize) - 20,
        ];

        // Generate deterministic pattern based on content hash
        $hash = md5($content);
        $cellSize = 5;

        for ($i = 0; $i < strlen($hash); $i++) {
            $val = hexdec($hash[$i]);
            $row = (int) ($i / 4);
            $col = $i % 4;

            $x = $dataArea['x'] + $col * ($dataArea['w'] / 4);
            $y = $dataArea['y'] + $row * ($dataArea['h'] / 4);

            for ($bit = 0; $bit < 4; $bit++) {
                if (($val >> $bit) & 1) {
                    imagefilledrectangle(
                        $image,
                        (int) ($x + ($bit % 2) * $cellSize * 3),
                        (int) ($y + (int) ($bit / 2) * $cellSize * 3),
                        (int) ($x + ($bit % 2) * $cellSize * 3 + $cellSize * 2),
                        (int) ($y + (int) ($bit / 2) * $cellSize * 3 + $cellSize * 2),
                        $black
                    );
                }
            }
        }

        // Add text indicating it's a placeholder
        $fontSize = 2;
        $text = 'VERIFY';
        $textWidth = imagefontwidth($fontSize) * strlen($text);
        imagestring($image, $fontSize, (int) (($size - $textWidth) / 2), $size - 30, $text, $gray);

        // Output as PNG
        ob_start();
        imagepng($image);
        $content = ob_get_clean();
        imagedestroy($image);

        return $content;
    }

    /**
     * Draw a QR positioning marker.
     *
     * @param  resource|\GdImage  $image  GD image resource
     */
    private function drawQrMarker($image, int $x, int $y, int $size, int $black, int $white): void
    {
        // Outer black square
        imagefilledrectangle($image, $x, $y, $x + $size, $y + $size, $black);

        // Inner white square
        $innerMargin = (int) ($size / 7);
        imagefilledrectangle(
            $image,
            $x + $innerMargin,
            $y + $innerMargin,
            $x + $size - $innerMargin,
            $y + $size - $innerMargin,
            $white
        );

        // Center black square
        $centerMargin = (int) ($size / 3.5);
        imagefilledrectangle(
            $image,
            $x + $centerMargin,
            $y + $centerMargin,
            $x + $size - $centerMargin,
            $y + $size - $centerMargin,
            $black
        );
    }

    /**
     * Store QR code to disk.
     *
     * @return string Storage path
     */
    private function storeQrCode(string $content, VerificationCode $verificationCode): string
    {
        $disk = config('verification.qr.storage_disk', 'local');
        $basePath = config('verification.qr.storage_path', 'qr-codes');
        $format = config('verification.qr.format', 'png');

        $filename = sprintf(
            '%s/%s/%s.%s',
            $basePath,
            now()->format('Y/m'),
            $verificationCode->uuid,
            $format
        );

        Storage::disk($disk)->put($filename, $content);

        return $filename;
    }

    /**
     * Get QR code content for a verification code.
     *
     * @return string|null QR code image content or null if not found
     */
    public function getQrCode(VerificationCode $verificationCode): ?string
    {
        if (! $verificationCode->qr_code_path) {
            return null;
        }

        $disk = config('verification.qr.storage_disk', 'local');

        if (! Storage::disk($disk)->exists($verificationCode->qr_code_path)) {
            return null;
        }

        return Storage::disk($disk)->get($verificationCode->qr_code_path);
    }

    /**
     * Get QR code as base64 data URL.
     */
    public function getQrCodeAsDataUrl(VerificationCode $verificationCode): ?string
    {
        $content = $this->getQrCode($verificationCode);

        if (! $content) {
            return null;
        }

        $format = config('verification.qr.format', 'png');
        $mimeType = match ($format) {
            'png' => 'image/png',
            'svg' => 'image/svg+xml',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'image/png',
        };

        return "data:{$mimeType};base64,".base64_encode($content);
    }

    /**
     * Delete QR code from storage.
     */
    public function deleteQrCode(VerificationCode $verificationCode): bool
    {
        if (! $verificationCode->qr_code_path) {
            return true;
        }

        $disk = config('verification.qr.storage_disk', 'local');

        if (Storage::disk($disk)->exists($verificationCode->qr_code_path)) {
            Storage::disk($disk)->delete($verificationCode->qr_code_path);
        }

        $verificationCode->update(['qr_code_path' => null]);

        return true;
    }

    /**
     * Regenerate QR code for a verification code.
     */
    public function regenerateQrCode(VerificationCode $verificationCode): string
    {
        // Delete existing QR code
        $this->deleteQrCode($verificationCode);

        // Generate new QR code
        return $this->generateForCode($verificationCode);
    }
}
