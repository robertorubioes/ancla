<?php

declare(strict_types=1);

namespace Tests\Feature\Document;

use App\Models\Document;
use App\Models\EvidencePackage;
use App\Models\Signer;
use App\Models\SigningProcess;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Evidence\EvidenceDossierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Feature tests for promoter document downloads (final document, dossier, bundle).
 *
 * Tests requirements from E5-003 code review.
 */
class PromoterDownloadTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $promoter;

    private User $otherUser;

    private SigningProcess $completedProcess;

    private SigningProcess $pendingProcess;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        // Enable TSA mock mode for testing
        config(['evidence.tsa.mock' => true]);

        // Create tenant
        $this->tenant = Tenant::factory()->create();

        // Create promoter (process creator)
        $this->promoter = User::factory()->create(['tenant_id' => $this->tenant->id]);

        // Create another user in same tenant
        $this->otherUser = User::factory()->create(['tenant_id' => $this->tenant->id]);

        // Create completed signing process with final document
        $this->completedProcess = $this->createCompletedProcess();

        // Create pending signing process (not completed)
        $this->pendingProcess = $this->createPendingProcess();

        // Mock EvidenceDossierService for dossier generation
        $this->mockDossierService();
    }

    /**
     * Mock the EvidenceDossierService.
     */
    private function mockDossierService(): void
    {
        $mock = $this->mock(EvidenceDossierService::class);
        $mock->shouldReceive('generateDossier')
            ->andReturn('%PDF-1.7 Mock Evidence Dossier');
    }

    /**
     * Test promoter can download final document.
     *
     * @test
     */
    public function test_promoter_can_download_final_document(): void
    {
        $response = $this->actingAs($this->promoter)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->get(route('signing-processes.download-document', $this->completedProcess->uuid));

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="'.$this->completedProcess->final_document_name.'"');

        $this->assertNotEmpty($response->getContent());
    }

    /**
     * Test non-creator cannot download document (403).
     *
     * @test
     */
    public function test_non_creator_cannot_download_document(): void
    {
        $response = $this->actingAs($this->otherUser)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->get(route('signing-processes.download-document', $this->completedProcess->uuid));

        $response->assertStatus(403);
    }

    /**
     * Test download requires completed process.
     *
     * @test
     */
    public function test_download_requires_completed_process(): void
    {
        $response = $this->actingAs($this->promoter)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->get(route('signing-processes.download-document', $this->pendingProcess->uuid));

        $response->assertStatus(404);
    }

    /**
     * Test promoter can download evidence dossier.
     *
     * @test
     */
    public function test_promoter_can_download_dossier(): void
    {
        $response = $this->actingAs($this->promoter)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->get(route('signing-processes.download-dossier', $this->completedProcess->uuid));

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="evidence_dossier_'.$this->completedProcess->uuid.'.pdf"');

        $this->assertNotEmpty($response->getContent());
    }

    /**
     * Test dossier download requires completed process.
     *
     * @test
     */
    public function test_dossier_requires_completed_process(): void
    {
        $response = $this->actingAs($this->promoter)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->get(route('signing-processes.download-dossier', $this->pendingProcess->uuid));

        $response->assertStatus(404);
    }

    /**
     * Test promoter can download bundle (ZIP with both files).
     *
     * @test
     */
    public function test_promoter_can_download_bundle(): void
    {
        $response = $this->actingAs($this->promoter)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->get(route('signing-processes.download-bundle', $this->completedProcess->uuid));

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/zip')
            ->assertHeader('Content-Disposition', 'attachment; filename="signed_bundle_'.$this->completedProcess->uuid.'.zip"');

        $this->assertNotEmpty($response->getContent());
    }

    /**
     * Test bundle contains both final document and evidence dossier.
     *
     * @test
     */
    public function test_bundle_contains_both_files(): void
    {
        $response = $this->actingAs($this->promoter)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->get(route('signing-processes.download-bundle', $this->completedProcess->uuid));

        $response->assertStatus(200);

        // Save ZIP to temporary file for inspection
        $zipContent = $response->getContent();
        $tempZipPath = sys_get_temp_dir().'/test_bundle_'.uniqid().'.zip';
        file_put_contents($tempZipPath, $zipContent);

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($tempZipPath) === true);

        // Check that both files are present
        $this->assertNotFalse($zip->locateName($this->completedProcess->final_document_name));
        $this->assertNotFalse($zip->locateName('evidence_dossier_'.$this->completedProcess->uuid.'.pdf'));

        $zip->close();
        @unlink($tempZipPath);
    }

    /**
     * Test ZIP cleanup happens on error.
     *
     * @test
     */
    public function test_zip_cleanup_on_error(): void
    {
        // Create a process with missing final document to trigger error
        $brokenProcess = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->promoter->id,
            'status' => SigningProcess::STATUS_COMPLETED,
            'completed_at' => now(),
            'final_document_path' => 'nonexistent/file.pdf', // This will cause error
            'final_document_name' => 'test.pdf',
        ]);

        $tempDir = storage_path('app/temp');

        // Get initial file count in temp directory
        $filesBefore = is_dir($tempDir) ? count(glob($tempDir.'/bundle_*.zip')) : 0;

        $response = $this->actingAs($this->promoter)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->get(route('signing-processes.download-bundle', $brokenProcess->uuid));

        $response->assertStatus(500);

        // Verify temp files are cleaned up
        $filesAfter = is_dir($tempDir) ? count(glob($tempDir.'/bundle_*.zip')) : 0;
        $this->assertEquals($filesBefore, $filesAfter, 'Temporary ZIP files should be cleaned up on error');
    }

    /**
     * Test tenant isolation on downloads.
     *
     * @test
     */
    public function test_tenant_isolation_on_downloads(): void
    {
        // Create another tenant with a completed process
        $otherTenant = Tenant::factory()->create();
        $otherTenantUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherTenantProcess = $this->createCompletedProcessForUser($otherTenantUser);

        // Try to download other tenant's document (should fail with 403 or 404)
        $response = $this->actingAs($this->promoter)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->get(route('signing-processes.download-document', $otherTenantProcess->uuid));

        $this->assertContains($response->status(), [403, 404], 'Should deny access to other tenant documents');

        // Try to download other tenant's dossier
        $response = $this->actingAs($this->promoter)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->get(route('signing-processes.download-dossier', $otherTenantProcess->uuid));

        $this->assertContains($response->status(), [403, 404]);

        // Try to download other tenant's bundle
        $response = $this->actingAs($this->promoter)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->get(route('signing-processes.download-bundle', $otherTenantProcess->uuid));

        $this->assertContains($response->status(), [403, 404]);
    }

    /**
     * Helper: Create a completed signing process with final document.
     */
    private function createCompletedProcess(): SigningProcess
    {
        return $this->createCompletedProcessForUser($this->promoter);
    }

    /**
     * Helper: Create a completed signing process for specific user.
     */
    private function createCompletedProcessForUser(User $user): SigningProcess
    {
        $document = Document::factory()->ready()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
        ]);

        // Create fake final document
        $finalDocPath = 'final_documents/test_'.uniqid().'.pdf';
        $finalDocContent = '%PDF-1.7 Test Final Document';
        Storage::disk('local')->put($finalDocPath, $finalDocContent);

        $process = SigningProcess::factory()->create([
            'tenant_id' => $user->tenant_id,
            'document_id' => $document->id,
            'created_by' => $user->id,
            'status' => SigningProcess::STATUS_COMPLETED,
            'completed_at' => now(),
            'final_document_path' => $finalDocPath,
            'final_document_name' => 'signed_document.pdf',
            'final_document_hash' => hash('sha256', $finalDocContent),
            'final_document_size' => strlen($finalDocContent),
        ]);

        // Create signers with evidence packages
        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SIGNED,
            'signed_at' => now(),
        ]);

        // Create evidence package for the signer (using polymorphic relation)
        EvidencePackage::factory()->create([
            'tenant_id' => $user->tenant_id,
            'packagable_type' => Signer::class,
            'packagable_id' => $signer->id,
        ]);

        return $process;
    }

    /**
     * Helper: Create a pending (not completed) signing process.
     */
    private function createPendingProcess(): SigningProcess
    {
        $document = Document::factory()->ready()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->promoter->id,
        ]);

        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $document->id,
            'created_by' => $this->promoter->id,
            'status' => SigningProcess::STATUS_IN_PROGRESS,
        ]);

        Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SENT,
        ]);

        return $process;
    }
}
