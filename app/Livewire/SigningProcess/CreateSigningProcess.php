<?php

declare(strict_types=1);

namespace App\Livewire\SigningProcess;

use App\Models\Document;
use App\Models\Signer;
use App\Models\SigningProcess;
use App\Services\Evidence\AuditTrailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Livewire component for creating signing processes.
 *
 * Features:
 * - Select document to sign
 * - Add multiple signers
 * - Set signing order (sequential/parallel)
 * - Optional deadline
 * - Custom message to signers
 */
class CreateSigningProcess extends Component
{
    /**
     * The selected document ID.
     */
    #[Validate('required|exists:documents,id')]
    public ?int $documentId = null;

    /**
     * Custom message for signers.
     */
    #[Validate('nullable|string|max:500')]
    public ?string $customMessage = null;

    /**
     * Deadline for signing (optional).
     */
    #[Validate('nullable|date|after:today')]
    public ?string $deadlineAt = null;

    /**
     * Signature order: sequential or parallel.
     */
    #[Validate('required|in:sequential,parallel')]
    public string $signatureOrder = 'parallel';

    /**
     * Array of signers.
     *
     * @var array<int, array{name: string, email: string, phone: ?string}>
     */
    public array $signers = [];

    /**
     * Whether a process is being created.
     */
    public bool $creating = false;

    /**
     * Error message if creation fails.
     */
    public ?string $error = null;

    /**
     * Success message.
     */
    public ?string $success = null;

    /**
     * The created process UUID (for redirect).
     */
    public ?string $createdProcessUuid = null;

    /**
     * Mount the component.
     */
    public function mount(?int $documentId = null): void
    {
        $this->documentId = $documentId;

        // Start with one empty signer
        $this->addSigner();
    }

    /**
     * Add a new signer to the list.
     */
    public function addSigner(): void
    {
        if (count($this->signers) >= 10) {
            $this->error = 'Maximum 10 signers allowed.';

            return;
        }

        $this->signers[] = [
            'name' => '',
            'email' => '',
            'phone' => '',
        ];

        $this->error = null;
    }

    /**
     * Remove a signer from the list.
     */
    public function removeSigner(int $index): void
    {
        if (count($this->signers) <= 1) {
            $this->error = 'At least one signer is required.';

            return;
        }

        unset($this->signers[$index]);
        $this->signers = array_values($this->signers); // Re-index array
        $this->error = null;
    }

    /**
     * Validate signers array.
     */
    protected function validateSigners(): void
    {
        // Validate each signer
        foreach ($this->signers as $index => $signer) {
            $this->validate([
                "signers.{$index}.name" => 'required|string|min:2|max:255',
                "signers.{$index}.email" => 'required|email|max:255',
                "signers.{$index}.phone" => 'nullable|string|max:20',
            ], [
                "signers.{$index}.name.required" => 'Signer name is required',
                "signers.{$index}.name.min" => 'Signer name must be at least 2 characters',
                "signers.{$index}.email.required" => 'Signer email is required',
                "signers.{$index}.email.email" => 'Signer email must be valid',
            ]);
        }

        // Check for duplicate emails
        $emails = array_column($this->signers, 'email');
        $duplicates = array_filter(array_count_values($emails), fn ($count) => $count > 1);

        if (! empty($duplicates)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'signers' => ['Duplicate email addresses are not allowed in the same process.'],
            ]);
        }
    }

    /**
     * Create the signing process.
     */
    public function create(AuditTrailService $auditService)
    {
        $this->creating = true;
        $this->error = null;
        $this->success = null;

        try {
            // Validate basic fields
            $this->validate();

            // Validate signers
            $this->validateSigners();

            // Check minimum signers
            if (count($this->signers) < 1) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'signers' => ['At least one signer is required.'],
                ]);
            }

            // Check maximum signers
            if (count($this->signers) > 10) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'signers' => ['Maximum 10 signers allowed.'],
                ]);
            }

            // Get the document
            $document = Document::query()
                ->where('id', $this->documentId)
                ->where('tenant_id', auth()->user()->tenant_id)
                ->ready()
                ->firstOrFail();

            // Create process in transaction
            DB::beginTransaction();

            try {
                // Create signing process
                $process = SigningProcess::create([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => auth()->user()->tenant_id,
                    'document_id' => $document->id,
                    'created_by' => auth()->id(),
                    'status' => SigningProcess::STATUS_DRAFT,
                    'signature_order' => $this->signatureOrder,
                    'custom_message' => $this->customMessage,
                    'deadline_at' => $this->deadlineAt ? now()->parse($this->deadlineAt) : null,
                ]);

                // Create signers
                foreach ($this->signers as $index => $signerData) {
                    Signer::create([
                        'uuid' => (string) Str::uuid(),
                        'signing_process_id' => $process->id,
                        'name' => trim($signerData['name']),
                        'email' => trim(strtolower($signerData['email'])),
                        'phone' => ! empty($signerData['phone']) ? trim($signerData['phone']) : null,
                        'order' => $index,
                        'status' => Signer::STATUS_PENDING,
                        'token' => Str::random(32),
                    ]);
                }

                // Register in audit trail
                $auditService->logEvent(
                    eventType: 'signing_process.created',
                    metadata: [
                        'process_id' => $process->id,
                        'process_uuid' => $process->uuid,
                        'document_id' => $document->id,
                        'document_name' => $document->original_filename,
                        'signers_count' => count($this->signers),
                        'signature_order' => $this->signatureOrder,
                        'has_deadline' => $this->deadlineAt !== null,
                    ],
                    userId: auth()->id(),
                    tenantId: auth()->user()->tenant_id
                );

                DB::commit();

                $this->success = 'Signing process created successfully!';
                $this->createdProcessUuid = $process->uuid;

                // Dispatch event
                $this->dispatch('process-created', processUuid: $process->uuid);

                // Redirect to processes list
                return $this->redirect(route('signing-processes.index'), navigate: true);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->creating = false;
            throw $e;
        } catch (\Exception $e) {
            $this->error = 'Failed to create signing process. Please try again.';

            Log::error('Failed to create signing process', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'document_id' => $this->documentId,
            ]);
        }

        $this->creating = false;
    }

    /**
     * Get available documents for the current user.
     */
    #[Computed]
    public function availableDocuments()
    {
        return Document::query()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->ready()
            ->recent(90) // Last 90 days
            ->limit(50)
            ->get()
            ->map(function (Document $doc) {
                return [
                    'id' => $doc->id,
                    'name' => $doc->original_filename,
                    'pages' => $doc->page_count,
                    'size' => $doc->getFormattedFileSize(),
                    'created_at' => $doc->created_at->diffForHumans(),
                ];
            });
    }

    /**
     * Get selected document details.
     */
    #[Computed]
    public function selectedDocument()
    {
        if (! $this->documentId) {
            return null;
        }

        return Document::query()
            ->where('id', $this->documentId)
            ->where('tenant_id', auth()->user()->tenant_id)
            ->ready()
            ->first();
    }

    /**
     * Get minimum deadline date (tomorrow).
     */
    #[Computed]
    public function minDeadlineDate(): string
    {
        return now()->addDay()->format('Y-m-d');
    }

    /**
     * Reset the form.
     */
    public function resetForm(): void
    {
        $this->reset([
            'documentId',
            'customMessage',
            'deadlineAt',
            'signatureOrder',
            'error',
            'success',
            'createdProcessUuid',
        ]);

        $this->signers = [];
        $this->addSigner();
    }

    /**
     * Render the component.
     */
    public function render()
    {
        return view('livewire.signing-process.create-signing-process');
    }
}
