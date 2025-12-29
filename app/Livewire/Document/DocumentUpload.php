<?php

declare(strict_types=1);

namespace App\Livewire\Document;

use App\Models\Document;
use App\Services\Document\DocumentUploadException;
use App\Services\Document\DocumentUploadService;
use App\Services\Document\DuplicateDocumentException;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Livewire component for document upload with drag & drop support.
 *
 * Features:
 * - Drag and drop upload
 * - Real-time validation
 * - Progress bar
 * - Document preview
 * - Recent documents list
 */
class DocumentUpload extends Component
{
    use WithFileUploads;

    /**
     * The uploaded file.
     */
    #[Validate('required|file|max:51200|mimes:pdf')]
    public $file;

    /**
     * Whether an upload is in progress.
     */
    public bool $uploading = false;

    /**
     * The uploaded document data.
     *
     * @var array<string, mixed>|null
     */
    public ?array $uploadedDocument = null;

    /**
     * Error message if upload fails.
     */
    public ?string $error = null;

    /**
     * Warning messages from validation.
     *
     * @var array<string>
     */
    public array $warnings = [];

    /**
     * Upload progress percentage.
     */
    public int $progress = 0;

    /**
     * Maximum file size in bytes.
     */
    public int $maxSize;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->maxSize = config('documents.max_size', 50 * 1024 * 1024);
    }

    /**
     * Handle file selection/update.
     */
    public function updatedFile(): void
    {
        $this->error = null;
        $this->warnings = [];
        $this->uploadedDocument = null;

        // Validate immediately on file selection
        $this->validateOnly('file');
    }

    /**
     * Upload the document.
     */
    public function upload(DocumentUploadService $uploadService): void
    {
        $this->validate();

        $this->uploading = true;
        $this->error = null;
        $this->progress = 0;

        try {
            $document = $uploadService->upload(
                $this->file,
                auth()->user()
            );

            $this->uploadedDocument = [
                'uuid' => $document->uuid,
                'name' => $document->original_filename,
                'size' => $document->file_size,
                'formatted_size' => $document->getFormattedFileSize(),
                'pages' => $document->page_count,
                'hash' => $document->sha256_hash,
                'thumbnail_url' => $document->getThumbnailUrl(),
                'download_url' => $document->getDownloadUrl(),
                'has_javascript' => $document->has_javascript,
                'has_signatures' => $document->has_signatures,
                'is_pdf_a' => $document->is_pdf_a,
                'created_at' => $document->created_at->toIso8601String(),
            ];

            // Dispatch event for parent components
            $this->dispatch('document-uploaded', documentId: $document->id, uuid: $document->uuid);

            // Reset file input
            $this->reset('file');

        } catch (DuplicateDocumentException $e) {
            $this->error = $e->getMessage();
            $this->dispatch('document-duplicate', existingUuid: $e->existingUuid);

        } catch (DocumentUploadException $e) {
            $this->error = 'Validation failed: '.$e->getErrorsAsString();
            $this->warnings = [];

        } catch (\Exception $e) {
            $this->error = 'Upload failed. Please try again.';
            Log::error('Document upload failed in Livewire component', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);
        }

        $this->uploading = false;
    }

    /**
     * Remove the uploaded file and reset state.
     */
    public function removeFile(): void
    {
        $this->reset(['file', 'uploadedDocument', 'error', 'warnings', 'progress']);
    }

    /**
     * Cancel the current upload.
     */
    public function cancelUpload(): void
    {
        $this->uploading = false;
        $this->reset(['file', 'error', 'warnings', 'progress']);
    }

    /**
     * Get recent documents for the current user.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Document>
     */
    #[Computed]
    public function recentDocuments()
    {
        return Document::query()
            ->byUser(auth()->id())
            ->ready()
            ->recent(7) // Last 7 days
            ->limit(5)
            ->get();
    }

    /**
     * Get maximum file size in MB.
     */
    #[Computed]
    public function maxSizeMb(): float
    {
        return round($this->maxSize / 1024 / 1024, 0);
    }

    /**
     * Get maximum allowed pages.
     */
    #[Computed]
    public function maxPages(): int
    {
        return config('documents.max_pages', 500);
    }

    /**
     * Handle document deleted event (refresh recent list).
     */
    #[On('document-deleted')]
    public function refreshRecentDocuments(): void
    {
        unset($this->recentDocuments);
    }

    /**
     * Render the component.
     */
    public function render()
    {
        return view('livewire.document.document-upload');
    }
}
