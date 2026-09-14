<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Tenancy\WorkspaceContext;

/**
 * Admins create, edit and archive projects; every member can view them
 * (PROJECT_SPEC.md §4, §16).
 *
 * Checks are anchored to the tenant context established by the ResolveWorkspace
 * middleware, so a project is only ever authorized against the workspace the
 * caller is a verified active member of.
 */
class ProjectPolicy
{
    public function __construct(private readonly WorkspaceContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->established();
    }

    /**
     * Archived projects remain viewable (PROJECT_SPEC.md §8), so status plays
     * no part in read authorization.
     */
    public function view(User $user, Project $project): bool
    {
        return $this->context->owns($project);
    }

    public function create(User $user): bool
    {
        return $this->context->established() && $this->context->isAdmin();
    }

    public function update(User $user, Project $project): bool
    {
        return $this->context->owns($project) && $this->context->isAdmin();
    }

    /**
     * DELETE archives rather than removes (PROJECT_SPEC.md §11), but it is the
     * same admin capability.
     */
    public function delete(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }
}
