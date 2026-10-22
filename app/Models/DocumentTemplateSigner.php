<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Rol de firmante previsto por una plantilla.
 *
 * La plantilla no fija personas, fija papeles ("arrendador",
 * "arrendatario"). Al usarla se asigna un nombre y un correo a cada rol.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property int $document_template_version_id
 * @property string $role_key
 * @property string $label
 * @property int $order
 * @property int|null $signature_page
 * @property float|null $signature_x
 * @property float|null $signature_y
 */
class DocumentTemplateSigner extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'tenant_id',
        'document_template_version_id',
        'role_key',
        'label',
        'order',
        'signature_page',
        'signature_x',
        'signature_y',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'signature_page' => 'integer',
            'signature_x' => 'float',
            'signature_y' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $signer): void {
            $signer->uuid ??= (string) Str::uuid();
        });
    }

    /**
     * @return BelongsTo<DocumentTemplateVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplateVersion::class, 'document_template_version_id');
    }

    /**
     * La plantilla puede fijar donde firma este rol; si no, se usa la
     * posicion por defecto de config/signing.php.
     */
    public function hasSignaturePosition(): bool
    {
        return $this->signature_page !== null
            && $this->signature_x !== null
            && $this->signature_y !== null;
    }
}
