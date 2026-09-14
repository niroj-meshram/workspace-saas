<?php

use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Note
|--------------------------------------------------------------------------
|
| A statement that violates a constraint aborts the surrounding PostgreSQL
| transaction, and these tests run inside one. Each test therefore ends on the
| failing statement and asserts nothing against the database afterwards.
|
*/

it('rejects a second active membership for the same user and workspace', function () {
    $workspace = Workspace::factory()->create();
    $user = User::factory()->create();

    WorkspaceMember::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
    ]);

    expect(fn () => WorkspaceMember::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
    ]))->toThrow(QueryException::class);
});

it('allows re-adding a member whose previous membership was soft deleted', function () {
    $workspace = Workspace::factory()->create();
    $user = User::factory()->create();

    $first = WorkspaceMember::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
    ]);
    $first->delete();

    $second = WorkspaceMember::factory()->admin()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
    ]);

    expect($second->exists)->toBeTrue()
        ->and($second->role)->toBe(WorkspaceRole::Admin)
        ->and(WorkspaceMember::withTrashed()->count())->toBe(2);
});

it('allows the same user to be a member of different workspaces', function () {
    $user = User::factory()->create();

    WorkspaceMember::factory()->create(['user_id' => $user->id]);
    WorkspaceMember::factory()->admin()->create(['user_id' => $user->id]);

    expect($user->memberships()->count())->toBe(2);
});

it('rejects duplicate project names inside one workspace', function () {
    $workspace = Workspace::factory()->create();

    Project::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Launch']);

    expect(fn () => Project::factory()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Launch',
    ]))->toThrow(QueryException::class);
});

it('allows the same project name in different workspaces', function () {
    $a = Project::factory()->create(['name' => 'Launch']);
    $b = Project::factory()->create(['name' => 'Launch']);

    expect($a->workspace_id)->not->toBe($b->workspace_id)
        ->and(Project::where('name', 'Launch')->count())->toBe(2);
});

it('rejects a task whose project belongs to another workspace', function () {
    $workspace = Workspace::factory()->create();
    $foreignProject = Project::factory()->create();

    expect(fn () => Task::factory()->create([
        'workspace_id' => $workspace->id,
        'project_id' => $foreignProject->id,
    ]))->toThrow(QueryException::class);
});

it('accepts a task whose project belongs to the same workspace', function () {
    $project = Project::factory()->create();

    $task = Task::factory()->forProject($project)->create();

    expect($task->workspace_id)->toBe($project->workspace_id)
        ->and($task->project->is($project))->toBeTrue();
});

it('rejects duplicate invitation token hashes', function () {
    $hash = hash('sha256', 'shared-token');

    WorkspaceInvitation::factory()->create(['token_hash' => $hash]);

    expect(fn () => WorkspaceInvitation::factory()->create(['token_hash' => $hash]))
        ->toThrow(QueryException::class);
});

it('rejects a second pending invitation for the same email and workspace', function () {
    $workspace = Workspace::factory()->create();

    WorkspaceInvitation::factory()->create([
        'workspace_id' => $workspace->id,
        'email' => 'new@example.com',
    ]);

    expect(fn () => WorkspaceInvitation::factory()->create([
        'workspace_id' => $workspace->id,
        'email' => 'new@example.com',
    ]))->toThrow(QueryException::class);
});

it('allows a fresh invitation once the previous one was accepted', function () {
    $workspace = Workspace::factory()->create();

    WorkspaceInvitation::factory()->accepted()->create([
        'workspace_id' => $workspace->id,
        'email' => 'new@example.com',
    ]);

    $second = WorkspaceInvitation::factory()->create([
        'workspace_id' => $workspace->id,
        'email' => 'new@example.com',
    ]);

    expect($second->exists)->toBeTrue()
        ->and(WorkspaceInvitation::where('email', 'new@example.com')->count())->toBe(2);
});

it('rejects duplicate user emails', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    expect(fn () => User::factory()->create(['email' => 'taken@example.com']))
        ->toThrow(QueryException::class);
});

it('refuses to physically delete a workspace that still owns records', function () {
    $project = Project::factory()->create();

    expect(fn () => DB::table('workspaces')->where('id', $project->workspace_id)->delete())
        ->toThrow(QueryException::class);
});

it('refuses to physically delete a user who is still assigned to a task', function () {
    $assignee = User::factory()->create();
    Task::factory()->create(['assignee_id' => $assignee->id]);

    expect(fn () => DB::table('users')->where('id', $assignee->id)->delete())
        ->toThrow(QueryException::class);
});
