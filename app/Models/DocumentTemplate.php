<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Plantilla de documento.
 *
 * Agrupa las versiones y apunta a la vigente. La plantilla en si no guarda ni
 * el PDF ni los campos: eso vive en cada version, que es inmutable.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property string $name
 * @property string|null $description
 * @property string $status
 * @property int|null $current_version_id
 * @property int $created_by
 *
 * @see docs/architecture/adr-011-plantillas-y-api-de-cumplimentacion.md
 */
class DocumentTemplate extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'tenant_id',
        'name',
        'description',
        'status',
        'current_version_id',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $template): void {
            $template->uuid ??= (string) Str::uuid();
        });
    }

    /**
     * @return HasMany<DocumentTemplateVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(DocumentTemplateVersion::class)->orderBy('version');
    }

    /**
     * @return BelongsTo<DocumentTemplateVersion, $this>
     */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplateVersion::class, 'current_version_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Solo las plantillas utilizables: activas y con una version publicada.
     *
     * @param  Builder<DocumentTemplate>  $query
     * @return Builder<DocumentTemplate>
     */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->whereNotNull('current_version_id');
    }

    /**
     * Una plantilla es utilizable cuando esta activa y tiene version vigente.
     */
    public function isUsable(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->current_version_id !== null;
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
