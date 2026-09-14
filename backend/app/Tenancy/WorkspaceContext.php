<?php

namespace App\Tenancy;

use App\Enums\WorkspaceRole;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * The current tenant for the request (PROJECT_SPEC.md §7).
 *
 * Established once by the ResolveWorkspace middleware after it has verified an
 * active membership, and read from there on by policies, controllers and
 * actions. Nothing else may set it, and the workspace never comes from the
 * request body.
 *
 * Bound as a scoped instance, so it is created per request and reset between
 * requests even on a long-lived worker.
 */
class WorkspaceContext
{
    private ?Workspace $workspace = null;

    private ?WorkspaceMember $member = null;

    /**
     * Record the resolved tenant. The caller is responsible for having
     * verified that the membership is active and belongs to the workspace.
     */
    public function establish(Workspace $workspace, WorkspaceMember $member): void
    {
        if ($member->workspace_id !== $workspace->getKey()) {
            throw new RuntimeException('Membership does not belong to the resolved workspace.');
        }

        $this->workspace = $workspace;
        $this->member = $member;
    }

    public function established(): bool
    {
        return $this->workspace !== null;
    }

    public function workspace(): Workspace
    {
        return $this->workspace ?? throw new RuntimeException(
            'No workspace context. Route is missing the "workspace" middleware.'
        );
    }

    /**
     * The authenticated user's membership in the current workspace.
     */
    public function member(): WorkspaceMember
    {
        return $this->member ?? throw new RuntimeException(
            'No workspace context. Route is missing the "workspace" middleware.'
        );
    }

    public function role(): WorkspaceRole
    {
        return $this->member()->role;
    }

    public function isAdmin(): bool
    {
        return $this->established() && $this->role() === WorkspaceRole::Admin;
    }

    /**
     * Whether the given workspace is the one resolved for this request.
     */
    public function is(Workspace $workspace): bool
    {
        return $this->established() && $this->workspace()->is($workspace);
    }

    /**
     * Whether a tenant-owned record belongs to the current workspace. Used by
     * policies as a defence in depth behind the route's scoped bindings.
     */
    public function owns(Model $record): bool
    {
        return $this->established()
            && $record->getAttribute('workspace_id') === $this->workspace()->getKey();
    }
}
