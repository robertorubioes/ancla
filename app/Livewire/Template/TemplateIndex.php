<?php

namespace App\Livewire\Template;

use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Services\Template\TemplateException;
use App\Services\Template\TemplateVersionService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
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
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    /** Dialogo de conversion de documento a plantilla */
    public bool $showCreate = false;

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
        $this->reset(['sourceDocumentId', 'newName', 'newDescription', 'error']);
        $this->showCreate = true;
    }

    public function closeCreate(): void
    {
        $this->showCreate = false;
    }

    /**
     * Al elegir el documento se propone su nombre, que casi siempre sirve.
     */
    public function updatedSourceDocumentId(mixed $value): void
    {
        if (blank($value) || filled($this->newName)) {
            return;
        }

        $document = $this->availableDocuments()->firstWhere('id', (int) $value);

        if ($document !== null) {
            $this->newName = pathinfo($document->original_filename, PATHINFO_FILENAME);
        }
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
     * Documentos listos que todavia no son plantilla.
     *
     * @return Collection<int, Document>
     */
    public function availableDocuments(): Collection
    {
        return Document::query()
            ->where('status', Document::STATUS_READY)
            ->whereNotIn(
                'id',
                DocumentTemplate::query()
                    ->join('document_template_versions as v', 'v.document_template_id', '=', 'document_templates.id')
                    ->select('v.document_id')
            )
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
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
            'documents' => $this->showCreate ? $this->availableDocuments() : collect(),
        ]);
    }
}
