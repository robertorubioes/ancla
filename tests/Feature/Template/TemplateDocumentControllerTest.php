<?php

declare(strict_types=1);

namespace Tests\Feature\Template;

use App\Enums\UserRole;
use App\Http\Middleware\IdentifyTenant;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateVersion;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TemplateDocumentControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private DocumentTemplateVersion $version;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->tenant = Tenant::factory()->create();
        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => UserRole::ADMIN,
        ]);

        $this->version = $this->versionFor($this->tenant, 'CONTENIDO PDF');
    }

    private function versionFor(Tenant $tenant, string $content): DocumentTemplateVersion
    {
        $path = 'documents/test';
        $filename = Str::uuid().'.pdf';
        Storage::disk('local')->put("{$path}/{$filename}", $content);

        $document = Document::factory()->create([
            'tenant_id' => $tenant->id,
            'storage_disk' => 'local',
            'storage_path' => $path,
            'stored_filename' => $filename,
            'is_encrypted' => false,
        ]);

        $template = DocumentTemplate::factory()->create(['tenant_id' => $tenant->id]);

        return DocumentTemplateVersion::factory()->create([
            'tenant_id' => $tenant->id,
            'document_template_id' => $template->id,
            'document_id' => $document->id,
        ]);
    }

    private function fetch(User $user, DocumentTemplateVersion $version): TestResponse
    {
        return $this->actingAs($user)
            ->withoutMiddleware(IdentifyTenant::class)
            ->get(route('templates.pdf', ['version' => $version->uuid]));
    }

    public function test_sirve_el_pdf_base_al_administrador(): void
    {
        $response = $this->fetch($this->admin, $this->version);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame('CONTENIDO PDF', $response->getContent());
    }

    public function test_no_se_cachea(): void
    {
        // El PDF base puede sustituirse; un cache haria que el editor pintase
        // el documento anterior.
        $this->fetch($this->admin, $this->version)
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_no_sirve_el_pdf_de_otro_tenant(): void
    {
        // El scope global de tenant ya deberia filtrarla, pero aqui se resuelve
        // sin ese scope a proposito: comprueba que la verificacion explicita
        // del controlador sostiene el caso por si sola.
        $otro = Tenant::factory()->create();
        $ajena = $this->versionFor($otro, 'PDF AJENO');

        $this->fetch($this->admin, $ajena)->assertForbidden();
    }

    public function test_exige_autenticacion(): void
    {
        $this->withoutMiddleware(IdentifyTenant::class)
            ->get(route('templates.pdf', ['version' => $this->version->uuid]))
            ->assertRedirect();
    }

    public function test_un_operador_no_llega_al_pdf(): void
    {
        $operador = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => UserRole::OPERATOR,
        ]);

        $this->fetch($operador, $this->version)->assertForbidden();
    }
}
