<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Models\SignedDocument;
use App\Models\SigningProcess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

/**
 * Generate final consolidated document with all signatures.
 *
 * This service merges all individual signed PDFs into a single
 * final document and appends a certification page.
 */
class FinalDocumentService
{
    public function __construct(
        private readonly CertificationPageBuilder $certificationBuilder
    ) {}

    /**
     * Generate final document for a completed signing process.
     *
     * @throws FinalDocumentException
     */
    public function generateFinalDocument(SigningProcess $process): FinalDocumentResult
    {
        Log::info('Starting final document generation', [
            'process_id' => $process->id,
            'process_uuid' => $process->uuid,
        ]);

        // Validate process is ready for final document
        $this->validateProcess($process);

        return DB::transaction(function () use ($process) {
            try {
                // 1. Get all signed documents for this process
                $signedDocuments = $this->getSignedDocuments($process);

                if ($signedDocuments->isEmpty()) {
                    throw FinalDocumentException::noSignedDocuments($process->id);
                }

                Log::info('Found signed documents', [
                    'count' => $signedDocuments->count(),
                ]);

                // 2. Merge all signed PDFs
                $mergedPdfContent = $this->mergeSignedDocuments($signedDocuments);

                Log::info('PDFs merged', [
                    'size' => strlen($mergedPdfContent),
                ]);

                // 3. Add certification page
                $finalPdfContent = $this->addCertificationPage($mergedPdfContent, $process);

                Log::info('Certification page added', [
                    'final_size' => strlen($finalPdfContent),
                ]);

                // 4. Calculate hash and metadata
                $contentHash = hash('sha256', $finalPdfContent);
                $pageCount = $this->countPdfPages($finalPdfContent);

                // 5. Store final document
                $storagePath = $this->storeFinalDocument($finalPdfContent, $process);

                Log::info('Final document stored', [
                    'path' => $storagePath,
                    'hash' => $contentHash,
                ]);

                // 6. Update signing process with final document info
                $process->update([
                    'final_document_path' => $storagePath,
                    'final_document_name' => $this->generateFinalDocumentName($process),
                    'final_document_hash' => $contentHash,
                    'final_document_size' => strlen($finalPdfContent),
                    'final_document_generated_at' => now(),
                    'final_document_pages' => $pageCount,
                ]);

                Log::info('Final document generation completed', [
                    'process_id' => $process->id,
                    'document_path' => $storagePath,
                    'pages' => $pageCount,
                ]);

                return new FinalDocumentResult(
                    success: true,
                    storagePath: $storagePath,
                    contentHash: $contentHash,
                    fileSize: strlen($finalPdfContent),
                    pageCount: $pageCount,
                    signingProcess: $process->fresh()
                );
            } catch (FinalDocumentException $e) {
                Log::error('Final document generation failed', [
                    'process_id' => $process->id,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            } catch (\Exception $e) {
                Log::error('Unexpected error during final document generation', [
                    'process_id' => $process->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw FinalDocumentException::generationFailed($e->getMessage());
            }
        });
    }

    /**
     * Merge multiple signed PDF documents into one.
     *
     * @param  \Illuminate\Support\Collection<SignedDocument>  $signedDocuments
     */
    private function mergeSignedDocuments($signedDocuments): string
    {
        $pdf = new Fpdi;

        foreach ($signedDocuments as $signedDoc) {
            try {
                $pdfPath = Storage::path($signedDoc->signed_path);

                if (! file_exists($pdfPath)) {
                    throw FinalDocumentException::signedDocumentNotFound($signedDoc->id, $pdfPath);
                }

                // Verify integrity before merging
                if (! $signedDoc->verifyIntegrity()) {
                    throw FinalDocumentException::integrityCheckFailed($signedDoc->id);
                }

                // Import all pages from this signed document
                $pageCount = $pdf->setSourceFile($pdfPath);

                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $pdf->AddPage();
                    $tplId = $pdf->importPage($pageNo);
                    $pdf->useTemplate($tplId);
                }

                Log::debug('Merged signed document', [
                    'signed_document_id' => $signedDoc->id,
                    'pages' => $pageCount,
                ]);
            } catch (FinalDocumentException $e) {
                throw $e;
            } catch (\Exception $e) {
                throw FinalDocumentException::mergeFailed($signedDoc->id, $e->getMessage());
            }
        }

        return $pdf->Output('S');
    }

    /**
     * Add certification page to the end of the document.
     */
    private function addCertificationPage(string $pdfContent, SigningProcess $process): string
    {
        // Save merged PDF to temp file
        $tempMergedPath = tempnam(sys_get_temp_dir(), 'merged_');
        file_put_contents($tempMergedPath, $pdfContent);

        try {
            // Create new PDF with all pages plus certification
            $pdf = new Fpdi;

            // Import all pages from merged document
            $pageCount = $pdf->setSourceFile($tempMergedPath);

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $pdf->AddPage();
                $tplId = $pdf->importPage($pageNo);
                $pdf->useTemplate($tplId);
            }

            // Build and append certification page
            $certificationPdf = $this->certificationBuilder->build($process);
            $tempCertPath = tempnam(sys_get_temp_dir(), 'cert_');
            file_put_contents($tempCertPath, $certificationPdf);

            $certPageCount = $pdf->setSourceFile($tempCertPath);

            for ($pageNo = 1; $pageNo <= $certPageCount; $pageNo++) {
                $pdf->AddPage();
                $tplId = $pdf->importPage($pageNo);
                $pdf->useTemplate($tplId);
            }

            $finalContent = $pdf->Output('S');

            // Cleanup temp files
            @unlink($tempMergedPath);
            @unlink($tempCertPath);

            return $finalContent;
        } catch (\Exception $e) {
            // Cleanup on error
            @unlink($tempMergedPath);
            @unlink($tempCertPath ?? null);

            throw FinalDocumentException::certificationFailed($e->getMessage());
        }
    }

    /**
     * Store final document in storage.
     */
    private function storeFinalDocument(string $content, SigningProcess $process): string
    {
        $path = sprintf(
            'final/%s/%s/%s.pdf',
            $process->tenant_id,
            now()->format('Y/m'),
            $process->uuid
        );

        Storage::disk('local')->put($path, $content);

        return $path;
    }

    /**
     * Generate filename for final document.
     */
    private function generateFinalDocumentName(SigningProcess $process): string
    {
        $originalName = pathinfo($process->document->original_name, PATHINFO_FILENAME);

        return sprintf('%s_signed_final.pdf', $originalName);
    }

    /**
     * Get all signed documents for the process, ordered by signer order.
     */
    private function getSignedDocuments(SigningProcess $process)
    {
        return SignedDocument::query()
            ->where('signing_process_id', $process->id)
            ->where('tenant_id', $process->tenant_id)
            ->where('status', 'signed')
            ->with(['signer'])
            ->get()
            ->sortBy(fn ($doc) => $doc->signer->order);
    }

    /**
     * Count pages in a PDF.
     */
    private function countPdfPages(string $pdfContent): int
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'count_');
        file_put_contents($tempPath, $pdfContent);

        try {
            $pdf = new Fpdi;
            $pageCount = $pdf->setSourceFile($tempPath);
            @unlink($tempPath);

            return $pageCount;
        } catch (\Exception $e) {
            @unlink($tempPath);

            return 0;
        }
    }

    /**
     * Validate process is ready for final document generation.
     */
    private function validateProcess(SigningProcess $process): void
    {
        if (! $process->isCompleted()) {
            throw FinalDocumentException::processNotCompleted($process->id);
        }

        if ($process->final_document_path !== null) {
            throw FinalDocumentException::alreadyGenerated($process->id);
        }

        if (! $process->allSignersCompleted()) {
            throw FinalDocumentException::notAllSignersSigned($process->id);
        }

        // Check if process has signers
        if ($process->signers()->count() === 0) {
            throw FinalDocumentException::noSigners($process->id);
        }
    }

    /**
     * Check if final document exists and is valid.
     */
    public function verifyFinalDocument(SigningProcess $process): bool
    {
        if (! $process->final_document_path) {
            return false;
        }

        $path = Storage::path($process->final_document_path);

        if (! file_exists($path)) {
            return false;
        }

        // Verify hash integrity
        $currentHash = hash_file('sha256', $path);

        return hash_equals($process->final_document_hash, $currentHash);
    }

    /**
     * Get final document content.
     */
    public function getFinalDocumentContent(SigningProcess $process): ?string
    {
        if (! $process->final_document_path) {
            return null;
        }

        if (! $this->verifyFinalDocument($process)) {
            throw FinalDocumentException::integrityCheckFailed($process->id);
        }

        return Storage::disk('local')->get($process->final_document_path);
    }

    /**
     * Regenerate final document (if needed).
     */
    public function regenerateFinalDocument(SigningProcess $process): FinalDocumentResult
    {
        Log::info('Regenerating final document', [
            'process_id' => $process->id,
        ]);

        // Delete old final document if exists
        if ($process->final_document_path) {
            Storage::disk('local')->delete($process->final_document_path);

            $process->update([
                'final_document_path' => null,
                'final_document_name' => null,
                'final_document_hash' => null,
                'final_document_size' => null,
                'final_document_generated_at' => null,
                'final_document_pages' => null,
            ]);
        }

        // Generate new final document
        return $this->generateFinalDocument($process);
    }
}
