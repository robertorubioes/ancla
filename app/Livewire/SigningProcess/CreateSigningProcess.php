<?php

declare(strict_types=1);

namespace App\Livewire\SigningProcess;

use App\Models\Document;
use App\Models\Signer;
use App\Models\SigningProcess;
use App\Services\Document\DocumentUploadService;
use App\Services\Evidence\AuditTrailService;
use App\Services\Template\TemplateVersionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Livewire component for creating signing processes.
 *
 * Features:
 * - Upload document directly or select existing
 * - Add multiple signers with inline editing
 * - Drag & drop reordering for sequential signing
 * - Set signing order (sequential/parallel)
 * - Optional deadline
 * - Custom message to signers
 */
class CreateSigningProcess extends Component
{
    use WithFileUploads;

    /**
     * The selected document ID.
     */
    public ?int $documentId = null;

    /**
     * File upload for new document.
     */
    #[Validate('nullable|file|max:51200|mimes:pdf')]
    public $uploadedFile = null;

    /**
     * Whether to show upload or select mode.
     */
    public string $documentMode = 'select'; // 'select' or 'upload'

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
     * Index of signer being edited (for mobile).
     */
    public ?int $editingSignerIndex = null;

    /**
     * Whether a process is being created.
     */
    public bool $creating = false;

    /**
     * Whether file is uploading.
     */
    public bool $uploadingFile = false;

    /**
     * Guardar ademas el documento como plantilla.
     *
     * Un proceso de firma que se repite suele acabar siendo una plantilla. En
     * lugar de obligar a rehacerlo desde cero, se ofrece aqui: queda en
     * borrador, y hay que colocarle los campos y habilitarla antes de poder
     * usarla.
     */
    public bool $alsoSaveAsTemplate = false;

    public ?string $createdTemplateUuid = null;

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
     * The signing process being edited (null for new).
     */
    public ?SigningProcess $editingProcess = null;

    /**
     * Whether we are in edit mode.
     */
    public bool $isEditing = false;

    /**
     * Mount the component.
     */
    public function mount(?int $documentId = null, ?SigningProcess $signingProcess = null): void
    {
        // Edit mode: load existing draft process.
        //
        // Se comprueba exists() y no solo el nulo: cuando el parametro no
        // viene de la ruta, Livewire lo resuelve del contenedor y entrega un
        // modelo vacio, que es truthy pero no representa ningun proceso.
        if ($signingProcess && $signingProcess->exists) {
            // Verify it's a draft and belongs to user's tenant
            if ($signingProcess->status !== SigningProcess::STATUS_DRAFT) {
                abort(403, 'Only draft processes can be edited.');
            }
            if ($signingProcess->tenant_id !== auth()->user()->tenant_id) {
                abort(403, 'Unauthorized.');
            }

            $this->isEditing = true;
            $this->editingProcess = $signingProcess;
            $this->documentId = $signingProcess->document_id;
            $this->documentMode = 'select';
            $this->signatureOrder = $signingProcess->signature_order;
            $this->customMessage = $signingProcess->custom_message;
            $this->deadlineAt = $signingProcess->deadline_at?->format('Y-m-d');

            // Load existing signers
            $this->signers = $signingProcess->signers()
                ->orderBy('order')
                ->get()
                ->map(fn ($s) => [
                    'name' => $s->name,
                    'email' => $s->email,
                    'phone' => $s->phone,
                ])
                ->toArray();

            return;
        }

        // Create mode
        $this->documentId = $documentId;

        // If document provided, use select mode
        if ($documentId) {
            $this->documentMode = 'select';
        }

        // Start with one empty signer
        $this->addSigner();
    }

    /**
     * Handle file upload.
     */
    public function updatedUploadedFile(): void
    {
        $this->validateOnly('uploadedFile');

        if ($this->uploadedFile) {
            $this->uploadingFile = true;
            $this->error = null;

            try {
                $uploadService = app(DocumentUploadService::class);
                $document = $uploadService->upload(
                    $this->uploadedFile,
                    auth()->user()
                );

                $this->documentId = $document->id;
                $this->documentMode = 'select';
                $this->uploadedFile = null;
                $this->success = 'Document uploaded successfully!';

            } catch (\Exception $e) {
                $this->error = 'Failed to upload document: '.$e->getMessage();
                Log::error('Document upload failed in signing process', [
                    'error' => $e->getMessage(),
                    'user_id' => auth()->id(),
                ]);
            }

            $this->uploadingFile = false;
        }
    }

    /**
     * Switch document mode.
     */
    public function setDocumentMode(string $mode): void
    {
        $this->documentMode = $mode;
        if ($mode === 'upload') {
            $this->documentId = null;
        }
        $this->error = null;
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
     * Move signer up in order.
     */
    public function moveSignerUp(int $index): void
    {
        if ($index <= 0) {
            return;
        }

        $temp = $this->signers[$index - 1];
        $this->signers[$index - 1] = $this->signers[$index];
        $this->signers[$index] = $temp;
    }

    /**
     * Move signer down in order.
     */
    public function moveSignerDown(int $index): void
    {
        if ($index >= count($this->signers) - 1) {
            return;
        }

        $temp = $this->signers[$index + 1];
        $this->signers[$index + 1] = $this->signers[$index];
        $this->signers[$index] = $temp;
    }

    /**
     * Reorder signers from drag & drop.
     */
    public function reorderSigners(array $order): void
    {
        $newSigners = [];
        foreach ($order as $index) {
            if (isset($this->signers[$index])) {
                $newSigners[] = $this->signers[$index];
            }
        }
        $this->signers = $newSigners;
    }

    /**
     * Validate signers array.
     */
    /**
     * Crea la plantilla si se ha pedido al vuelo.
     *
     * Nunca hace fallar el envio: el proceso de firma ya esta creado, y no
     * poder guardarlo ademas como plantilla es un contratiempo, no un motivo
     * para tirar todo atras.
     */
    private function maybeCreateTemplate(Document $document): ?string
    {
        if (! $this->alsoSaveAsTemplate || $this->isEditing) {
            return null;
        }

        try {
            $template = app(TemplateVersionService::class)->createFromDocument(
                $document,
                auth()->user(),
                pathinfo($document->original_filename, PATHINFO_FILENAME),
            );

            return $template->uuid;
        } catch (\Throwable $e) {
            Log::warning('No se pudo guardar el proceso como plantilla', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Validate the whole form in a single pass.
     *
     * Se valida todo de una vez, y no campo a campo, para que el formulario
     * muestre todos sus errores juntos en lugar de destaparlos de uno en uno.
     *
     * @throws ValidationException
     */
    protected function validateForm(): void
    {
        // Normaliza antes de validar: si no, un email con un espacio final
        // se rechaza por la regla 'email' aunque luego se recorte al guardar.
        $this->signers = array_map(fn (array $signer): array => [
            'name' => trim($signer['name']),
            'email' => trim(strtolower($signer['email'])),
            'phone' => trim($signer['phone'] ?? ''),
        ], $this->signers);

        $rules = [
            'documentId' => 'required|exists:documents,id',
            'signatureOrder' => 'required|in:sequential,parallel',
            'customMessage' => 'nullable|string|max:500',
            'deadlineAt' => 'nullable|date|after:today',
        ];

        $messages = [
            'documentId.required' => 'Please select or upload a document.',
            'documentId.exists' => 'Please select or upload a document.',
        ];

        foreach (array_keys($this->signers) as $index) {
            $rules["signers.{$index}.name"] = 'required|string|min:2|max:255';
            $rules["signers.{$index}.email"] = 'required|email|max:255';
            $rules["signers.{$index}.phone"] = 'nullable|string|max:20';

            $messages["signers.{$index}.name.required"] = 'Signer name is required';
            $messages["signers.{$index}.name.min"] = 'Signer name must be at least 2 characters';
            $messages["signers.{$index}.email.required"] = 'Signer email is required';
            $messages["signers.{$index}.email.email"] = 'Signer email must be valid';
        }

        $this->validate($rules, $messages);

        // Reglas que no se expresan como reglas de campo
        $extra = [];

        if (count($this->signers) < 1) {
            $extra['signers'][] = 'At least one signer is required.';
        }

        if (count($this->signers) > 10) {
            $extra['signers'][] = 'Maximum 10 signers allowed.';
        }

        $emails = array_column($this->signers, 'email');
        $duplicates = array_filter(array_count_values($emails), fn ($count) => $count > 1);

        if (! empty($duplicates)) {
            $extra['signers'][] = 'Duplicate email addresses are not allowed in the same process.';
        }

        if (! empty($extra)) {
            throw ValidationException::withMessages($extra);
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
            $this->validateForm();

            // Get the document
            $document = Document::query()
                ->where('id', $this->documentId)
                ->where('tenant_id', auth()->user()->tenant_id)
                ->ready()
                ->firstOrFail();

            // Create or update process in transaction
            DB::beginTransaction();

            try {
                if ($this->isEditing && $this->editingProcess) {
                    // Update existing process
                    $process = $this->editingProcess;
                    $process->update([
                        'document_id' => $document->id,
                        'signature_order' => $this->signatureOrder,
                        'custom_message' => $this->customMessage,
                        'deadline_at' => $this->deadlineAt ? now()->parse($this->deadlineAt) : null,
                    ]);

                    // Delete existing signers and recreate
                    $process->signers()->delete();
                } else {
                    // Create new signing process
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
                }

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

                // Register in audit trail.
                //
                // record() y no logEvent(): logEvent solo escribe en
                // laravel.log, que no es prueba. La creacion de un proceso de
                // firma tiene que quedar en la cadena encadenada por hash.
                $auditService->record(
                    $process,
                    $this->isEditing ? 'signing_process.updated' : 'signing_process.created',
                    [
                        'process_id' => $process->id,
                        'process_uuid' => $process->uuid,
                        'document_id' => $document->id,
                        'document_name' => $document->original_filename,
                        'signers_count' => count($this->signers),
                        'signature_order' => $this->signatureOrder,
                        'has_deadline' => $this->deadlineAt !== null,
                    ]
                );

                DB::commit();

                // Send notifications to signers (outside transaction)
                try {
                    $process->sendNotifications();
                } catch (\Exception $e) {
                    Log::error('Failed to send signing notifications', [
                        'process_id' => $process->id,
                        'error' => $e->getMessage(),
                    ]);
                    // Don't fail the creation, notifications can be resent
                }

                $this->createdTemplateUuid = $this->maybeCreateTemplate($document);

                $this->success = $this->isEditing
                    ? 'Signing process updated and sent successfully!'
                    : 'Signing process created successfully!';
                $this->createdProcessUuid = $process->uuid;

                // Dispatch event
                $this->dispatch('process-created', processUuid: $process->uuid);

                // Redirect to processes list
                return $this->redirect(route('signing-processes.index'), navigate: true);

            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (ValidationException $e) {
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
     * Save the signing process as draft without sending notifications.
     */
    public function saveAsDraft(AuditTrailService $auditService)
    {
        $this->creating = true;
        $this->error = null;
        $this->success = null;

        try {
            $this->validateForm();

            // Get the document
            $document = Document::query()
                ->where('id', $this->documentId)
                ->where('tenant_id', auth()->user()->tenant_id)
                ->ready()
                ->firstOrFail();

            // Create or update process in transaction
            DB::beginTransaction();

            try {
                if ($this->isEditing && $this->editingProcess) {
                    // Update existing draft
                    $process = $this->editingProcess;
                    $process->update([
                        'document_id' => $document->id,
                        'signature_order' => $this->signatureOrder,
                        'custom_message' => $this->customMessage,
                        'deadline_at' => $this->deadlineAt ? now()->parse($this->deadlineAt) : null,
                    ]);

                    // Delete existing signers and recreate
                    $process->signers()->delete();
                } else {
                    // Create signing process as draft
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
                }

                // Create signers (without sending notifications)
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

                // Register in audit trail (ver la nota en create()).
                $auditService->record(
                    $process,
                    $this->isEditing ? 'signing_process.draft_updated' : 'signing_process.draft_saved',
                    [
                        'process_id' => $process->id,
                        'process_uuid' => $process->uuid,
                        'document_id' => $document->id,
                        'document_name' => $document->original_filename,
                        'signers_count' => count($this->signers),
                        'signature_order' => $this->signatureOrder,
                    ]
                );

                DB::commit();

                $this->success = __('Draft saved successfully! You can send it later.');
                $this->createdProcessUuid = $process->uuid;

                // Redirect to processes list
                return $this->redirect(route('signing-processes.index'), navigate: true);

            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (ValidationException $e) {
            $this->creating = false;
            throw $e;
        } catch (\Exception $e) {
            $this->error = __('Failed to save draft. Please try again.');

            Log::error('Failed to save signing process draft', [
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
