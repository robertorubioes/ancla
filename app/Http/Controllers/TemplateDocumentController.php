<?php

namespace App\Http\Controllers;

use App\Livewire\Template\TemplateFill;
use App\Models\DocumentTemplateVersion;
use App\Services\Document\DocumentUploadService;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sirve el PDF base de una version de plantilla, para que el editor lo pinte.
 *
 * No se expone el documento por su propia ruta: se sirve a traves de la
 * version, de modo que el acceso depende del tenant de la plantilla y no de
 * quien subiera el PDF.
 */
class TemplateDocumentController extends Controller
{
    public function __construct(
        private readonly DocumentUploadService $documents,
    ) {}

    public function show(DocumentTemplateVersion $version): Response
    {
        $user = auth()->user();

        // El scope global de tenant ya filtra, pero la comprobacion explicita
        // deja el fallo en 403 en lugar de en un 404 confuso, y protege si
        // alguna ruta futura resuelve la version sin scope.
        if ($user === null || $version->tenant_id !== $user->tenant_id) {
            abort(403, 'Esta plantilla no pertenece a tu organizacion.');
        }

        $document = $version->document;

        if ($document === null) {
            abort(404, 'La version no tiene documento base.');
        }

        $content = $this->documents->getDecryptedContent($document);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="plantilla.pdf"',
            // El editor lo recarga al cambiar de version; no interesa cachear
            // un PDF que el usuario acaba de sustituir.
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Sirve una vista previa recien generada.
     *
     * El PDF vive en cache y caduca solo: es un borrador de lo que se esta
     * rellenando, no un documento del sistema. La clave es aleatoria y ademas
     * se comprueba de quien es, para que no baste con adivinarla.
     */
    public function preview(string $key): Response
    {
        $entry = Cache::get(TemplateFill::previewCacheKey($key));

        if (! is_array($entry) || ! isset($entry['pdf'], $entry['user_id'])) {
            abort(404, 'La vista previa ha caducado. Vuelve a generarla.');
        }

        if ($entry['user_id'] !== auth()->id()) {
            abort(403);
        }

        return response((string) $entry['pdf'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="vista-previa.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
