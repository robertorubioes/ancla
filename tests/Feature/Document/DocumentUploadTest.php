<?php

declare(strict_types=1);

namespace Tests\Feature\Document;

use App\Models\Document;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Feature tests for document upload flow.
 */
class DocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        // Enable TSA mock mode for testing without external dependencies
        config(['evidence.tsa.mock' => true]);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    /**
     * Test authenticated user can access documents index.
     */
    public function test_authenticated_user_can_access_documents_index(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson('/documents');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);
    }

    /**
     * Test guest cannot access documents.
     */
    public function test_guest_cannot_access_documents(): void
    {
        $response = $this->get('/documents');

        $response->assertRedirect('/login');
    }

    /**
     * Test user can view own document.
     */
    public function test_user_can_view_own_document(): void
    {
        $document = Document::factory()->ready()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson("/documents/{$document->uuid}");

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['uuid', 'name']]);
    }

    /**
     * Test user cannot view another user's document.
     */
    public function test_user_cannot_view_another_users_document(): void
    {
        $otherUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $document = Document::factory()->ready()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson("/documents/{$document->uuid}");

        $response->assertStatus(403);
    }

    /**
     * Test user can upload valid PDF document via API.
     */
    public function test_user_can_upload_valid_pdf_via_api(): void
    {
        $file = $this->createValidPdfFile();

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson('/documents', [
                'file' => $file,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'uuid',
                    'name',
                    'size',
                    'hash',
                    'created_at',
                ],
            ]);

        $this->assertDatabaseHas('documents', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'status' => 'ready',
        ]);
    }

    /**
     * Test upload rejects non-PDF file.
     */
    public function test_upload_rejects_non_pdf_file(): void
    {
        $file = UploadedFile::fake()->create('document.txt', 100, 'text/plain');

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson('/documents', [
                'file' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    /**
     * Test upload rejects file exceeding max size.
     */
    public function test_upload_rejects_file_exceeding_max_size(): void
    {
        // Create a file larger than max size (default 50MB)
        $file = UploadedFile::fake()->create('large.pdf', 51 * 1024, 'application/pdf');

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson('/documents', [
                'file' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    /**
     * Test upload requires file field.
     */
    public function test_upload_requires_file(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson('/documents', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    /**
     * Test user can delete own document.
     */
    public function test_user_can_delete_own_document(): void
    {
        $document = Document::factory()->ready()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->deleteJson("/documents/{$document->uuid}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertSoftDeleted('documents', ['id' => $document->id]);
    }

    /**
     * Test user cannot delete another user's document.
     */
    public function test_user_cannot_delete_another_users_document(): void
    {
        $otherUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $document = Document::factory()->ready()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->deleteJson("/documents/{$document->uuid}");

        $response->assertStatus(403);
    }

    /**
     * Test document integrity verification via API.
     */
    public function test_document_integrity_verification(): void
    {
        $content = '%PDF-1.7 test content';
        $hash = hash('sha256', $content);

        $document = Document::factory()->ready()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'sha256_hash' => $hash,
            'storage_disk' => 'local',
            'storage_path' => 'test/doc.pdf',
            'is_encrypted' => false,
        ]);

        Storage::disk('local')->put('test/doc.pdf', $content);

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson("/documents/{$document->uuid}/verify");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'integrity_valid' => true,
                ],
            ]);
    }

    /**
     * Test documents list only shows user's documents.
     */
    public function test_documents_list_only_shows_users_documents(): void
    {
        // Create documents for current user
        $userDocuments = Document::factory()->count(3)->ready()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        // Create documents for another user
        $otherUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
        Document::factory()->count(2)->ready()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson('/documents');

        $response->assertStatus(200);
        $data = $response->json('data.data');

        $this->assertCount(3, $data);
    }

    /**
     * Test document search by filename.
     */
    public function test_document_search_by_filename(): void
    {
        Document::factory()->ready()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'original_filename' => 'contract-2024.pdf',
        ]);

        Document::factory()->ready()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'original_filename' => 'invoice-2024.pdf',
        ]);

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson('/documents?search=contract');

        $response->assertStatus(200);
        $data = $response->json('data.data');

        $this->assertCount(1, $data);
        $this->assertStringContainsString('contract', $data[0]['original_filename']);
    }

    /**
     * Test multi-tenant isolation.
     */
    public function test_multi_tenant_document_isolation(): void
    {
        // Create another tenant with documents
        $otherTenant = Tenant::factory()->create();
        $otherTenantUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        Document::factory()->count(3)->ready()->create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $otherTenantUser->id,
        ]);

        // Create documents for current tenant
        Document::factory()->count(2)->ready()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson('/documents');

        $response->assertStatus(200);
        $data = $response->json('data.data');

        // Should only see documents from own tenant
        $this->assertCount(2, $data);
    }

    /**
     * Test document show returns correct data structure.
     */
    public function test_document_show_returns_correct_structure(): void
    {
        $document = Document::factory()->ready()->withThumbnail()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson("/documents/{$document->uuid}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'uuid',
                    'name',
                    'size',
                    'formatted_size',
                    'pages',
                    'hash',
                    'status',
                    'metadata',
                    'pdf_version',
                    'is_pdf_a',
                    'has_signatures',
                    'has_javascript',
                    'thumbnail_url',
                    'download_url',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    /**
     * Test recently uploaded documents appear in list.
     */
    public function test_recently_uploaded_documents_appear_in_list(): void
    {
        // Create old documents
        Document::factory()->ready()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'created_at' => now()->subDays(60),
        ]);

        // Create recent documents
        $recentDocs = Document::factory()->count(2)->ready()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'created_at' => now()->subDays(5),
        ]);

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson('/documents');

        $response->assertStatus(200);
        $data = $response->json('data.data');

        // Should see all 3 documents
        $this->assertCount(3, $data);
    }

    /**
     * Helper to create a valid PDF file for testing.
     */
    private function createValidPdfFile(): UploadedFile
    {
        $pdfContent = '%PDF-1.7
1 0 obj
<< /Type /Catalog /Pages 2 0 R >>
endobj
2 0 obj
<< /Type /Pages /Kids [3 0 R] /Count 1 >>
endobj
3 0 obj
<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>
endobj
xref
0 4
trailer
<< /Size 4 /Root 1 0 R >>
startxref
%%EOF';

        $tempPath = tempnam(sys_get_temp_dir(), 'test_pdf_');
        file_put_contents($tempPath, $pdfContent);

        return new UploadedFile(
            $tempPath,
            'test-document.pdf',
            'application/pdf',
            null,
            true
        );
    }
}
