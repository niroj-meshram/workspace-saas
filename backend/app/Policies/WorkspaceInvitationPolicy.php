<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkspaceInvitation;
use App\Tenancy\WorkspaceContext;

/**
 * Only admins invite, list and revoke (PROJECT_SPEC.md §4, §16).
 *
 * accept() is the exception: it runs on a route with no workspace context,
 * because the accepting user is by definition not a member yet. Its
 * authorization is the email match instead.
 */
class WorkspaceInvitationPolicy
{
    public function __construct(private readonly WorkspaceContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->established() && $this->context->isAdmin();
    }

    public function create(User $user): bool
    {
        return $this->context->established() && $this->context->isAdmin();
    }

    public function delete(User $user, WorkspaceInvitation $invitation): bool
    {
        return $this->context->owns($invitation) && $this->context->isAdmin();
    }

    /**
     * "The authenticated user's email must match the invitation email."
     *
     * Compared with hash_equals so the check does not leak the address through
     * timing, and case-insensitively because both sides are stored lowercased.
     */
    public function accept(User $user, WorkspaceInvitation $invitation): bool
    {
        return hash_equals(
            mb_strtolower($invitation->email),
            mb_strtolower($user->email),
        );
    }
}
