<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Projects are archived rather than deleted, so this model deliberately has no
 * SoftDeletes trait (PROJECT_SPEC.md §11).
 *
 * workspace_id is not mass assignable; it is set from the resolved tenant
 * context (PROJECT_SPEC.md §7, §17).
 */
#[Fillable(['name', 'description', 'status'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, HasUuidPrimaryKey;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
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
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
