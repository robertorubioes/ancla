<?php

namespace App\Models;

use App\Enums\TemplateFieldType;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Un campo de una plantilla: que se pide y donde se estampa.
 *
 * Las coordenadas van en milimetros desde arriba a la izquierda, la misma
 * convencion que config/signing.php usa para la firma.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property int $document_template_version_id
 * @property string $key
 * @property string $label
 * @property string|null $help_text
 * @property TemplateFieldType $type
 * @property bool $required
 * @property string|null $default_value
 * @property array<int, array{value: string, label?: string}>|null $options
 * @property array<string, mixed>|null $validation
 * @property int $page
 * @property float $x
 * @property float $y
 * @property float $width
 * @property float $height
 * @property int $font_size
 * @property string $align
 * @property int $order
 */
class DocumentTemplateField extends Model
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
        'key',
        'label',
        'help_text',
        'type',
        'required',
        'default_value',
        'options',
        'validation',
        'page',
        'x',
        'y',
        'width',
        'height',
        'font_size',
        'align',
        'order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TemplateFieldType::class,
            'required' => 'boolean',
            'options' => 'array',
            'validation' => 'array',
            'page' => 'integer',
            'x' => 'float',
            'y' => 'float',
            'width' => 'float',
            'height' => 'float',
            'font_size' => 'integer',
            'order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $field): void {
            $field->uuid ??= (string) Str::uuid();
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
     * Valores posibles de un desplegable, indexados por su valor.
     *
     * @return array<string, string>
     */
    public function optionMap(): array
    {
        $map = [];
        foreach ($this->options ?? [] as $option) {
            $map[(string) $option['value']] = (string) ($option['label'] ?? $option['value']);
        }

        return $map;
    }
}
