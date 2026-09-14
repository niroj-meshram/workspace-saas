<?php

namespace App\Actions\Invitations;

use App\Actions\Activity\RecordActivity;
use App\Enums\ActivityType;
use App\Exceptions\InvitationConflict;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class AcceptInvitation
{
    public function __construct(private readonly RecordActivity $activity) {}

    /**
     * Turn an invitation into a membership.
     *
     * Email matching is authorization and is checked by the policy before this
     * runs; what is enforced here is the invitation's own state.
     *
     * The membership and the acceptance are one transaction: an invitation
     * marked accepted without a membership would be unrecoverable, and a
     * membership without the mark would let the link be used again
     * (PROJECT_SPEC.md §18).
     *
     * @throws InvitationConflict when the invitation cannot be accepted
     */
    public function handle(WorkspaceInvitation $invitation, User $user): WorkspaceMember
    {
        return DB::transaction(function () use ($invitation, $user): WorkspaceMember {
            // Re-read under a row lock so two clicks on the same link cannot
            // both observe an unaccepted invitation.
            $invitation = WorkspaceInvitation::query()
                ->whereKey($invitation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($invitation->isAccepted()) {
                throw InvitationConflict::alreadyAccepted();
            }

            if ($invitation->isExpired()) {
                throw InvitationConflict::expired();
            }

            $workspace = $invitation->workspace;

            $member = new WorkspaceMember;
            $member->user_id = $user->getKey();
            // The role comes from the invitation the admin issued, never from
            // anything the accepting user sends (PROJECT_SPEC.md §17).
            $member->role = $invitation->role;

            try {
                $workspace->members()->save($member);
            } catch (UniqueConstraintViolationException) {
                throw InvitationConflict::alreadyAMember();
            }

            $invitation->accepted_at = now();
            $invitation->save();

            $this->activity->handle(
                $workspace,
                $user,
                ActivityType::InvitationAccepted,
                $invitation,
                [
                    'email' => $invitation->email,
                    'role' => $invitation->role->value,
                    'member_id' => $member->getKey(),
                ],
            );

            return $member;
        });
    }
}
