<?php

namespace App\Actions\Invitations;

use App\Exceptions\InvitationConflict;
use App\Models\WorkspaceInvitation;

class RevokeInvitation
{
    /**
     * Withdraw an invitation that has not been used.
     *
     * A soft delete, so the row survives for history and the partial unique
     * index stops treating the address as spoken for, which lets the admin
     * invite it again (PROJECT_SPEC.md §10, §11).
     *
     * An accepted invitation is not revocable: the membership it produced is
     * real, and erasing the record of how it came about would lose history.
     * Removing the person is what RemoveMember is for.
     *
     * @throws InvitationConflict when the invitation has already been accepted
     */
    public function handle(WorkspaceInvitation $invitation): void
    {
        if ($invitation->isAccepted()) {
            throw InvitationConflict::alreadyAccepted();
        }

        $invitation->delete();
    }
}
