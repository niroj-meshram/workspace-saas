<?php

namespace App\Models;

use App\Enums\ActivityType;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

/**
 * Append-only audit record. Activities are never updated or deleted, and are
 * retained even when their subject is soft-deleted (PROJECT_SPEC.md §9, §11),
 * so there is no SoftDeletes trait and no updated_at column.
 */
class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory, HasUuidPrimaryKey;

    /**
     * Activities record only when they happened.
     */
    public const UPDATED_AT = null;

    /**
     * Filters a client may narrow the feed by. Anything else is ignored
     * rather than passed through to the query.
     *
     * @var list<string>
     */
    public const FILTERABLE = ['type', 'user_id'];

    /**
     * Append-only, enforced rather than merely documented
     * (PROJECT_SPEC.md §9).
     *
     * No route exposes a write, but the invariant belongs on the model too:
     * history that can be quietly rewritten from a future action, a console
     * command or a tinker session is not an audit log.
     */
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new RuntimeException('Activities are append-only and cannot be modified.');
        });

        static::deleting(function (): never {
            throw new RuntimeException('Activities are append-only and cannot be deleted.');
        });
    }

    /**
     * Apply the whitelisted feed filters.
     *
     * Workspace scoping is not done here: it comes from the relationship the
     * query starts on, so no tenant filter is ever built from request input
     * (PROJECT_SPEC.md §7).
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['type'] ?? null, fn (Builder $q, string $v) => $q->where('type', $v))
            ->when($filters['user_id'] ?? null, fn (Builder $q, string $v) => $q->where('user_id', $v));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * The acting user. Null for system-generated events.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The record the event was about.
     *
     * withTrashed() so history stays readable after the subject is
     * soft-deleted (PROJECT_SPEC.md §9).
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }
}
