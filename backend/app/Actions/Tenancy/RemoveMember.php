<?php

namespace App\Actions\Tenancy;

use App\Actions\Activity\RecordActivity;
use App\Actions\Tenancy\Concerns\ProtectsLastAdmin;
use App\Enums\ActivityType;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveMember
{
    use ProtectsLastAdmin;

    public function __construct(private readonly RecordActivity $activity) {}

    /**
     * Soft-delete the membership, which is what makes it inactive everywhere:
     * the relationship, the tenant middleware and the route's scoped bindings
     * all go through the soft-delete scope (PROJECT_SPEC.md §11).
     *
     * The membership and the unassignment are one transaction, so a workspace
     * can never be left holding tasks assigned to somebody who is no longer a
     * member of it (PROJECT_SPEC.md §18).
     *
     * @throws ValidationException when removing the last admin
     */
    public function handle(Workspace $workspace, User $actor, WorkspaceMember $member): void
    {
        DB::transaction(function () use ($workspace, $actor, $member): void {
            $this->ensureWorkspaceKeepsAnAdmin($workspace, $member, 'member');

            $this->activity->handle(
                $workspace,
                $actor,
                ActivityType::MemberRemoved,
                $member,
                [
                    'user_id' => $member->user_id,
                    'role' => $member->role->value,
                ],
            );

            $this->unassignTheirTasks($workspace, $member);

            $member->delete();
        });
    }

    /**
     * "Removing a member sets their assigned tasks to NULL"
     * (PROJECT_SPEC.md §8).
     *
     * Scoped by starting from the workspace's own relationship, so it can only
     * ever reach this tenant's tasks — the same person may still be an active
     * member elsewhere, and their work there is none of this operation's
     * business. Soft-deleted tasks are outside the relationship and are left
     * as they are: they are already gone from every listing, and rewriting
     * them would edit history rather than the present.
     *
     * One statement rather than a loop: the number of tasks a person holds is
     * unbounded, and this runs inside the transaction that removes them.
     */
    private function unassignTheirTasks(Workspace $workspace, WorkspaceMember $member): void
    {
        $workspace->tasks()
            ->where('assignee_id', $member->user_id)
            ->update(['assignee_id' => null]);
    }
}
