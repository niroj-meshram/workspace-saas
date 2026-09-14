<?php

namespace App\Policies;

use App\Models\User;
use App\Tenancy\WorkspaceContext;

/**
 * The activity feed is readable by any active member of the workspace, and by
 * nobody else.
 *
 * There is deliberately no create, update or delete method: activities are
 * written only by business actions and are never modified
 * (PROJECT_SPEC.md §9). A missing policy method denies by default, so any
 * future attempt to authorize a write against an Activity fails closed.
 */
class ActivityPolicy
{
    public function __construct(private readonly WorkspaceContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->established();
    }
}
