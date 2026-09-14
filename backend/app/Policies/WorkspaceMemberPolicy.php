<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkspaceMember;
use App\Tenancy\WorkspaceContext;

class WorkspaceMemberPolicy
{
    public function __construct(private readonly WorkspaceContext $context) {}

    /**
     * Members can view members (PROJECT_SPEC.md §4).
     */
    public function viewAny(User $user): bool
    {
        return $this->context->established();
    }

    /**
     * Only admins can change roles (PROJECT_SPEC.md §4).
     */
    public function update(User $user, WorkspaceMember $member): bool
    {
        return $this->context->owns($member) && $this->context->isAdmin();
    }

    /**
     * Only admins can remove members (PROJECT_SPEC.md §4).
     */
    public function delete(User $user, WorkspaceMember $member): bool
    {
        return $this->update($user, $member);
    }
}
