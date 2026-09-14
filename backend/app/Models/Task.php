<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Only user-authored content and the workflow fields are mass assignable.
 * The ownership keys (workspace_id, project_id, assignee_id) are set
 * explicitly by actions after the tenant and membership checks
 * (PROJECT_SPEC.md §17).
 */
#[Fillable(['title', 'description', 'status', 'priority', 'due_date'])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    /**
     * Columns a client may sort by (PROJECT_SPEC.md §12: "Only whitelist valid
     * filters/sorts"). Anything else falls back to the default order.
     *
     * status and priority are deliberately absent: ordering them lexically
     * would read as correct while being wrong (priority would sort
     * high, low, medium). Adding them means adding an explicit ordering, not
     * another column name here.
     *
     * @var list<string>
     */
    public const SORTABLE = ['title', 'due_date', 'created_at', 'updated_at'];

    public const DEFAULT_SORT = '-created_at';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'due_date' => 'immutable_date',
        ];
    }

    /**
     * Apply the whitelisted list filters (PROJECT_SPEC.md §12).
     *
     * Values are validated upstream by IndexTaskRequest; this only decides how
     * each one narrows the query. Workspace scoping is not done here — it comes
     * from the relationship the query starts on, so no tenant filter is ever
     * built from request input (PROJECT_SPEC.md §7).
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['project_id'] ?? null, fn (Builder $q, string $v) => $q->where('project_id', $v))
            ->when($filters['status'] ?? null, fn (Builder $q, string $v) => $q->where('status', $v))
            ->when($filters['priority'] ?? null, fn (Builder $q, string $v) => $q->where('priority', $v))
            ->when($filters['assignee_id'] ?? null, fn (Builder $q, string $v) => $q->where('assignee_id', $v))
            ->when($filters['search'] ?? null, fn (Builder $q, string $v) => $q->where(
                fn (Builder $q) => $q->whereRaw("lower(title) like ? escape '\\'", [self::likePattern($v)])
                    ->orWhereRaw("lower(description) like ? escape '\\'", [self::likePattern($v)])
            ));
    }

    /**
     * Order by a whitelisted column, "-" meaning descending.
     *
     * The id tiebreaker keeps pagination stable when the sort column has
     * duplicates, and follows the same direction as the sort: timestamps are
     * stored at second precision, so rows created in the same second tie on
     * created_at, and an ascending tiebreaker there would hand back the oldest
     * first under a "newest first" sort.
     */
    public function scopeSorted(Builder $query, ?string $sort): void
    {
        $sort = $sort ?: self::DEFAULT_SORT;

        $descending = str_starts_with($sort, '-');
        $column = ltrim($sort, '-');

        // Defence in depth: the request already restricted this to SORTABLE,
        // but a column name must never reach the query builder unchecked.
        if (! in_array($column, self::SORTABLE, true)) {
            $column = 'created_at';
            $descending = true;
        }

        $direction = $descending ? 'desc' : 'asc';

        $query->orderBy($column, $direction)->orderBy('id', $direction);
    }

    /**
     * Case-insensitive contains-match, with LIKE wildcards in the user's term
     * escaped so a search for "50%" cannot match everything.
     */
    private static function likePattern(string $term): string
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], mb_strtolower($term));

        return '%'.$escaped.'%';
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }
}
