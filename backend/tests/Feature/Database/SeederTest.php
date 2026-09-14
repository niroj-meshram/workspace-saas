<?php

use App\Enums\ProjectStatus;
use App\Enums\WorkspaceRole;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Database\Seeders\DemoWorkspaceSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/*
|--------------------------------------------------------------------------
| The demo seeder
|--------------------------------------------------------------------------
|
| Seeders rot quietly, because nothing runs them. These tests run it.
|
*/

beforeEach(function () {
    Mail::fake();
    $this->seed(DemoWorkspaceSeeder::class);
});

it('creates sign-in-able accounts', function () {
    $admin = User::where('email', DemoWorkspaceSeeder::PRIMARY_EMAIL)->sole();

    expect(Hash::check(DemoWorkspaceSeeder::PASSWORD, $admin->password))->toBeTrue()
        ->and(User::count())->toBe(3);
});

it('creates two workspaces with the primary account in both', function () {
    $admin = User::where('email', DemoWorkspaceSeeder::PRIMARY_EMAIL)->sole();

    expect(Workspace::count())->toBe(2)
        ->and($admin->workspaces()->pluck('name')->sort()->values()->all())
        ->toBe(['Acme Product', 'Internal Tools']);
});

it('gives the primary account a different role in each workspace', function () {
    // The point of the second workspace: the member-facing UI is reachable
    // just by switching.
    $admin = User::where('email', DemoWorkspaceSeeder::PRIMARY_EMAIL)->sole();

    $roles = $admin->memberships()
        ->with('workspace')
        ->get()
        ->mapWithKeys(fn ($member) => [$member->workspace->name => $member->role]);

    expect($roles['Acme Product'])->toBe(WorkspaceRole::Admin)
        ->and($roles['Internal Tools'])->toBe(WorkspaceRole::User);
});

it('creates projects in both states', function () {
    expect(Project::where('status', ProjectStatus::Active)->count())->toBeGreaterThan(0)
        ->and(Project::where('status', ProjectStatus::Archived)->count())->toBe(1);
});

it('creates tasks across every status and priority', function () {
    expect(Task::distinct()->pluck('status')->count())->toBe(4)
        ->and(Task::distinct()->pluck('priority')->count())->toBe(3);
});

it('leaves every task in the same workspace as its project', function () {
    $mismatched = Task::with('project')
        ->get()
        ->filter(fn (Task $task) => $task->workspace_id !== $task->project->workspace_id);

    expect($mismatched)->toBeEmpty();
});

it('only assigns tasks to members of the same workspace', function () {
    $assigned = Task::whereNotNull('assignee_id')->with('workspace.members')->get();

    expect($assigned)->not->toBeEmpty();

    foreach ($assigned as $task) {
        $memberIds = $task->workspace->members->pluck('user_id');

        expect($memberIds)->toContain($task->assignee_id);
    }
});

it('leaves a pending invitation that reaches nobody real', function () {
    $invitation = WorkspaceInvitation::sole();

    expect($invitation->isPending())->toBeTrue()
        // RFC 2606 reserves example.com, so this cannot be delivered.
        ->and($invitation->email)->toEndWith('@example.com')
        ->and($invitation->token_hash)->toMatch('/^[0-9a-f]{64}$/');
});

it('produces an activity feed rather than empty screens', function () {
    $types = Activity::distinct()->pluck('type')->map->value;

    expect(Activity::count())->toBeGreaterThan(10)
        ->and($types)->toContain('project.created')
        ->and($types)->toContain('project.archived')
        ->and($types)->toContain('task.created')
        ->and($types)->toContain('task.status_changed')
        ->and($types)->toContain('member.invited')
        ->and($types)->toContain('member.role_changed');
});

it('records every activity inside a workspace', function () {
    expect(Activity::whereNull('workspace_id')->count())->toBe(0);
});

it('is safe to run twice', function () {
    $this->seed(DemoWorkspaceSeeder::class);

    expect(User::count())->toBe(3)
        ->and(Workspace::count())->toBe(2);
});
