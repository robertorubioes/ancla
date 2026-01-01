<?php

namespace Tests\Unit\Encryption;

use App\Models\Tenant;
use App\Services\TenantContext;
use App\Traits\Encryptable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Unit tests for Encryptable trait.
 *
 * @see \App\Traits\Encryptable
 */
class EncryptableTraitTest extends TestCase
{
    use RefreshDatabase;

    private TenantContext $tenantContext;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test tenant
        $this->tenant = Tenant::factory()->create();
        $this->tenantContext = app(TenantContext::class);
        $this->tenantContext->set($this->tenant);

        // Set up encryption key
        Config::set('app.encryption_key', 'base64:'.base64_encode(random_bytes(32)));

        // Create test table
        if (! Schema::hasTable('encryptable_test_models')) {
            Schema::create('encryptable_test_models', function ($table) {
                $table->id();
                $table->foreignId('tenant_id');
                $table->text('secret_data')->nullable();
                $table->text('public_data')->nullable();
                $table->timestamps();
            });
        }
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('encryptable_test_models');
        parent::tearDown();
    }

    /** @test */
    public function it_encrypts_attributes_on_save(): void
    {
        $model = new EncryptableTestModel([
            'tenant_id' => $this->tenant->id,
            'secret_data' => 'This is secret',
            'public_data' => 'This is public',
        ]);

        $model->save();

        // Read raw from DB
        $raw = \DB::table('encryptable_test_models')->find($model->id);

        // Secret data should be encrypted in DB
        $this->assertNotEquals('This is secret', $raw->secret_data);

        // Public data should remain plain
        $this->assertEquals('This is public', $raw->public_data);
    }

    /** @test */
    public function it_decrypts_attributes_on_retrieval(): void
    {
        $model = new EncryptableTestModel([
            'tenant_id' => $this->tenant->id,
            'secret_data' => 'This is secret',
        ]);
        $model->save();

        // Retrieve fresh instance
        $retrieved = EncryptableTestModel::find($model->id);

        // Should be automatically decrypted
        $this->assertEquals('This is secret', $retrieved->secret_data);
    }

    /** @test */
    public function it_prevents_double_encryption(): void
    {
        $model = new EncryptableTestModel([
            'tenant_id' => $this->tenant->id,
            'secret_data' => 'Test data',
        ]);
        $model->save();

        $firstEncrypted = \DB::table('encryptable_test_models')
            ->where('id', $model->id)
            ->value('secret_data');

        // Save again without changes
        $model->save();

        $secondEncrypted = \DB::table('encryptable_test_models')
            ->where('id', $model->id)
            ->value('secret_data');

        // Should remain the same (not re-encrypted)
        $this->assertEquals($firstEncrypted, $secondEncrypted);
    }

    /** @test */
    public function it_checks_if_attribute_is_encrypted(): void
    {
        $model = new EncryptableTestModel([
            'tenant_id' => $this->tenant->id,
            'secret_data' => 'Test data',
        ]);
        $model->save();

        // Retrieve to get decrypted version
        $retrieved = EncryptableTestModel::find($model->id);

        // After retrieval, attribute is decrypted in memory
        $this->assertFalse($retrieved->isAttributeEncrypted('secret_data'));

        // But in DB it is encrypted
        $rawData = \DB::table('encryptable_test_models')
            ->where('id', $model->id)
            ->value('secret_data');
        $this->assertNotEquals('Test data', $rawData);
    }

    /** @test */
    public function it_provides_encryption_metadata_for_attributes(): void
    {
        $model = new EncryptableTestModel([
            'tenant_id' => $this->tenant->id,
            'secret_data' => 'Test data',
        ]);
        $model->save();

        $metadata = $model->getAttributeEncryptionMetadata('secret_data');

        $this->assertTrue($metadata['exists']);
        $this->assertArrayHasKey('encrypted', $metadata);
    }

    /** @test */
    public function it_manually_encrypts_attribute(): void
    {
        $model = new EncryptableTestModel(['tenant_id' => $this->tenant->id]);
        $plaintext = 'Secret value';

        $encrypted = $model->encryptAttribute('secret_data', $plaintext);

        $this->assertNotEquals($plaintext, $encrypted);
        $this->assertIsString($encrypted);
    }

    /** @test */
    public function it_manually_decrypts_attribute(): void
    {
        $model = new EncryptableTestModel(['tenant_id' => $this->tenant->id]);
        $plaintext = 'Secret value';

        $encrypted = $model->encryptAttribute('secret_data', $plaintext);
        $decrypted = $model->decryptAttribute('secret_data', $encrypted);

        $this->assertEquals($plaintext, $decrypted);
    }

    /** @test */
    public function it_throws_exception_for_non_encryptable_attribute_encryption(): void
    {
        $model = new EncryptableTestModel(['tenant_id' => $this->tenant->id]);

        $this->expectException(\InvalidArgumentException::class);
        $model->encryptAttribute('public_data', 'test');
    }

    /** @test */
    public function it_throws_exception_for_non_encryptable_attribute_decryption(): void
    {
        $model = new EncryptableTestModel(['tenant_id' => $this->tenant->id]);

        $this->expectException(\InvalidArgumentException::class);
        $model->decryptAttribute('public_data', 'test');
    }

    /** @test */
    public function it_handles_null_values(): void
    {
        $model = new EncryptableTestModel([
            'tenant_id' => $this->tenant->id,
            'secret_data' => null,
        ]);
        $model->save();

        $retrieved = EncryptableTestModel::find($model->id);

        $this->assertNull($retrieved->secret_data);
    }

    /** @test */
    public function it_handles_empty_string(): void
    {
        $model = new EncryptableTestModel([
            'tenant_id' => $this->tenant->id,
            'secret_data' => '',
        ]);
        $model->save();

        $retrieved = EncryptableTestModel::find($model->id);

        $this->assertEquals('', $retrieved->secret_data);
    }
}

/**
 * Test model using Encryptable trait.
 */
class EncryptableTestModel extends Model
{
    use Encryptable;

    protected $table = 'encryptable_test_models';

    protected $fillable = ['tenant_id', 'secret_data', 'public_data'];

    protected array $encryptable = ['secret_data'];

    public $timestamps = true;
}
