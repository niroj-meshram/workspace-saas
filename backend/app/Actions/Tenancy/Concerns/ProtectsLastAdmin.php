<?php

namespace App\Actions\Tenancy\Concerns;

use App\Enums\WorkspaceRole;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Validation\ValidationException;

/**
 * "A workspace must always have at least one admin" (PROJECT_SPEC.md §4).
 *
 * Kept in one place because it is the same invariant behind two different
 * operations — demoting an admin and removing one — and getting it wrong in
 * either leaves a workspace nobody can administer.
 */
trait ProtectsLastAdmin
{
    /**
     * Refuse an operation that would leave the workspace with no admin.
     *
     * Must be called inside the operation's transaction. It locks every admin
     * membership row in the workspace, not just the other ones: with two
     * admins and two concurrent demotions, locking only "the others" lets both
     * requests observe a surviving admin and commit, leaving zero. Locking the
     * whole set serialises them, so the second request re-reads after the
     * first commits and is correctly refused.
     */
    protected function ensureWorkspaceKeepsAnAdmin(
        Workspace $workspace,
        WorkspaceMember $member,
        string $errorKey,
    ): void {
        $adminIds = $workspace->members()
            ->where('role', WorkspaceRole::Admin)
            ->lockForUpdate()
            ->pluck('id')
            ->all();

        $targetIsAdmin = in_array($member->getKey(), $adminIds, true);

        if ($targetIsAdmin && count($adminIds) === 1) {
            throw ValidationException::withMessages([
                $errorKey => ['A workspace must always have at least one admin.'],
            ]);
        }
    }
}
