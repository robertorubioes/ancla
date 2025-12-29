<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Verification\PublicVerificationService;
use App\Services\Verification\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Controller for public document verification API.
 *
 * These endpoints are public and do not require authentication.
 * Rate limiting is applied to prevent abuse.
 *
 * @see ADR-007 in docs/architecture/adr-007-sprint3-retention-verification-upload.md
 */
class PublicVerificationController extends Controller
{
    public function __construct(
        private readonly PublicVerificationService $verificationService,
        private readonly QrCodeService $qrCodeService,
    ) {}

    /**
     * Verify a document by verification code.
     *
     * @unauthenticated
     *
     * @group Public Verification
     *
     * @urlParam code string required The verification code (12 characters, with or without dashes). Example: ABCD-EFGH-IJKL
     *
     * @response 200 {
     *   "valid": true,
     *   "confidence": {
     *     "score": 95,
     *     "level": "high"
     *   },
     *   "document": {
     *     "filename": "contract.pdf",
     *     "hash": "sha256:abc123...",
     *     "uploaded_at": "2025-01-15T10:30:00Z",
     *     "pages": 5
     *   },
     *   "verification": {
     *     "document_integrity": true,
     *     "chain_integrity": true,
     *     "tsa_valid": true,
     *     "timestamp": "2025-01-15T10:30:00Z"
     *   },
     *   "verified_at": "2025-12-28T19:00:00Z"
     * }
     * @response 400 {
     *   "valid": false,
     *   "error": "Invalid verification code format",
     *   "verified_at": "2025-12-28T19:00:00Z"
     * }
     * @response 404 {
     *   "valid": false,
     *   "error": "Verification code not found",
     *   "verified_at": "2025-12-28T19:00:00Z"
     * }
     */
    public function verifyByCode(string $code): JsonResponse
    {
        $result = $this->verificationService->verifyByCode($code);

        $statusCode = match (true) {
            $result->isValid => 200,
            $result->getMetadata('result') === 'not_found' => 404,
            $result->getMetadata('result') === 'expired' => 410,
            $result->getMetadata('result') === 'invalid_code' => 400,
            default => 400,
        };

        return response()->json($result->toResponse(), $statusCode)
            ->header('X-Verification-Status', $result->isValid ? 'valid' : 'invalid')
            ->header('X-Confidence-Level', $result->confidenceLevel)
            ->header('X-Confidence-Score', (string) $result->confidenceScore);
    }

    /**
     * Verify a document by its SHA-256 hash.
     *
     * @unauthenticated
     *
     * @group Public Verification
     *
     * @bodyParam hash string required The SHA-256 hash of the document (64 hex characters). Example: a1b2c3d4e5f6...
     *
     * @response 200 {
     *   "valid": true,
     *   "confidence": {
     *     "score": 90,
     *     "level": "high"
     *   },
     *   "document": {
     *     "filename": "contract.pdf",
     *     "hash": "sha256:a1b2c3d4e5f6...",
     *     "uploaded_at": "2025-01-15T10:30:00Z",
     *     "pages": 5
     *   },
     *   "verification_code": "ABCD-EFGH-IJKL",
     *   "verified_at": "2025-12-28T19:00:00Z"
     * }
     * @response 400 {
     *   "valid": false,
     *   "error": "Invalid hash format",
     *   "verified_at": "2025-12-28T19:00:00Z"
     * }
     * @response 404 {
     *   "valid": false,
     *   "error": "No document found with provided hash",
     *   "verified_at": "2025-12-28T19:00:00Z"
     * }
     */
    public function verifyByHash(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hash' => ['required', 'string', 'regex:/^[a-fA-F0-9]{64}$/'],
        ]);

        $result = $this->verificationService->verifyByHash($validated['hash']);

        $statusCode = match (true) {
            $result->isValid => 200,
            $result->getMetadata('result') === 'not_found' => 404,
            default => 400,
        };

        $response = $result->toResponse();

        // Add verification code if available
        $verificationCode = $result->getMetadata('verification_code');
        if ($verificationCode) {
            $response['verification_code'] = $verificationCode;
        }

        return response()->json($response, $statusCode)
            ->header('X-Verification-Status', $result->isValid ? 'valid' : 'invalid')
            ->header('X-Confidence-Level', $result->confidenceLevel)
            ->header('X-Confidence-Score', (string) $result->confidenceScore);
    }

    /**
     * Get detailed verification information.
     *
     * @unauthenticated
     *
     * @group Public Verification
     *
     * @urlParam code string required The verification code. Example: ABCD-EFGH-IJKL
     *
     * @response 200 {
     *   "document": {
     *     "filename": "contract.pdf",
     *     "hash": "a1b2c3d4e5f6...",
     *     "algorithm": "SHA-256",
     *     "pages": 5,
     *     "size": 1048576,
     *     "uploaded_at": "2025-01-15T10:30:00Z",
     *     "uploaded_by": "John Doe"
     *   },
     *   "verification": {
     *     "code": "ABCD-EFGH-IJKL",
     *     "short_code": "ABCDEF",
     *     "created_at": "2025-01-15T10:30:00Z",
     *     "expires_at": null,
     *     "access_count": 5,
     *     "last_accessed_at": "2025-12-28T18:00:00Z"
     *   },
     *   "integrity": {
     *     "is_valid": true,
     *     "confidence_score": 95,
     *     "confidence_level": "high",
     *     "checks": [...]
     *   },
     *   "tsa": {
     *     "provider": "DigiCert",
     *     "timestamp": "2025-01-15T10:30:00Z",
     *     "valid_until": "2026-01-15T10:30:00Z"
     *   }
     * }
     * @response 404 {
     *   "error": "Verification code not found"
     * }
     */
    public function getDetails(string $code): JsonResponse
    {
        $details = $this->verificationService->getVerificationDetails($code);

        if (! $details) {
            return response()->json([
                'error' => 'Verification code not found',
            ], 404);
        }

        return response()->json($details);
    }

    /**
     * Get the QR code image for a verification code.
     *
     * @unauthenticated
     *
     * @group Public Verification
     *
     * @urlParam code string required The verification code. Example: ABCD-EFGH-IJKL
     *
     * @response 200 Binary PNG image
     * @response 404 {
     *   "error": "Verification code not found"
     * }
     */
    public function getQrCode(string $code): Response|JsonResponse
    {
        $verificationCode = \App\Models\VerificationCode::byCode($code)->first();

        if (! $verificationCode) {
            return response()->json([
                'error' => 'Verification code not found',
            ], 404);
        }

        // Generate QR code if not exists
        if (! $verificationCode->qr_code_path) {
            $this->qrCodeService->generateForCode($verificationCode);
            $verificationCode->refresh();
        }

        $qrContent = $this->qrCodeService->getQrCode($verificationCode);

        if (! $qrContent) {
            return response()->json([
                'error' => 'QR code not available',
            ], 404);
        }

        $format = config('verification.qr.format', 'png');
        $mimeType = match ($format) {
            'png' => 'image/png',
            'svg' => 'image/svg+xml',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'image/png',
        };

        return response($qrContent, 200)
            ->header('Content-Type', $mimeType)
            ->header('Content-Disposition', "inline; filename=\"qr-{$code}.{$format}\"")
            ->header('Cache-Control', 'public, max-age=86400'); // Cache for 24 hours
    }

    /**
     * Download evidence dossier for a verified document.
     *
     * @unauthenticated
     *
     * @group Public Verification
     *
     * @urlParam code string required The verification code. Example: ABCD-EFGH-IJKL
     *
     * @response 200 Binary PDF file
     * @response 404 {
     *   "error": "Verification code not found"
     * }
     * @response 403 {
     *   "error": "Document verification failed. Cannot download evidence."
     * }
     */
    public function downloadEvidence(string $code): Response|JsonResponse
    {
        // Check if downloads are allowed
        if (! config('verification.page.allow_download', true)) {
            return response()->json([
                'error' => 'Evidence downloads are not enabled',
            ], 403);
        }

        // Verify the document first
        $result = $this->verificationService->verifyByCode($code);

        if (! $result->isValid) {
            return response()->json([
                'error' => 'Document verification failed. Cannot download evidence.',
                'verification_result' => $result->toResponse(),
            ], 403);
        }

        if (! $result->document) {
            return response()->json([
                'error' => 'Document not found',
            ], 404);
        }

        // Try to generate evidence dossier
        // This requires the EvidenceDossierService
        try {
            $dossierService = app(\App\Services\Evidence\EvidenceDossierService::class);

            $dossier = $dossierService->generateForDocument(
                $result->document,
                'legal'
            );

            $content = $dossierService->renderToPdf($dossier);

            return response($content, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', "attachment; filename=\"evidence-{$code}.pdf\"")
                ->header('Content-Length', (string) strlen($content));

        } catch (\Exception $e) {
            // If evidence dossier service is not available, return basic info
            return response()->json([
                'error' => 'Evidence dossier generation not available',
                'verification_result' => $result->toResponse(),
            ], 503);
        }
    }

    /**
     * Get the verification page URL for a code.
     *
     * @unauthenticated
     *
     * @group Public Verification
     *
     * @urlParam code string required The verification code. Example: ABCD-EFGH-IJKL
     *
     * @response 200 {
     *   "url": "https://ancla.app/verify/ABCD-EFGH-IJKL",
     *   "short_url": "https://ancla.app/v/ABCDEF",
     *   "qr_code_url": "https://ancla.app/api/v1/public/verify/ABCD-EFGH-IJKL/qr"
     * }
     */
    public function getUrls(string $code): JsonResponse
    {
        $verificationCode = \App\Models\VerificationCode::byCode($code)->first();

        if (! $verificationCode) {
            return response()->json([
                'error' => 'Verification code not found',
            ], 404);
        }

        return response()->json([
            'url' => $this->qrCodeService->generateVerificationUrl($verificationCode->verification_code),
            'short_url' => $this->qrCodeService->generateShortVerificationUrl($verificationCode->short_code),
            'qr_code_url' => route('api.public.verify.qr', ['code' => $verificationCode->verification_code]),
        ]);
    }
}
