<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\Document\DocumentUploadException;
use App\Services\Document\DocumentUploadService;
use App\Services\Document\DuplicateDocumentException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Controller for document management operations.
 *
 * Handles document CRUD operations including upload, view, download,
 * thumbnail retrieval, and deletion.
 */
class DocumentController extends Controller
{
    public function __construct(
        private readonly DocumentUploadService $uploadService,
    ) {}

    /**
     * Display a listing of documents.
     */
    public function index(Request $request): View|JsonResponse
    {
        $query = Document::query()
            ->byUser(auth()->id())
            ->ready()
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('original_filename', 'like', "%{$search}%");
        }

        if ($request->has('status')) {
            $query->withStatus($request->input('status'));
        }

        $documents = $query->paginate(15);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $documents,
            ]);
        }

        return view('documents.index', compact('documents'));
    }

    /**
     * Store a newly uploaded document.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'max:'.(config('documents.max_size') / 1024), // KB
                'mimes:pdf',
            ],
        ]);

        try {
            $document = $this->uploadService->upload(
                $request->file('file'),
                $request->user()
            );

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Document uploaded successfully',
                    'data' => [
                        'uuid' => $document->uuid,
                        'name' => $document->original_filename,
                        'size' => $document->file_size,
                        'pages' => $document->page_count,
                        'hash' => $document->sha256_hash,
                        'thumbnail_url' => $document->getThumbnailUrl(),
                        'download_url' => $document->getDownloadUrl(),
                        'created_at' => $document->created_at->toIso8601String(),
                    ],
                ], 201);
            }

            return redirect()
                ->route('documents.show', $document)
                ->with('success', 'Document uploaded successfully');

        } catch (DuplicateDocumentException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'existing_uuid' => $e->existingUuid,
                ], 409);
            }

            return redirect()
                ->back()
                ->withErrors(['file' => $e->getMessage()])
                ->withInput();

        } catch (DocumentUploadException $e) {
            Log::warning('Document upload validation failed', [
                'errors' => $e->errors,
                'user_id' => auth()->id(),
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors,
                ], 422);
            }

            return redirect()
                ->back()
                ->withErrors(['file' => $e->getErrorsAsString()])
                ->withInput();

        } catch (\Exception $e) {
            Log::error('Document upload failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Upload failed. Please try again.',
                ], 500);
            }

            return redirect()
                ->back()
                ->withErrors(['file' => 'Upload failed. Please try again.'])
                ->withInput();
        }
    }

    /**
     * Display the specified document.
     */
    public function show(Request $request, Document $document): View|JsonResponse
    {
        // Authorization check
        if ($document->user_id !== auth()->id()) {
            abort(403, 'Unauthorized access to document');
        }

        $document->load(['user', 'uploadTsaToken']);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'uuid' => $document->uuid,
                    'name' => $document->original_filename,
                    'size' => $document->file_size,
                    'formatted_size' => $document->getFormattedFileSize(),
                    'pages' => $document->page_count,
                    'hash' => $document->sha256_hash,
                    'status' => $document->status,
                    'metadata' => $document->pdf_metadata,
                    'pdf_version' => $document->pdf_version,
                    'is_pdf_a' => $document->is_pdf_a,
                    'has_signatures' => $document->has_signatures,
                    'has_javascript' => $document->has_javascript,
                    'thumbnail_url' => $document->getThumbnailUrl(),
                    'download_url' => $document->getDownloadUrl(),
                    'tsa_timestamp' => $document->uploadTsaToken?->issued_at?->toIso8601String(),
                    'created_at' => $document->created_at->toIso8601String(),
                    'updated_at' => $document->updated_at->toIso8601String(),
                ],
            ]);
        }

        return view('documents.show', compact('document'));
    }

    /**
     * Download the original document.
     */
    public function download(Request $request, Document $document): Response
    {
        // Verify signature for signed URLs
        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid or expired download link');
        }

        // Authorization check
        if ($document->user_id !== auth()->id()) {
            abort(403, 'Unauthorized access to document');
        }

        if (! $document->isReady()) {
            abort(404, 'Document is not available for download');
        }

        try {
            $content = $this->uploadService->getDecryptedContent($document);

            return response($content, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$document->original_filename.'"',
                'Content-Length' => strlen($content),
                'Cache-Control' => 'private, no-cache, no-store',
            ]);

        } catch (\Exception $e) {
            Log::error('Document download failed', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            abort(500, 'Failed to retrieve document');
        }
    }

    /**
     * Get the document thumbnail.
     */
    public function thumbnail(Request $request, Document $document): Response
    {
        // Verify signature for signed URLs
        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid or expired thumbnail link');
        }

        // Authorization check
        if ($document->user_id !== auth()->id()) {
            abort(403, 'Unauthorized access to document');
        }

        if (! $document->hasThumbnail()) {
            abort(404, 'Thumbnail not available');
        }

        try {
            $content = \Storage::disk($document->storage_disk)->get($document->thumbnail_path);

            if ($content === null) {
                abort(404, 'Thumbnail not found');
            }

            $format = config('documents.thumbnail.format', 'png');
            $mimeType = $format === 'png' ? 'image/png' : 'image/jpeg';

            return response($content, 200, [
                'Content-Type' => $mimeType,
                'Content-Length' => strlen($content),
                'Cache-Control' => 'private, max-age=3600',
            ]);

        } catch (\Exception $e) {
            Log::error('Thumbnail retrieval failed', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            abort(500, 'Failed to retrieve thumbnail');
        }
    }

    /**
     * Remove the specified document (soft delete).
     */
    public function destroy(Request $request, Document $document): JsonResponse|RedirectResponse
    {
        // Authorization check
        if ($document->user_id !== auth()->id()) {
            abort(403, 'Unauthorized access to document');
        }

        try {
            $this->uploadService->delete($document);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Document deleted successfully',
                ]);
            }

            return redirect()
                ->route('documents.index')
                ->with('success', 'Document deleted successfully');

        } catch (\Exception $e) {
            Log::error('Document deletion failed', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete document',
                ], 500);
            }

            return redirect()
                ->back()
                ->withErrors(['error' => 'Failed to delete document']);
        }
    }

    /**
     * Verify document integrity.
     */
    public function verify(Request $request, Document $document): JsonResponse
    {
        // Authorization check
        if ($document->user_id !== auth()->id()) {
            abort(403, 'Unauthorized access to document');
        }

        $isValid = $this->uploadService->verifyIntegrity($document);

        return response()->json([
            'success' => true,
            'data' => [
                'document_uuid' => $document->uuid,
                'integrity_valid' => $isValid,
                'hash' => $document->sha256_hash,
                'hash_algorithm' => $document->hash_algorithm,
                'verified_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
