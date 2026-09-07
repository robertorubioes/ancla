<?php

namespace App\Traits;

use App\Exceptions\EncryptionException;
use App\Services\Document\DocumentEncryptionService;
use Illuminate\Support\Facades\Log;

/**
 * Encryptable Trait
 *
 * Provides automatic encryption/decryption of model attributes at-rest.
 *
 * Usage:
 * ```php
 * class Document extends Model
 * {
 *     use Encryptable;
 *
 *     protected array $encryptable = ['content', 'metadata'];
 * }
 * ```
 *
 * The trait automatically:
 * - Encrypts attributes before saving to database
 * - Decrypts attributes after retrieving from database
 * - Prevents double encryption/decryption
 * - Logs encryption errors
 *
 * @see DocumentEncryptionService
 * @see docs/architecture/adr-010-encryption-at-rest.md
 */
trait Encryptable
{
    /**
     * Flag to track if we're currently in an encryption operation.
     * Prevents infinite loops when accessing attributes during encryption.
     */
    private bool $isEncrypting = false;

    /**
     * Flag to track if attributes have been decrypted.
     * Prevents double decryption on repeated access.
     */
    private bool $isDecrypted = false;

    /**
     * Boot the Encryptable trait.
     *
     * Registers model event listeners for automatic encryption/decryption.
     */
    public static function bootEncryptable(): void
    {
        // Encrypt attributes before saving
        static::saving(function ($model) {
            $model->encryptAttributes();
        });

        // Decrypt attributes after retrieving from database
        static::retrieved(function ($model) {
            $model->decryptAttributes();
        });

        // Reset decryption flag when creating new instance
        static::creating(function ($model) {
            $model->isDecrypted = false;
        });
    }

    /**
     * Get the attributes that should be encrypted.
     *
     * Models using this trait should define this property:
     * protected array $encryptable = ['attribute1', 'attribute2'];
     *
     * @return list<string>
     */
    public function getEncryptableAttributes(): array
    {
        return $this->encryptable;
    }

    /**
     * Encrypt specified attributes before saving.
     *
     * Only encrypts if:
     * - Attribute exists and has a value
     * - Attribute is not already encrypted
     * - We're not already in an encryption operation
     */
    /**
     * Marca de lo que ya esta cifrado en una columna.
     *
     * El cifrado devuelve binario crudo -nonce, texto cifrado y tag-, y eso
     * no cabe en una columna de texto: MySQL rechaza la escritura con
     * "Incorrect string value" mientras SQLite la acepta sin rechistar, que es
     * por lo que en los tests no se veia. Se guarda en base64 y con un prefijo
     * para poder distinguir a simple vista lo ya cifrado de lo que no, sin
     * tener que intentar descifrarlo.
     */
    private const PREFIJO_CIFRADO = 'enc:v1:';

    /**
     * Si el valor tal y como esta en la columna ya viene cifrado.
     */
    private function estaCifradoEnColumna(mixed $value): bool
    {
        return is_string($value) && str_starts_with($value, self::PREFIJO_CIFRADO);
    }

    protected function encryptAttributes(): void
    {
        // Prevent infinite loops
        if ($this->isEncrypting) {
            return;
        }

        $this->isEncrypting = true;

        try {
            $service = app(DocumentEncryptionService::class);

            foreach ($this->getEncryptableAttributes() as $attribute) {
                if (! isset($this->attributes[$attribute])) {
                    continue;
                }

                $value = $this->attributes[$attribute];

                // Skip if null or empty
                if ($value === null || $value === '') {
                    continue;
                }

                // Only encrypt if not already encrypted
                if (! $this->estaCifradoEnColumna($value)) {
                    try {
                        $this->attributes[$attribute] = self::PREFIJO_CIFRADO
                            .base64_encode($service->encrypt($value));

                        Log::debug('Attribute encrypted', [
                            'model' => static::class,
                            'id' => $this->getKey(),
                            'attribute' => $attribute,
                        ]);
                    } catch (EncryptionException $e) {
                        Log::error('Failed to encrypt attribute', [
                            'model' => static::class,
                            'id' => $this->getKey(),
                            'attribute' => $attribute,
                            'error' => $e->getMessage(),
                        ]);
                        throw $e;
                    }
                }
            }
        } finally {
            $this->isEncrypting = false;
        }
    }

    /**
     * Decrypt attributes after retrieval from database.
     *
     * Only decrypts if:
     * - Attribute exists and has a value
     * - Attribute is encrypted
     * - We haven't already decrypted this instance
     */
    protected function decryptAttributes(): void
    {
        // Only decrypt once per instance
        if ($this->isDecrypted) {
            return;
        }

        $this->isDecrypted = true;

        $service = app(DocumentEncryptionService::class);

        foreach ($this->getEncryptableAttributes() as $attribute) {
            if (! isset($this->attributes[$attribute])) {
                continue;
            }

            $value = $this->attributes[$attribute];

            // Skip if null or empty
            if ($value === null || $value === '') {
                continue;
            }

            // Only decrypt if encrypted
            if ($this->estaCifradoEnColumna($value)) {
                try {
                    $this->attributes[$attribute] = $service->decrypt(
                        (string) base64_decode(substr($value, strlen(self::PREFIJO_CIFRADO)), true)
                    );

                    Log::debug('Attribute decrypted', [
                        'model' => static::class,
                        'id' => $this->getKey(),
                        'attribute' => $attribute,
                    ]);
                } catch (EncryptionException $e) {
                    Log::error('Failed to decrypt attribute', [
                        'model' => static::class,
                        'id' => $this->getKey(),
                        'attribute' => $attribute,
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }
            }
        }
    }

    /**
     * Manually encrypt an attribute value without saving.
     *
     * Useful for testing or manual operations.
     *
     * @param  string  $attribute  Attribute name
     * @param  string  $value  Value to encrypt
     * @return string Encrypted value
     *
     * @throws EncryptionException
     */
    public function encryptAttribute(string $attribute, string $value): string
    {
        if (! in_array($attribute, $this->getEncryptableAttributes())) {
            throw new \InvalidArgumentException("Attribute '{$attribute}' is not encryptable.");
        }

        $service = app(DocumentEncryptionService::class);

        // Se devuelve en el mismo formato en que se guarda, para que el
        // resultado se pueda escribir directamente en la columna.
        return self::PREFIJO_CIFRADO.base64_encode($service->encrypt($value));
    }

    /**
     * Manually decrypt an attribute value.
     *
     * Useful for testing or manual operations.
     *
     * @param  string  $attribute  Attribute name
     * @param  string  $value  Value to decrypt
     * @return string Decrypted value
     *
     * @throws EncryptionException
     */
    public function decryptAttribute(string $attribute, string $value): string
    {
        if (! in_array($attribute, $this->getEncryptableAttributes())) {
            throw new \InvalidArgumentException("Attribute '{$attribute}' is not encryptable.");
        }

        $service = app(DocumentEncryptionService::class);

        if (! $this->estaCifradoEnColumna($value)) {
            throw EncryptionException::invalidFormat();
        }

        return $service->decrypt(
            (string) base64_decode(substr($value, strlen(self::PREFIJO_CIFRADO)), true)
        );
    }

    /**
     * Check if an attribute value is encrypted.
     *
     * @param  string  $attribute  Attribute name
     */
    public function isAttributeEncrypted(string $attribute): bool
    {
        if (! isset($this->attributes[$attribute])) {
            return false;
        }

        $value = $this->attributes[$attribute];
        if ($value === null || $value === '') {
            return false;
        }

        return $this->estaCifradoEnColumna($value);
    }

    /**
     * Get encryption metadata for an attribute.
     *
     * @param  string  $attribute  Attribute name
     * @return array<string, mixed> Metadata including encryption status, algorithm, sizes
     */
    public function getAttributeEncryptionMetadata(string $attribute): array
    {
        if (! isset($this->attributes[$attribute])) {
            return ['exists' => false];
        }

        $value = $this->attributes[$attribute];
        if ($value === null || $value === '') {
            return ['exists' => true, 'encrypted' => false];
        }

        $service = app(DocumentEncryptionService::class);

        return array_merge(
            ['exists' => true, 'attribute' => $attribute],
            $service->getMetadata($value)
        );
    }
}
