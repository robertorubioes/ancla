<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Signer;
use App\Models\SigningProcess;
use App\Services\Document\FinalDocumentService;
use App\Services\Evidence\EvidenceDossierService;
use App\Services\Notification\CompletionNotificationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use ZipArchive;

/**
 * Controller for secure document downloads via token.
 */
class DocumentDownloadController extends Controller
{
    public function __construct(
        private readonly FinalDocumentService $finalDocumentService,
        private readonly EvidenceDossierService $dossierService
    ) {}

    /**
     * Download signed document using secure token.
     */
    public function download(Request $request, string $token): Response
    {
        // Find signer by download token
        $signer = Signer::where('download_token', $token)->first();

        if (! $signer) {
            Log::warning('Download attempt with invalid token', [
                'token' => substr($token, 0, 10).'...',
                'ip' => $request->ip(),
            ]);

            abort(404, 'Download link not found or invalid.');
        }

        // Check if token has expired
        if ($signer->download_expires_at && $signer->download_expires_at->isPast()) {
            Log::warning('Download attempt with expired token', [
                'signer_id' => $signer->id,
                'expired_at' => $signer->download_expires_at->toIso8601String(),
            ]);

            abort(410, 'Download link has expired. Please request a new copy.');
        }

        // Get signing process
        $signingProcess = $signer->signingProcess()->with('document')->first();

        if (! $signingProcess) {
            Log::error('Signing process not found for signer', [
                'signer_id' => $signer->id,
            ]);

            abort(404, 'Document not found.');
        }

        // Check if final document exists
        if (! $signingProcess->hasFinalDocument()) {
            Log::error('Final document not found', [
                'process_id' => $signingProcess->id,
                'signer_id' => $signer->id,
            ]);

            abort(404, 'Signed document not available.');
        }

        try {
            // Get final document content
            $content = $this->finalDocumentService->getFinalDocumentContent($signingProcess);

            if (! $content) {
                throw CompletionNotificationException::noFinalDocument($signingProcess->id);
            }

            // Update download statistics
            $signer->update([
                'downloaded_at' => now(),
                'download_count' => $signer->download_count + 1,
            ]);

            // Log download event
            Log::info('Document downloaded', [
                'process_id' => $signingProcess->id,
                'signer_id' => $signer->id,
                'download_count' => $signer->download_count + 1,
                'ip' => $request->ip(),
            ]);

            // Create audit trail
            if (method_exists($signer, 'logAuditEvent')) {
                $signer->logAuditEvent('signer.document_downloaded', [
                    'download_count' => $signer->download_count + 1,
                    'ip' => $request->ip(),
                ]);
            }

            // Return PDF file
            return response($content, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$signingProcess->final_document_name.'"',
                'Content-Length' => strlen($content),
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to download document', [
                'process_id' => $signingProcess->id,
                'signer_id' => $signer->id,
                'error' => $e->getMessage(),
            ]);

            abort(500, 'Failed to download document. Please try again later.');
        }
    }

    /**
     * Download signed document (for promoter/creator).
     */
    public function downloadDocument(Request $request, SigningProcess $signingProcess): Response
    {
        // Authorization: Only creator can download
        if ($signingProcess->created_by !== $request->user()->id) {
            abort(403, 'Unauthorized to download this document.');
        }

        // Check if final document exists
        if (! $signingProcess->hasFinalDocument()) {
            abort(404, 'Signed document not available yet.');
        }

        try {
            $content = $this->finalDocumentService->getFinalDocumentContent($signingProcess);

            if (! $content) {
                abort(404, 'Document file not found.');
            }

            Log::info('Promoter downloaded final document', [
                'process_id' => $signingProcess->id,
                'user_id' => $request->user()->id,
            ]);

            return response($content, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$signingProcess->final_document_name.'"',
                'Content-Length' => strlen($content),
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to download final document', [
                'process_id' => $signingProcess->id,
                'error' => $e->getMessage(),
            ]);

            abort(500, 'Failed to download document.');
        }
    }

    /**
     * Download evidence dossier (for promoter/creator).
     */
    public function downloadDossier(Request $request, SigningProcess $signingProcess): Response
    {
        // Authorization: Only creator can download
        if ($signingProcess->created_by !== $request->user()->id) {
            abort(403, 'Unauthorized to download this dossier.');
        }

        // Check if process is completed
        if (! $signingProcess->isCompleted()) {
            abort(404, 'Evidence dossier not available until process is completed.');
        }

        try {
            // Generate dossier PDF on-the-fly
            $dossierPdf = $this->dossierService->generateDossier($signingProcess);

            $filename = sprintf(
                'evidence_dossier_%s.pdf',
                $signingProcess->uuid
            );

            Log::info('Promoter downloaded evidence dossier', [
                'process_id' => $signingProcess->id,
                'user_id' => $request->user()->id,
            ]);

            return response($dossierPdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Content-Length' => strlen($dossierPdf),
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to download evidence dossier', [
                'process_id' => $signingProcess->id,
                'error' => $e->getMessage(),
            ]);

            abort(500, 'Failed to download dossier.');
        }
    }

    /**
     * Download bundle (document + dossier in ZIP).
     */
    public function downloadBundle(Request $request, SigningProcess $signingProcess): Response
    {
        // Authorization: Only creator can download
        if ($signingProcess->created_by !== $request->user()->id) {
            abort(403, 'Unauthorized to download this bundle.');
        }

        // Check if process is completed
        if (! $signingProcess->isCompleted() || ! $signingProcess->hasFinalDocument()) {
            abort(404, 'Bundle not available yet.');
        }

        try {
            // Create temporary ZIP file
            $zipPath = storage_path('app/temp/bundle_'.$signingProcess->uuid.'.zip');
            $zip = new ZipArchive;

            // Ensure temp directory exists
            if (! file_exists(dirname($zipPath))) {
                mkdir(dirname($zipPath), 0755, true);
            }

            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \Exception('Failed to create ZIP file');
            }

            // Add final document
            $finalDocContent = $this->finalDocumentService->getFinalDocumentContent($signingProcess);
            $zip->addFromString($signingProcess->final_document_name, $finalDocContent);

            // Add evidence dossier
            $dossierPdf = $this->dossierService->generateDossier($signingProcess);
            $dossierFilename = sprintf('evidence_dossier_%s.pdf', $signingProcess->uuid);
            $zip->addFromString($dossierFilename, $dossierPdf);

            $zip->close();

            // Read ZIP content
            $zipContent = file_get_contents($zipPath);

            // Clean up temp file
            @unlink($zipPath);

            $bundleFilename = sprintf(
                'signed_bundle_%s.zip',
                $signingProcess->uuid
            );

            Log::info('Promoter downloaded bundle', [
                'process_id' => $signingProcess->id,
                'user_id' => $request->user()->id,
            ]);

            return response($zipContent, 200, [
                'Content-Type' => 'application/zip',
                'Content-Disposition' => 'attachment; filename="'.$bundleFilename.'"',
                'Content-Length' => strlen($zipContent),
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to download bundle', [
                'process_id' => $signingProcess->id,
                'error' => $e->getMessage(),
            ]);

            // Clean up on error
            if (isset($zipPath) && file_exists($zipPath)) {
                @unlink($zipPath);
            }

            abort(500, 'Failed to download bundle.');
        }
    }
}
