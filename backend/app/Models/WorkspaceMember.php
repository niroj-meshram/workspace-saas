<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\WorkspaceMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Membership of a user in a workspace, carrying the workspace-specific role.
 *
 * Nothing is mass assignable: workspace_id, user_id and role are all
 * authorization-sensitive and must be set explicitly by an action
 * (PROJECT_SPEC.md §17).
 */
class WorkspaceMember extends Model
{
    /** @use HasFactory<WorkspaceMemberFactory> */
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => WorkspaceRole::class,
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
