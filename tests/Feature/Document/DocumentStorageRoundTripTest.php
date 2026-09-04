<?php

declare(strict_types=1);

namespace Tests\Feature\Document;

use App\Models\Document;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Document\DocumentUploadService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Un documento que se guarda tiene que poder volver a leerse.
 *
 * Parece obvio, y sin embargo no se cumplia: encryptAndStore() escribe el
 * fichero en storage_path -que ya incluye el nombre- y getDecryptedContent()
 * le concatenaba ademas stored_filename, construyendo una ruta inexistente.
 * Ni la previsualizacion, ni la descarga, ni la verificacion de integridad
 * podian leer un documento real.
 *
 * Los tests que habia usaban modelos de factory con la convencion contraria,
 * asi que nunca ejercitaron la pareja escritor/lector.
 */
class DocumentStorageRoundTripTest extends TestCase
{
    use RefreshDatabase;

    private DocumentUploadService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);

        $this->user = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->service = app(DocumentUploadService::class);
    }

    private function pdf(string $texto): string
    {
        $pdf = new \FPDF;
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Cell(0, 10, $texto, 0, 1);

        return $pdf->Output('S');
    }

    private function storedDocument(string $contenido): Document
    {
        $document = Document::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'user_id' => $this->user->id,
        ]);

        $temp = tempnam(sys_get_temp_dir(), 'rt').'.pdf';
        file_put_contents($temp, $contenido);

        $resultado = $this->service->encryptAndStore(
            new UploadedFile($temp, 'documento.pdf', 'application/pdf', null, true),
            $document,
        );

        $document->update([
            'storage_disk' => $resultado['disk'],
            'storage_path' => $resultado['path'],
            'stored_filename' => $resultado['filename'],
            'is_encrypted' => $resultado['encrypted'],
        ]);

        @unlink($temp);

        return $document->refresh();
    }

    public function test_el_fichero_esta_exactamente_en_storage_path(): void
    {
        $document = $this->storedDocument($this->pdf('CONTENIDO'));

        $this->assertTrue(
            Storage::disk($document->storage_disk)->exists($document->storage_path),
            'storage_path debe ser la ruta completa del fichero.'
        );

        $this->assertTrue($document->existsInStorage());
    }

    public function test_lo_guardado_se_vuelve_a_leer_byte_a_byte(): void
    {
        $contenido = $this->pdf('CONTENIDO ORIGINAL');

        $document = $this->storedDocument($contenido);

        $this->assertSame($contenido, $this->service->getDecryptedContent($document));
    }

    public function test_no_se_concatena_el_nombre_dos_veces(): void
    {
        // La forma exacta del fallo: storage_path + '/' + stored_filename.
        $document = $this->storedDocument($this->pdf('X'));

        $rutaDuplicada = $document->storage_path.'/'.$document->stored_filename;

        $this->assertFalse(
            Storage::disk($document->storage_disk)->exists($rutaDuplicada),
            'Esa ruta no existe: componerla asi es el bug.'
        );
    }

    public function test_borrar_elimina_el_fichero(): void
    {
        $document = $this->storedDocument($this->pdf('X'));
        $ruta = $document->storage_path;

        $this->service->forceDelete($document);

        $this->assertFalse(Storage::disk($document->storage_disk)->exists($ruta));
    }
}
