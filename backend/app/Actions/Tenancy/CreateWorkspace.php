<?php

namespace App\Actions\Tenancy;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\DB;

/**
 * Create a workspace and make its creator an admin (PROJECT_SPEC.md §6).
 */
class CreateWorkspace
{
    /**
     * The workspace and the creator's membership are written in one
     * transaction: a workspace with no admin would be unmanageable and would
     * violate the "at least one admin" invariant from the moment it existed
     * (PROJECT_SPEC.md §18).
     */
    public function handle(User $creator, string $name): Workspace
    {
        return DB::transaction(function () use ($creator, $name): Workspace {
            $workspace = Workspace::create(['name' => $name]);

            // Set explicitly rather than mass assigned: user_id and role are
            // authorization-sensitive and must never come from input
            // (PROJECT_SPEC.md §17). The user is the authenticated one.
            $member = new WorkspaceMember;
            $member->user_id = $creator->getKey();
            $member->role = WorkspaceRole::Admin;

            $workspace->members()->save($member);

            return $workspace;
        });
    }
}
