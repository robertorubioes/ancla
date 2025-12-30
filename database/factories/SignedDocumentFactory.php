<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Document;
use App\Models\EvidencePackage;
use App\Models\SignedDocument;
use App\Models\Signer;
use App\Models\SigningProcess;
use App\Models\Tenant;
use App\Models\TsaToken;
use App\Models\VerificationCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SignedDocumentFactory extends Factory
{
    protected $model = SignedDocument::class;

    public function definition(): array
    {
        $content = '%PDF-1.4 signed test content';
        $hash = hash('sha256', $content);

        return [
            'uuid' => Str::uuid(),
            'tenant_id' => Tenant::factory(),
            'signing_process_id' => SigningProcess::factory(),
            'signer_id' => Signer::factory(),
            'original_document_id' => Document::factory(),
            'storage_disk' => 'local',
            'signed_path' => 'signed/test/'.Str::random(10).'.pdf',
            'signed_name' => 'test_signed.pdf',
            'file_size' => strlen($content),
            'content_hash' => $hash,
            'original_hash' => hash('sha256', '%PDF-1.4 original'),
            'hash_algorithm' => 'SHA-256',
            'pkcs7_signature' => bin2hex(random_bytes(256)),
            'certificate_subject' => 'CN=ANCLA Development, O=ANCLA',
            'certificate_issuer' => 'CN=ANCLA Development, O=ANCLA',
            'certificate_serial' => (string) random_int(1000000, 9999999),
            'certificate_fingerprint' => hash('sha256', random_bytes(32)),
            'pades_level' => 'B-LT',
            'has_tsa_token' => false,
            'tsa_token_id' => null,
            'has_validation_data' => false,
            'signature_position' => [
                'page' => 'last',
                'x' => 50,
                'y' => 50,
                'width' => 80,
                'height' => 40,
            ],
            'signature_visible' => true,
            'signature_appearance' => [],
            'embedded_metadata' => [
                'ANCLA_Version' => '1.0',
            ],
            'verification_code_id' => null,
            'qr_code_embedded' => true,
            'evidence_package_id' => EvidencePackage::factory(),
            'adobe_validated' => null,
            'adobe_validation_date' => null,
            'validation_errors' => null,
            'status' => 'signed',
            'error_message' => null,
            'signed_at' => now(),
        ];
    }

    public function withTsaToken(): static
    {
        return $this->state(fn (array $attributes) => [
            'has_tsa_token' => true,
            'tsa_token_id' => TsaToken::factory(),
        ]);
    }

    public function withVerificationCode(): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_code_id' => VerificationCode::factory(),
        ]);
    }

    public function signed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'signed',
            'signed_at' => now(),
        ]);
    }

    public function error(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'error',
            'error_message' => 'Test error message',
        ]);
    }
}
