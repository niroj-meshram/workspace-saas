<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use App\Tenancy\WorkspaceContext;

/**
 * Tasks are the collaborative surface of a workspace: every active member may
 * create and update them regardless of role (PROJECT_SPEC.md §4, §16).
 * Deletion is the same capability — the specification grants it to neither
 * role exclusively, and a soft delete is reversible.
 *
 * The role distinction lives on projects and membership, not here; what is
 * enforced is that the caller is an active member of the task's own workspace.
 */
class TaskPolicy
{
    public function __construct(private readonly WorkspaceContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->established();
    }

    public function view(User $user, Task $task): bool
    {
        return $this->context->owns($task);
    }

    public function create(User $user): bool
    {
        return $this->context->established();
    }

    public function update(User $user, Task $task): bool
    {
        return $this->context->owns($task);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->context->owns($task);
    }
}
