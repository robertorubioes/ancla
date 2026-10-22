<?php

namespace App\Livewire\Template;

use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Services\Document\DocumentUploadException;
use App\Services\Document\DocumentUploadService;
use App\Services\Template\TemplateException;
use App\Services\Template\TemplateVersionService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Listado de plantillas y punto de entrada de su ciclo de vida.
 *
 * Aqui se convierte un documento ya subido en plantilla, se habilita, se
 * retira y se abre una version nueva para editarla. Las decisiones las toma
 * TemplateVersionService; este componente solo las expone.
 *
 * @see docs/architecture/adr-011-plantillas-y-api-de-cumplimentacion.md
 */
#[Layout('components.layouts.app')]
class TemplateIndex extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    /** Dialogo de creacion de plantilla */
    public bool $showCreate = false;

    /** @var mixed */
    public $uploadedFile = null;

    public bool $uploading = false;

    public ?int $sourceDocumentId = null;

    public string $newName = '';

    public string $newDescription = '';

    public string $error = '';

    public string $success = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->reset(['sourceDocumentId', 'uploadedFile', 'newName', 'newDescription', 'error']);
        $this->showCreate = true;
    }

    /**
     * Sube el PDF base y lo deja elegido.
     *
     * Se apoya en DocumentUploadService, el mismo que usa la creacion de
     * procesos: valida el PDF, lo cifra y lo sella. Una plantilla no merece
     * un camino de subida propio.
     */
    public function updatedUploadedFile(): void
    {
        if (! $this->uploadedFile) {
            return;
        }

        $this->error = '';

        $this->validate([
            'uploadedFile' => ['required', 'file', 'mimes:pdf', 'max:51200'],
        ], [], ['uploadedFile' => 'documento']);

        $this->uploading = true;

        try {
            $document = app(DocumentUploadService::class)->upload(
                $this->uploadedFile,
                auth()->user(),
            );
        } catch (DocumentUploadException $e) {
            $this->error = $e->getMessage();
            $this->uploadedFile = null;
            $this->uploading = false;

            return;
        } catch (\Throwable $e) {
            report($e);
            $this->error = 'No se pudo subir el documento.';
            $this->uploadedFile = null;
            $this->uploading = false;

            return;
        }

        $this->sourceDocumentId = $document->id;

        if (blank($this->newName)) {
            $this->newName = pathinfo($document->original_filename, PATHINFO_FILENAME);
        }

        $this->uploadedFile = null;
        $this->uploading = false;
    }

    public function closeCreate(): void
    {
        $this->showCreate = false;
    }

    /**
     * Convierte un documento ya subido en una plantilla en borrador.
     */
    public function createFromDocument(TemplateVersionService $versions): void
    {
        $this->error = '';

        $this->validate([
            'sourceDocumentId' => ['required', 'integer'],
            'newName' => ['required', 'string', 'max:255'],
            'newDescription' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'sourceDocumentId' => 'documento',
            'newName' => 'nombre',
        ]);

        $document = Document::query()->find($this->sourceDocumentId);

        if ($document === null) {
            $this->error = 'Ese documento ya no esta disponible.';

            return;
        }

        try {
            $template = $versions->createFromDocument(
                $document,
                auth()->user(),
                trim($this->newName),
                filled($this->newDescription) ? trim($this->newDescription) : null,
            );
        } catch (TemplateException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->showCreate = false;

        // Recien creada no tiene campos: lo util es ir directo al editor.
        $this->redirectRoute('templates.editor', ['template' => $template->uuid], navigate: true);
    }

    /**
     * Habilita la plantilla para poder usarla.
     */
    public function enable(string $uuid, TemplateVersionService $versions): void
    {
        $this->error = '';
        $this->success = '';

        $template = $this->findTemplate($uuid);

        if ($template === null) {
            return;
        }

        try {
            $versions->publish($template, auth()->user());
        } catch (TemplateException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->success = "«{$template->name}» ya se puede usar.";
    }

    /**
     * Abre una version nueva en borrador y lleva al editor.
     */
    public function edit(string $uuid, TemplateVersionService $versions): void
    {
        $this->error = '';

        $template = $this->findTemplate($uuid);

        if ($template === null) {
            return;
        }

        try {
            $versions->openNewDraft($template, auth()->user());
        } catch (TemplateException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->redirectRoute('templates.editor', ['template' => $template->uuid], navigate: true);
    }

    public function archive(string $uuid, TemplateVersionService $versions): void
    {
        $this->error = '';
        $this->success = '';

        $template = $this->findTemplate($uuid);

        if ($template === null) {
            return;
        }

        $versions->archive($template);

        $this->success = "«{$template->name}» retirada.";
    }

    private function findTemplate(string $uuid): ?DocumentTemplate
    {
        $template = DocumentTemplate::query()->where('uuid', $uuid)->first();

        if ($template === null) {
            $this->error = 'Esa plantilla ya no existe.';
        }

        return $template;
    }

    /**
     * @return LengthAwarePaginator<int, DocumentTemplate>
     */
    public function templates(): LengthAwarePaginator
    {
        return DocumentTemplate::query()
            ->with(['currentVersion', 'creator'])
            ->withCount('versions')
            ->when(filled($this->search), fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->when(filled($this->statusFilter), fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('updated_at')
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.template.template-index', [
            'items' => $this->templates(),
        ]);
    }
}
