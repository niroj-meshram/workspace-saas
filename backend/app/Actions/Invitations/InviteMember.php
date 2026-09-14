<?php

namespace App\Actions\Invitations;

use App\Actions\Activity\RecordActivity;
use App\Enums\ActivityType;
use App\Enums\WorkspaceRole;
use App\Exceptions\InvitationConflict;
use App\Invitations\InvitationNotifier;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class InviteMember
{
    public function __construct(
        private readonly RecordActivity $activity,
        private readonly InvitationNotifier $notifier,
    ) {}

    /**
     * @throws InvitationConflict when the email already belongs to an active
     *                            member or to a pending invitation
     */
    public function handle(
        Workspace $workspace,
        User $inviter,
        string $email,
        WorkspaceRole $role,
    ): WorkspaceInvitation {
        // 256 bits from the CSPRNG. Only its digest is persisted; this value
        // leaves the process exactly once, inside the email.
        $token = bin2hex(random_bytes(32));

        [$invitation, $token] = DB::transaction(function () use ($workspace, $inviter, $email, $role, $token): array {
            $this->guardAgainstExistingMember($workspace, $email);
            $this->supersedeExpiredInvitation($workspace, $email);
            $this->guardAgainstPendingInvitation($workspace, $email);

            $invitation = new WorkspaceInvitation;
            $invitation->email = $email;
            $invitation->role = $role;
            $invitation->token_hash = WorkspaceInvitation::hashToken($token);
            $invitation->expires_at = now()->addDays(WorkspaceInvitation::EXPIRES_AFTER_DAYS);
            $invitation->invited_by = $inviter->getKey();

            try {
                // workspace_id comes from the resolved tenant via the
                // relationship, never from input (PROJECT_SPEC.md §7).
                $workspace->invitations()->save($invitation);
            } catch (UniqueConstraintViolationException) {
                // Two admins inviting the same address at once: the partial
                // unique index on (workspace_id, email) is the real guarantee,
                // the check above is only the friendly path.
                throw InvitationConflict::alreadyInvited();
            }

            $this->activity->handle(
                $workspace,
                $inviter,
                ActivityType::MemberInvited,
                $invitation,
                ['email' => $invitation->email, 'role' => $invitation->role->value],
            );

            return [$invitation, $token];
        });

        // Outside the transaction on purpose: the invitation is committed
        // before anyone tries to deliver it, so a mail failure cannot undo it
        // (PROJECT_SPEC.md §18).
        $this->notifier->send($invitation->load(['workspace', 'invitedBy']), $token);

        return $invitation;
    }

    private function guardAgainstExistingMember(Workspace $workspace, string $email): void
    {
        $alreadyMember = $workspace->members()
            ->whereHas('user', fn ($query) => $query->where('email', $email))
            ->exists();

        if ($alreadyMember) {
            throw InvitationConflict::alreadyAMember();
        }
    }

    /**
     * An expired invitation is not an active one, so it must not block a fresh
     * invite. It is soft-deleted rather than reused: the row stays for history
     * and the partial unique index is freed (PROJECT_SPEC.md §10).
     */
    private function supersedeExpiredInvitation(Workspace $workspace, string $email): void
    {
        $workspace->invitations()
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->where('expires_at', '<=', now())
            ->lockForUpdate()
            ->get()
            ->each->delete();
    }

    private function guardAgainstPendingInvitation(Workspace $workspace, string $email): void
    {
        $pending = $workspace->invitations()
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->exists();

        if ($pending) {
            throw InvitationConflict::alreadyInvited();
        }
    }
}
