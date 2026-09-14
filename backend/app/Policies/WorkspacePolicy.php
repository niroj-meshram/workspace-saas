<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;
use App\Tenancy\WorkspaceContext;

/**
 * Authorization runs against the tenant context established by the
 * ResolveWorkspace middleware, so every check is anchored to a verified active
 * membership rather than to a workspace id taken from the request.
 */
class WorkspacePolicy
{
    public function __construct(private readonly WorkspaceContext $context) {}

    /**
     * Any active member may read the workspace.
     */
    public function view(User $user, Workspace $workspace): bool
    {
        return $this->context->is($workspace);
    }

    /**
     * "Manage workspace" is an admin capability (PROJECT_SPEC.md §4).
     */
    public function update(User $user, Workspace $workspace): bool
    {
        return $this->context->is($workspace) && $this->context->isAdmin();
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        return $this->update($user, $workspace);
    }
}
