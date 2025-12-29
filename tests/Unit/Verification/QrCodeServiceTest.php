<?php

declare(strict_types=1);

namespace Tests\Unit\Verification;

use App\Models\Document;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VerificationCode;
use App\Services\Verification\PublicVerificationService;
use App\Services\Verification\QrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QrCodeServiceTest extends TestCase
{
    use RefreshDatabase;

    private QrCodeService $service;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->service = new QrCodeService;

        // Use fake storage
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        // Ensure any open transactions are rolled back
        while (\DB::transactionLevel() > 0) {
            \DB::rollBack();
        }

        parent::tearDown();
    }

    /** @test */
    public function it_generates_verification_url(): void
    {
        $code = 'TESTCODE123';
        $url = $this->service->generateVerificationUrl($code);

        $this->assertStringContainsString('/verify/', $url);
        $this->assertStringContainsString($code, $url);
    }

    /** @test */
    public function it_generates_short_verification_url(): void
    {
        $shortCode = 'TEST12';
        $url = $this->service->generateShortVerificationUrl($shortCode);

        $this->assertStringContainsString('/v/', $url);
        $this->assertStringContainsString($shortCode, $url);
    }

    /** @test */
    public function it_generates_qr_code_for_verification_code(): void
    {
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $verificationCode = VerificationCode::create([
            'uuid' => fake()->uuid(),
            'document_id' => $document->id,
            'verification_code' => 'QRTEST12345',
            'short_code' => 'QRTEST',
            'expires_at' => null,
            'access_count' => 0,
        ]);

        $path = $this->service->generateForCode($verificationCode);

        $this->assertNotEmpty($path);
        Storage::disk('local')->assertExists($path);

        $verificationCode->refresh();
        $this->assertEquals($path, $verificationCode->qr_code_path);
    }

    /** @test */
    public function it_generates_qr_code_for_document(): void
    {
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        // Need to mock PublicVerificationService for creating verification code
        $this->app->bind(PublicVerificationService::class, function () {
            $mock = $this->createMock(PublicVerificationService::class);
            $mock->method('createVerificationCode')
                ->willReturn(VerificationCode::create([
                    'uuid' => fake()->uuid(),
                    'document_id' => 1,
                    'verification_code' => 'DOCQRTEST12',
                    'short_code' => 'DOCQR1',
                    'expires_at' => null,
                    'access_count' => 0,
                ]));

            return $mock;
        });

        // Create verification code first
        $verificationCode = VerificationCode::create([
            'uuid' => fake()->uuid(),
            'document_id' => $document->id,
            'verification_code' => 'DOCTEST1234',
            'short_code' => 'DOCTES',
            'expires_at' => null,
            'access_count' => 0,
        ]);

        $path = $this->service->generateForDocument($document);

        $this->assertNotEmpty($path);
        Storage::disk('local')->assertExists($path);
    }

    /** @test */
    public function it_retrieves_qr_code_content(): void
    {
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $verificationCode = VerificationCode::create([
            'uuid' => fake()->uuid(),
            'document_id' => $document->id,
            'verification_code' => 'GETQRTEST12',
            'short_code' => 'GETQR1',
            'expires_at' => null,
            'access_count' => 0,
        ]);

        // Generate QR code first
        $this->service->generateForCode($verificationCode);
        $verificationCode->refresh();

        $content = $this->service->getQrCode($verificationCode);

        $this->assertNotNull($content);
        $this->assertNotEmpty($content);
    }

    /** @test */
    public function it_returns_null_for_missing_qr_code(): void
    {
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $verificationCode = VerificationCode::create([
            'uuid' => fake()->uuid(),
            'document_id' => $document->id,
            'verification_code' => 'NOQRTEST123',
            'short_code' => 'NOQR12',
            'expires_at' => null,
            'access_count' => 0,
            'qr_code_path' => null,
        ]);

        $content = $this->service->getQrCode($verificationCode);

        $this->assertNull($content);
    }

    /** @test */
    public function it_gets_qr_code_as_data_url(): void
    {
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $verificationCode = VerificationCode::create([
            'uuid' => fake()->uuid(),
            'document_id' => $document->id,
            'verification_code' => 'DATAURL1234',
            'short_code' => 'DATAU1',
            'expires_at' => null,
            'access_count' => 0,
        ]);

        $this->service->generateForCode($verificationCode);
        $verificationCode->refresh();

        $dataUrl = $this->service->getQrCodeAsDataUrl($verificationCode);

        $this->assertNotNull($dataUrl);
        $this->assertStringStartsWith('data:image/', $dataUrl);
        $this->assertStringContainsString('base64,', $dataUrl);
    }

    /** @test */
    public function it_deletes_qr_code(): void
    {
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $verificationCode = VerificationCode::create([
            'uuid' => fake()->uuid(),
            'document_id' => $document->id,
            'verification_code' => 'DELETETEST1',
            'short_code' => 'DELETE',
            'expires_at' => null,
            'access_count' => 0,
        ]);

        $path = $this->service->generateForCode($verificationCode);
        $verificationCode->refresh();

        // Verify file exists
        Storage::disk('local')->assertExists($path);

        // Delete QR code
        $result = $this->service->deleteQrCode($verificationCode);

        $this->assertTrue($result);
        Storage::disk('local')->assertMissing($path);

        $verificationCode->refresh();
        $this->assertNull($verificationCode->qr_code_path);
    }

    /** @test */
    public function it_regenerates_qr_code(): void
    {
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $verificationCode = VerificationCode::create([
            'uuid' => fake()->uuid(),
            'document_id' => $document->id,
            'verification_code' => 'REGENTEST12',
            'short_code' => 'REGEN1',
            'expires_at' => null,
            'access_count' => 0,
        ]);

        // Generate initial QR code
        $oldPath = $this->service->generateForCode($verificationCode);
        $verificationCode->refresh();

        // Regenerate
        $newPath = $this->service->regenerateQrCode($verificationCode);

        $this->assertNotEquals($oldPath, $newPath);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($newPath);
    }

    /** @test */
    public function it_returns_true_when_deleting_non_existent_qr(): void
    {
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $verificationCode = VerificationCode::create([
            'uuid' => fake()->uuid(),
            'document_id' => $document->id,
            'verification_code' => 'NODELETETE1',
            'short_code' => 'NODEL1',
            'expires_at' => null,
            'access_count' => 0,
            'qr_code_path' => null,
        ]);

        $result = $this->service->deleteQrCode($verificationCode);

        $this->assertTrue($result);
    }
}
