<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests run against a migrated, per-test database. Locally that is
| SQLite in-memory (see phpunit.xml); CI is expected to point the same suite
| at PostgreSQL.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;

/**
 * Add a user to a workspace with the given role.
 */
function memberOf(Workspace $workspace, User $user, WorkspaceRole $role = WorkspaceRole::User): WorkspaceMember
{
    return WorkspaceMember::factory()->create([
        'workspace_id' => $workspace->getKey(),
        'user_id' => $user->getKey(),
        'role' => $role,
    ]);
}

/**
 * A workspace plus its founding admin, the shape every workspace has after
 * going through CreateWorkspace.
 *
 * @return array{0: Workspace, 1: User, 2: WorkspaceMember}
 */
function workspaceWithAdmin(?User $admin = null): array
{
    $workspace = Workspace::factory()->create();
    $admin ??= User::factory()->create();

    return [$workspace, $admin, memberOf($workspace, $admin, WorkspaceRole::Admin)];
}
