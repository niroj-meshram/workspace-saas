<?php

namespace App\Actions\Tenancy;

use App\Actions\Activity\RecordActivity;
use App\Actions\Tenancy\Concerns\ProtectsLastAdmin;
use App\Enums\ActivityType;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeMemberRole
{
    use ProtectsLastAdmin;

    public function __construct(private readonly RecordActivity $activity) {}

    /**
     * @throws ValidationException when the change would
     *                             leave the workspace without an admin
     */
    public function handle(
        Workspace $workspace,
        User $actor,
        WorkspaceMember $member,
        WorkspaceRole $role,
    ): WorkspaceMember {
        return DB::transaction(function () use ($workspace, $actor, $member, $role): WorkspaceMember {
            // Promoting to admin can never reduce the admin count, so the
            // invariant only needs checking when the target role is not admin.
            if ($role !== WorkspaceRole::Admin) {
                $this->ensureWorkspaceKeepsAnAdmin($workspace, $member, 'role');
            }

            $previous = $member->role;

            if ($previous === $role) {
                return $member;
            }

            $member->role = $role;
            $member->save();

            $this->activity->handle(
                $workspace,
                $actor,
                ActivityType::MemberRoleChanged,
                $member,
                [
                    'user_id' => $member->user_id,
                    'old_role' => $previous->value,
                    'new_role' => $role->value,
                ],
            );

            return $member;
        });
    }
}
