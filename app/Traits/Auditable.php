<?php

namespace App\Traits;

use App\Models\AuditTrailEntry;
use App\Services\Evidence\AuditTrailService;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Trait for models that should be audited.
 *
 * Provides automatic audit trail recording for model events
 * (created, updated, deleted) and manual event recording.
 *
 * @see ADR-005 in docs/architecture/decisions.md
 */
trait Auditable
{
    /**
     * Boot the Auditable trait.
     *
     * Registers model observers for automatic event tracking.
     */
    protected static function bootAuditable(): void
    {
        // Record creation
        static::created(function ($model) {
            if ($model->shouldAudit('created')) {
                $model->recordEvent(
                    $model->getAuditEventType('created'),
                    $model->getCreatedAuditPayload()
                );
            }
        });

        // Record updates
        static::updated(function ($model) {
            if ($model->shouldAudit('updated')) {
                $model->recordEvent(
                    $model->getAuditEventType('updated'),
                    $model->getUpdatedAuditPayload()
                );
            }
        });

        // Record deletions
        static::deleted(function ($model) {
            if ($model->shouldAudit('deleted')) {
                $model->recordEvent(
                    $model->getAuditEventType('deleted'),
                    $model->getDeletedAuditPayload()
                );
            }
        });
    }

    /**
     * Get all audit trail entries for this model.
     *
     * @return MorphMany<AuditTrailEntry>
     */
    public function auditTrail(): MorphMany
    {
        return $this->morphMany(AuditTrailEntry::class, 'auditable')
            ->orderBy('sequence', 'desc');
    }

    /**
     * Record a custom event in the audit trail.
     *
     * @param  string  $event  Event type (e.g., 'document.viewed')
     * @param  array<string, mixed>  $payload  Additional event data
     */
    public function recordEvent(string $event, array $payload = []): void
    {
        /** @var AuditTrailService $service */
        $service = app(AuditTrailService::class);

        $service->record($this, $event, $payload);
    }

    /**
     * Get the event type for a model event.
     *
     * Override this method to customize event types.
     *
     * @param  string  $modelEvent  Model event (created, updated, deleted)
     * @return string Event type
     */
    protected function getAuditEventType(string $modelEvent): string
    {
        $modelName = strtolower(class_basename($this));

        return "{$modelName}.{$modelEvent}";
    }

    /**
     * Check if a model event should be audited.
     *
     * Override this method to customize which events are audited.
     *
     * @param  string  $event  Event name (created, updated, deleted)
     * @return bool True if the event should be audited
     */
    protected function shouldAudit(string $event): bool
    {
        // By default, audit all events
        // Override in model to customize
        $auditEvents = $this->getAuditedEvents();

        return in_array($event, $auditEvents);
    }

    /**
     * Get the list of events to audit.
     *
     * Override this in your model to customize which events are audited.
     *
     * @return array<string> List of event names
     */
    protected function getAuditedEvents(): array
    {
        return property_exists($this, 'auditedEvents')
            ? $this->auditedEvents
            : ['created', 'updated', 'deleted'];
    }

    /**
     * Get the payload for a created event.
     *
     * @return array<string, mixed>
     */
    protected function getCreatedAuditPayload(): array
    {
        $attributes = $this->getAuditableAttributes();

        return [
            'action' => 'created',
            'attributes' => $attributes,
        ];
    }

    /**
     * Get the payload for an updated event.
     *
     * @return array<string, mixed>
     */
    protected function getUpdatedAuditPayload(): array
    {
        $changes = $this->getAuditableChanges();

        return [
            'action' => 'updated',
            'changes' => $changes,
        ];
    }

    /**
     * Get the payload for a deleted event.
     *
     * @return array<string, mixed>
     */
    protected function getDeletedAuditPayload(): array
    {
        return [
            'action' => 'deleted',
            'attributes' => $this->getAuditableAttributes(),
        ];
    }

    /**
     * Get the model's attributes for auditing.
     *
     * Excludes hidden attributes (like passwords) from the audit log.
     *
     * @return array<string, mixed>
     */
    protected function getAuditableAttributes(): array
    {
        $hidden = $this->getHidden();
        $attributes = $this->getAttributes();

        // Exclude hidden fields
        $excluded = array_merge($hidden, $this->getExcludedAuditAttributes());

        return array_diff_key($attributes, array_flip($excluded));
    }

    /**
     * Get the model's changes for auditing.
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    protected function getAuditableChanges(): array
    {
        $dirty = $this->getDirty();
        $original = $this->getOriginal();
        $excluded = array_merge($this->getHidden(), $this->getExcludedAuditAttributes());

        $changes = [];

        foreach ($dirty as $key => $newValue) {
            if (! in_array($key, $excluded)) {
                $changes[$key] = [
                    'old' => $original[$key] ?? null,
                    'new' => $newValue,
                ];
            }
        }

        return $changes;
    }

    /**
     * Get attributes that should be excluded from audit logs.
     *
     * Override this method to exclude additional attributes.
     *
     * @return array<string>
     */
    protected function getExcludedAuditAttributes(): array
    {
        return property_exists($this, 'excludedFromAudit')
            ? $this->excludedFromAudit
            : [];
    }

    /**
     * Get the audit trail ordered chronologically (oldest first).
     *
     * @return MorphMany<AuditTrailEntry>
     */
    public function auditTrailChronological(): MorphMany
    {
        return $this->morphMany(AuditTrailEntry::class, 'auditable')
            ->orderBy('sequence', 'asc');
    }

    /**
     * Get the latest audit trail entry.
     */
    public function getLatestAuditEntry(): ?AuditTrailEntry
    {
        return $this->auditTrail()->first();
    }

    /**
     * Get the first audit trail entry.
     */
    public function getFirstAuditEntry(): ?AuditTrailEntry
    {
        return $this->auditTrailChronological()->first();
    }
}
