<?php

use App\Enums\ActivityType;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\WorkspaceRole;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

it('casts statuses, roles, dates and metadata', function () {
    $member = WorkspaceMember::factory()->admin()->create();
    $project = Project::factory()->archived()->create();
    $task = Task::factory()->create([
        'status' => TaskStatus::Blocked,
        'priority' => TaskPriority::High,
        'due_date' => '2026-12-24',
    ]);
    $invitation = WorkspaceInvitation::factory()->create();
    $activity = Activity::factory()->create([
        'type' => ActivityType::TaskStatusChanged,
        'metadata' => ['old_status' => 'todo', 'new_status' => 'done'],
    ]);

    expect($member->role)->toBe(WorkspaceRole::Admin)
        ->and($project->status)->toBe(ProjectStatus::Archived)
        ->and($task->status)->toBe(TaskStatus::Blocked)
        ->and($task->priority)->toBe(TaskPriority::High)
        ->and($task->due_date->toDateString())->toBe('2026-12-24')
        ->and($invitation->role)->toBe(WorkspaceRole::User)
        ->and($invitation->expires_at)->toBeInstanceOf(DateTimeInterface::class)
        ->and($activity->type)->toBe(ActivityType::TaskStatusChanged)
        ->and($activity->metadata)->toEqual(['old_status' => 'todo', 'new_status' => 'done']);
});

it('reads json metadata back from the database', function () {
    $activity = Activity::factory()->create([
        'metadata' => ['old_status' => 'todo', 'new_status' => 'done'],
    ]);

    // toEqual rather than toBe: jsonb normalises objects and does not preserve
    // key insertion order.
    expect($activity->fresh()->metadata)->toEqual(['old_status' => 'todo', 'new_status' => 'done']);
});

it('hides sensitive attributes from serialization', function () {
    $user = User::factory()->create();
    $invitation = WorkspaceInvitation::factory()->create();

    expect($user->toArray())->not->toHaveKey('password')
        ->and($invitation->toArray())->not->toHaveKey('token_hash');
});

it('soft deletes the entities the specification marks as deletable', function () {
    foreach ([User::class, Workspace::class, WorkspaceMember::class, Task::class, WorkspaceInvitation::class] as $model) {
        expect(in_array(SoftDeletes::class, class_uses_recursive($model), true))
            ->toBeTrue("{$model} should use SoftDeletes");
    }
});

it('does not soft delete projects or activities', function () {
    expect(in_array(SoftDeletes::class, class_uses_recursive(Project::class), true))->toBeFalse()
        ->and(in_array(SoftDeletes::class, class_uses_recursive(Activity::class), true))->toBeFalse()
        ->and(Schema::hasColumn('projects', 'deleted_at'))->toBeFalse()
        ->and(Schema::hasColumn('activities', 'deleted_at'))->toBeFalse();
});

it('does not track updated_at on activities', function () {
    expect(Schema::hasColumn('activities', 'updated_at'))->toBeFalse()
        ->and(Activity::factory()->create()->exists)->toBeTrue();
});

it('keeps child records when a workspace is soft deleted', function () {
    $workspace = Workspace::factory()->create();
    $project = Project::factory()->create(['workspace_id' => $workspace->id]);
    $task = Task::factory()->forProject($project)->create();
    $activity = Activity::factory()->create(['workspace_id' => $workspace->id]);

    $workspace->delete();

    expect($workspace->fresh()->trashed())->toBeTrue()
        ->and(Project::find($project->id))->not->toBeNull()
        ->and(Task::find($task->id))->not->toBeNull()
        ->and(Activity::find($activity->id))->not->toBeNull();
});

it('retains activities and resolves their subject after the subject is soft deleted', function () {
    $task = Task::factory()->create();
    $activity = Activity::factory()->forSubject($task)->create([
        'workspace_id' => $task->workspace_id,
        'type' => ActivityType::TaskCreated,
    ]);

    $task->delete();

    $activity = $activity->fresh()->load('subject');

    expect($activity)->not->toBeNull()
        ->and($activity->subject_type)->toBe('task')
        ->and($activity->subject)->not->toBeNull()
        ->and($activity->subject->is($task))->toBeTrue()
        ->and($activity->subject->trashed())->toBeTrue();
});

it('stores a morph alias rather than a class name in subject_type', function () {
    $project = Project::factory()->create();

    $activity = Activity::factory()->forSubject($project)->create([
        'workspace_id' => $project->workspace_id,
    ]);

    expect($activity->subject_type)->toBe('project');
});

it('resolves workspace relationships', function () {
    $workspace = Workspace::factory()->create();
    $admin = User::factory()->create();
    WorkspaceMember::factory()->admin()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $admin->id,
    ]);
    $project = Project::factory()->create(['workspace_id' => $workspace->id]);
    $task = Task::factory()->forProject($project)->create(['assignee_id' => $admin->id]);
    WorkspaceInvitation::factory()->create([
        'workspace_id' => $workspace->id,
        'invited_by' => $admin->id,
    ]);
    Activity::factory()->forSubject($project)->create([
        'workspace_id' => $workspace->id,
        'user_id' => $admin->id,
    ]);

    expect($workspace->members)->toHaveCount(1)
        ->and($workspace->users->first()->is($admin))->toBeTrue()
        ->and($workspace->users->first()->pivot->role)->toBe(WorkspaceRole::Admin->value)
        ->and($workspace->projects)->toHaveCount(1)
        ->and($workspace->tasks)->toHaveCount(1)
        ->and($workspace->invitations)->toHaveCount(1)
        ->and($workspace->activities)->toHaveCount(1)
        ->and($project->tasks->first()->is($task))->toBeTrue()
        ->and($task->assignee->is($admin))->toBeTrue()
        ->and($task->workspace->is($workspace))->toBeTrue();
});

it('resolves user relationships', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
    ]);
    Task::factory()->create(['assignee_id' => $user->id]);
    WorkspaceInvitation::factory()->create(['invited_by' => $user->id]);

    expect($user->memberships)->toHaveCount(1)
        ->and($user->workspaces)->toHaveCount(1)
        ->and($user->workspaces->first()->is($workspace))->toBeTrue()
        ->and($user->assignedTasks)->toHaveCount(1)
        ->and($user->sentInvitations)->toHaveCount(1);
});

it('excludes soft deleted memberships from the workspaces relationship', function () {
    $user = User::factory()->create();
    $member = WorkspaceMember::factory()->create(['user_id' => $user->id]);

    expect($user->workspaces()->count())->toBe(1);

    $member->delete();

    expect($user->workspaces()->count())->toBe(0);
});

it('leaves assignee null when no one is assigned', function () {
    $task = Task::factory()->create();

    expect($task->assignee_id)->toBeNull()
        ->and($task->assignee)->toBeNull();
});
