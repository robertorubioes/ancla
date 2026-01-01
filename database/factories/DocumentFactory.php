<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Document;
use App\Models\Tenant;
use App\Models\TsaToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory for creating Document model instances.
 *
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Document>
     */
    protected $model = Document::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $filename = $this->faker->words(3, true).'.pdf';

        return [
            'uuid' => Str::uuid()->toString(),
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'original_filename' => $filename,
            'original_extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => $this->faker->numberBetween(10000, 5000000),
            'page_count' => $this->faker->numberBetween(1, 50),
            'storage_disk' => 'local',
            'storage_path' => 'documents/test/'.Str::uuid().'.pdf.enc',
            'stored_filename' => Str::uuid().'.pdf.enc',
            'is_encrypted' => true,
            'encryption_key_id' => null,
            'encrypted_at' => null,
            'encryption_key_version' => 'v1',
            'sha256_hash' => hash('sha256', Str::random(100)),
            'hash_algorithm' => 'SHA-256',
            'hash_verified_at' => null,
            'upload_tsa_token_id' => null,
            'thumbnail_path' => null,
            'thumbnail_generated_at' => null,
            'pdf_metadata' => [
                'title' => $this->faker->sentence(4),
                'author' => $this->faker->name(),
                'creator' => 'Test Application',
                'producer' => 'Test PDF Generator',
                'page_count' => $this->faker->numberBetween(1, 50),
            ],
            'pdf_version' => '1.7',
            'is_pdf_a' => false,
            'has_signatures' => false,
            'has_encryption' => false,
            'has_javascript' => false,
            'status' => Document::STATUS_READY,
            'error_message' => null,
        ];
    }

    /**
     * Indicate that the document is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Document::STATUS_PENDING,
        ]);
    }

    /**
     * Indicate that the document is processing.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Document::STATUS_PROCESSING,
        ]);
    }

    /**
     * Indicate that the document is ready.
     */
    public function ready(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Document::STATUS_READY,
        ]);
    }

    /**
     * Indicate that the document has an error.
     */
    public function error(string $message = 'Processing failed'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Document::STATUS_ERROR,
            'error_message' => $message,
        ]);
    }

    /**
     * Indicate that the document has a thumbnail.
     */
    public function withThumbnail(): static
    {
        return $this->state(fn (array $attributes) => [
            'thumbnail_path' => 'thumbnails/test/'.Str::uuid().'.png',
            'thumbnail_generated_at' => now(),
        ]);
    }

    /**
     * Indicate that the document has a TSA token.
     */
    public function withTsaToken(): static
    {
        return $this->state(fn (array $attributes) => [
            'upload_tsa_token_id' => TsaToken::factory(),
        ]);
    }

    /**
     * Indicate that the document is not encrypted.
     */
    public function unencrypted(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_encrypted' => false,
            'storage_path' => str_replace('.enc', '', $attributes['storage_path']),
            'stored_filename' => str_replace('.enc', '', $attributes['stored_filename']),
        ]);
    }

    /**
     * Indicate that the document is PDF/A compliant.
     */
    public function pdfA(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_pdf_a' => true,
        ]);
    }

    /**
     * Indicate that the document has existing signatures.
     */
    public function withSignatures(): static
    {
        return $this->state(fn (array $attributes) => [
            'has_signatures' => true,
        ]);
    }

    /**
     * Indicate that the document contains JavaScript.
     */
    public function withJavaScript(): static
    {
        return $this->state(fn (array $attributes) => [
            'has_javascript' => true,
        ]);
    }

    /**
     * Indicate that the document was recently created.
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => now()->subHours($this->faker->numberBetween(1, 24)),
        ]);
    }

    /**
     * Indicate that the document is large.
     */
    public function large(): static
    {
        return $this->state(fn (array $attributes) => [
            'file_size' => $this->faker->numberBetween(10000000, 50000000), // 10-50 MB
            'page_count' => $this->faker->numberBetween(100, 500),
        ]);
    }

    /**
     * Create a document for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);
    }

    /**
     * Create a document for a specific tenant.
     */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }
}
