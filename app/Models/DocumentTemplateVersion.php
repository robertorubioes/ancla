<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Version de una plantilla: PDF base mas esquema de campos.
 *
 * Una vez publicada NO se modifica. Los procesos de firma apuntan aqui, de
 * modo que siempre se puede reconstruir con que plantilla y que campos se
 * genero un documento firmado.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property int $document_template_id
 * @property int $version
 * @property int $document_id
 * @property int $created_by
 * @property Carbon|null $published_at
 */
class DocumentTemplateVersion extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'tenant_id',
        'document_template_id',
        'version',
        'document_id',
        'created_by',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $version): void {
            $version->uuid ??= (string) Str::uuid();
        });
    }

    /**
     * @return BelongsTo<DocumentTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
    }

    /**
     * PDF base sobre el que se estampan los valores.
     *
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return HasMany<DocumentTemplateField, $this>
     */
    public function fields(): HasMany
    {
        return $this->hasMany(DocumentTemplateField::class)->orderBy('order');
    }

    /**
     * @return HasMany<DocumentTemplateSigner, $this>
     */
    public function signerRoles(): HasMany
    {
        return $this->hasMany(DocumentTemplateSigner::class)->orderBy('order');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
