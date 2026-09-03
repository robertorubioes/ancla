<?php

declare(strict_types=1);

namespace Tests\Feature\Signing;

use App\Livewire\Signing\SigningPage;
use App\Models\Document;
use App\Models\EvidencePackage;
use App\Models\OtpCode;
use App\Models\Signer;
use App\Models\SigningProcess;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Signing\SignatureException;
use App\Services\Signing\SignatureService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SignatureCreationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Document $document;

    private SigningProcess $process;

    private Signer $signer;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test tenant
        $this->tenant = Tenant::factory()->create();

        // Set tenant context
        app(TenantContext::class)->set($this->tenant);

        // Create test user
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Create test document
        $this->document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        // Create signing process
        $this->process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => 'sent',
            'signature_order' => 'parallel',
        ]);

        // Create signer
        $this->signer = Signer::factory()->create([
            'signing_process_id' => $this->process->id,
            'status' => 'sent',
            'order' => 1,
        ]);

        // Mark OTP as verified
        OtpCode::factory()->verified()->create([
            'signer_id' => $this->signer->id,
        ]);
    }

    /** @test */
    public function it_renders_signature_type_tabs(): void
    {
        // Los tabs solo aparecen tras leer el documento y verificar el OTP.
        // El OTP ya viene verificado del setUp.
        Livewire::test(SigningPage::class, ['token' => $this->signer->token])
            ->set('hasReadDocument', true)
            ->set('otpVerified', true)
            ->assertSee('Dibujar')
            ->assertSee('Escribir')
            ->assertSee('Subir');
    }

    /** @test */
    public function it_can_switch_signature_types(): void
    {
        Livewire::test(SigningPage::class, ['token' => $this->signer->token])
            ->call('setSignatureType', 'draw')
            ->assertSet('signatureType', 'draw')
            ->call('setSignatureType', 'type')
            ->assertSet('signatureType', 'type')
            ->call('setSignatureType', 'upload')
            ->assertSet('signatureType', 'upload');
    }

    /** @test */
    public function it_can_clear_signature_data(): void
    {
        Livewire::test(SigningPage::class, ['token' => $this->signer->token])
            ->set('signatureData', 'some-data')
            ->set('typedSignature', 'John Doe')
            ->call('clearSignature')
            ->assertSet('signatureData', null)
            ->assertSet('typedSignature', '');
    }

    /** @test */
    public function it_validates_canvas_signature_is_not_empty(): void
    {
        $service = app(SignatureService::class);

        $this->expectException(SignatureException::class);
        $this->expectExceptionMessage('Canvas signature cannot be empty');

        // Create a white canvas (empty)
        $image = imagecreate(400, 200);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $white);
        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);

        $base64 = base64_encode($imageData);
        $dataUrl = "data:image/png;base64,{$base64}";

        $service->processSignature(
            signer: $this->signer,
            type: 'draw',
            data: $dataUrl,
            consentGiven: true
        );
    }

    /** @test */
    public function it_validates_typed_signature_minimum_length(): void
    {
        $service = app(SignatureService::class);

        $this->expectException(SignatureException::class);
        $this->expectExceptionMessage('Typed signature must be at least 2 characters');

        $service->processSignature(
            signer: $this->signer,
            type: 'type',
            data: 'A',
            consentGiven: true
        );
    }

    /** @test */
    public function it_validates_typed_signature_maximum_length(): void
    {
        $service = app(SignatureService::class);

        $this->expectException(SignatureException::class);
        $this->expectExceptionMessage('Typed signature cannot exceed 100 characters');

        $service->processSignature(
            signer: $this->signer,
            type: 'type',
            data: str_repeat('A', 101),
            consentGiven: true
        );
    }

    /** @test */
    public function it_validates_uploaded_signature_format(): void
    {
        $service = app(SignatureService::class);

        $this->expectException(SignatureException::class);
        $this->expectExceptionMessage('Invalid image format');

        $service->processSignature(
            signer: $this->signer,
            type: 'upload',
            data: 'data:image/gif;base64,R0lGODlhAQABAAAAACw=',
            consentGiven: true
        );
    }

    /** @test */
    public function it_validates_uploaded_signature_size(): void
    {
        $service = app(SignatureService::class);

        // Un PNG blanco de 4000x4000 comprime muy por debajo de 2MB, asi que
        // no ejercitaba el limite. El tamano se comprueba antes que el formato,
        // de modo que basta con un payload de 3MB.
        $imageData = random_bytes(3 * 1024 * 1024);

        $base64 = base64_encode($imageData);
        $dataUrl = "data:image/png;base64,{$base64}";

        $this->expectException(SignatureException::class);
        $this->expectExceptionMessage('Image file size cannot exceed 2MB');

        $service->processSignature(
            signer: $this->signer,
            type: 'upload',
            data: $dataUrl,
            consentGiven: true
        );
    }

    /** @test */
    public function it_requires_consent_to_sign(): void
    {
        $service = app(SignatureService::class);

        $this->expectException(SignatureException::class);
        $this->expectExceptionMessage('Explicit consent is required');

        $service->processSignature(
            signer: $this->signer,
            type: 'draw',
            data: $this->createValidCanvasSignature(),
            consentGiven: false
        );
    }

    /** @test */
    public function it_requires_otp_verification_before_signing(): void
    {
        // Create a signer without OTP verification
        $unverifiedSigner = Signer::factory()->create([
            'signing_process_id' => $this->process->id,
            'status' => 'sent',
            'order' => 2,
        ]);

        $service = app(SignatureService::class);

        $this->expectException(SignatureException::class);
        $this->expectExceptionMessage('OTP verification is required');

        $service->processSignature(
            signer: $unverifiedSigner,
            type: 'draw',
            data: $this->createValidCanvasSignature(),
            consentGiven: true
        );
    }

    /** @test */
    public function it_successfully_processes_draw_signature(): void
    {
        $service = app(SignatureService::class);

        $result = $service->processSignature(
            signer: $this->signer,
            type: 'draw',
            data: $this->createValidCanvasSignature(),
            consentGiven: true
        );

        $this->assertTrue($result->isSuccess());
        $this->assertDatabaseHas('signers', [
            'id' => $this->signer->id,
            'signature_type' => 'draw',
            'status' => 'signed',
        ]);
        $this->assertNotNull($this->signer->fresh()->signed_at);
        $this->assertNotNull($this->signer->fresh()->evidence_package_id);
    }

    /** @test */
    public function it_successfully_processes_type_signature(): void
    {
        $service = app(SignatureService::class);

        $result = $service->processSignature(
            signer: $this->signer,
            type: 'type',
            data: 'John Doe',
            consentGiven: true
        );

        $this->assertTrue($result->isSuccess());
        $this->assertDatabaseHas('signers', [
            'id' => $this->signer->id,
            'signature_type' => 'type',
            'signature_data' => 'John Doe',
            'status' => 'signed',
        ]);
    }

    /** @test */
    public function it_successfully_processes_upload_signature(): void
    {
        $service = app(SignatureService::class);

        $result = $service->processSignature(
            signer: $this->signer,
            type: 'upload',
            data: $this->createValidUploadedSignature(),
            consentGiven: true
        );

        $this->assertTrue($result->isSuccess());
        $this->assertDatabaseHas('signers', [
            'id' => $this->signer->id,
            'signature_type' => 'upload',
            'status' => 'signed',
        ]);
    }

    /** @test */
    public function it_captures_evidence_package_on_signature(): void
    {
        $service = app(SignatureService::class);

        $result = $service->processSignature(
            signer: $this->signer,
            type: 'draw',
            data: $this->createValidCanvasSignature(),
            consentGiven: true
        );

        $signer = $this->signer->fresh();
        $this->assertNotNull($signer->evidence_package_id);

        $evidencePackage = $signer->evidencePackage;
        $this->assertNotNull($evidencePackage);
        // El paquete es polimorfico y se ancla al firmante; no tiene columna
        // 'type', y los estados validos son los del enum del modelo.
        $this->assertEquals(Signer::class, $evidencePackage->packagable_type);
        $this->assertEquals($signer->id, $evidencePackage->packagable_id);
        $this->assertEquals(EvidencePackage::STATUS_READY, $evidencePackage->status);
        $this->assertNotNull($evidencePackage->generated_at);
        $this->assertNotNull($evidencePackage->tsa_token_id);
    }

    /** @test */
    public function it_creates_audit_trail_entry_on_signature(): void
    {
        $service = app(SignatureService::class);

        $service->processSignature(
            signer: $this->signer,
            type: 'draw',
            data: $this->createValidCanvasSignature(),
            consentGiven: true
        );

        $this->assertDatabaseHas('audit_trail_entries', [
            'auditable_type' => Signer::class,
            'auditable_id' => $this->signer->id,
            // La columna es event_type, no action.
            'event_type' => 'signer.signed',
        ]);
    }

    /** @test */
    public function it_updates_process_status_when_all_signers_complete(): void
    {
        $service = app(SignatureService::class);

        // Sign the document
        $service->processSignature(
            signer: $this->signer,
            type: 'draw',
            data: $this->createValidCanvasSignature(),
            consentGiven: true
        );

        $process = $this->process->fresh();
        $this->assertEquals('completed', $process->status);
        $this->assertNotNull($process->completed_at);
    }

    /** @test */
    public function it_does_not_complete_process_until_all_signers_sign(): void
    {
        // Add another signer
        $signer2 = Signer::factory()->create([
            'signing_process_id' => $this->process->id,
            'status' => 'sent',
            'order' => 2,
        ]);

        OtpCode::factory()->verified()->create([
            'signer_id' => $signer2->id,
        ]);

        $service = app(SignatureService::class);

        // First signer signs
        $service->processSignature(
            signer: $this->signer,
            type: 'draw',
            data: $this->createValidCanvasSignature(),
            consentGiven: true
        );

        // Process should not be completed yet
        $process = $this->process->fresh();
        $this->assertNotEquals('completed', $process->status);

        // Second signer signs
        $service->processSignature(
            signer: $signer2,
            type: 'draw',
            data: $this->createValidCanvasSignature(),
            consentGiven: true
        );

        // Now process should be completed
        $process = $this->process->fresh();
        $this->assertEquals('completed', $process->status);
    }

    /** @test */
    public function it_respects_multi_tenant_isolation(): void
    {
        // Create another tenant with its own data
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherDocument = Document::factory()->create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $otherUser->id,
        ]);

        $service = app(SignatureService::class);

        $service->processSignature(
            signer: $this->signer,
            type: 'draw',
            data: $this->createValidCanvasSignature(),
            consentGiven: true
        );

        $evidencePackage = $this->signer->fresh()->evidencePackage;
        $this->assertEquals($this->tenant->id, $evidencePackage->tenant_id);
        $this->assertNotEquals($otherTenant->id, $evidencePackage->tenant_id);
    }

    /** @test */
    public function livewire_component_sign_button_is_disabled_without_consent(): void
    {
        Livewire::test(SigningPage::class, ['token' => $this->signer->token])
            ->set('signatureData', $this->createValidCanvasSignature())
            ->set('consentGiven', false)
            ->assertSee('disabled');
    }

    /** @test */
    public function livewire_component_sign_button_is_disabled_without_signature(): void
    {
        Livewire::test(SigningPage::class, ['token' => $this->signer->token])
            ->set('signatureData', null)
            ->set('consentGiven', true)
            ->assertSee('disabled');
    }

    /** @test */
    public function livewire_component_can_sign_document_successfully(): void
    {
        Livewire::test(SigningPage::class, ['token' => $this->signer->token])
            ->set('hasReadDocument', true)
            ->set('otpVerified', true)
            ->set('signatureType', 'draw')
            ->set('signatureData', $this->createValidCanvasSignature())
            ->set('consentGiven', true)
            ->call('signDocument')
            ->assertSet('signatureError', false);

        $this->assertDatabaseHas('signers', [
            'id' => $this->signer->id,
            'status' => 'signed',
        ]);
    }

    /**
     * Helper: Create a valid canvas signature (PNG with some content).
     */
    private function createValidCanvasSignature(): string
    {
        $image = imagecreate(400, 200);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefill($image, 0, 0, $white);

        // Draw some lines to simulate signature
        imageline($image, 50, 100, 350, 100, $black);
        imageline($image, 50, 120, 350, 120, $black);
        imageline($image, 100, 80, 100, 140, $black);

        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);

        $base64 = base64_encode($imageData);

        return "data:image/png;base64,{$base64}";
    }

    /**
     * Helper: Create a valid uploaded signature.
     */
    private function createValidUploadedSignature(): string
    {
        return $this->createValidCanvasSignature();
    }
}
