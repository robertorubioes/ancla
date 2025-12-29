<?php

namespace Database\Factories;

use App\Models\EvidenceDossier;
use App\Models\EvidencePackage;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EvidenceDossier>
 */
class EvidenceDossierFactory extends Factory
{
    protected $model = EvidenceDossier::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid()->toString(),
            'tenant_id' => Tenant::factory(),
            'signable_type' => EvidencePackage::class,
            'signable_id' => EvidencePackage::factory(),
            'dossier_type' => fake()->randomElement(EvidenceDossier::getDossierTypes()),
            'file_path' => 'evidence-dossiers/'.Str::random(20).'.pdf',
            'file_name' => 'dossier-'.Str::random(10).'.pdf',
            'file_size' => fake()->numberBetween(10000, 5000000),
            'file_hash' => hash('sha256', Str::random(100)),
            'page_count' => fake()->numberBetween(1, 50),
            'includes_document' => true,
            'includes_audit_trail' => true,
            'includes_device_info' => true,
            'includes_geolocation' => true,
            'includes_ip_info' => true,
            'includes_consents' => true,
            'includes_tsa_tokens' => true,
            'verification_code' => EvidenceDossier::generateVerificationCode(),
            'verification_url' => null,
            'verification_qr_path' => null,
            'audit_entries_count' => fake()->numberBetween(5, 50),
            'devices_count' => fake()->numberBetween(1, 5),
            'geolocations_count' => fake()->numberBetween(1, 5),
            'consents_count' => fake()->numberBetween(1, 10),
            'generated_at' => now(),
            'download_count' => 0,
        ];
    }

    /**
     * Configure the dossier as signed.
     */
    public function signed(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform_signature' => hash('sha256', Str::random(100)),
            'signature_algorithm' => 'HMAC-SHA256',
            'signed_at' => now(),
        ]);
    }

    /**
     * Configure as full evidence type.
     */
    public function fullEvidence(): static
    {
        return $this->state(fn (array $attributes) => [
            'dossier_type' => EvidenceDossier::TYPE_FULL_EVIDENCE,
        ]);
    }

    /**
     * Configure as audit trail only type.
     */
    public function auditTrailOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'dossier_type' => EvidenceDossier::TYPE_AUDIT_TRAIL,
            'includes_document' => false,
            'includes_device_info' => false,
            'includes_geolocation' => false,
            'includes_ip_info' => false,
            'includes_consents' => false,
        ]);
    }
}
