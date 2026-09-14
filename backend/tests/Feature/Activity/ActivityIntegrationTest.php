<?php

use App\Enums\ActivityType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\WorkspaceRole;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use Illuminate\Support\Facades\Mail;

/*
|--------------------------------------------------------------------------
| Every module records through the one Activity boundary
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    Mail::fake();
    [$this->workspace, $this->admin] = workspaceWithAdmin();
    $this->member = User::factory()->create();
    $this->membership = memberOf($this->workspace, $this->member);
    $this->project = Project::factory()->create(['workspace_id' => $this->workspace->id]);
});

function typesRecorded(): array
{
    return Activity::orderBy('created_at')->orderBy('id')->pluck('type')->map->value->all();
}

it('records the whole lifecycle of a workspace through the API', function () {
    // Project created + archived.
    $projectId = $this->actingAs($this->admin)
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/projects", ['name' => 'Launch'])
        ->assertCreated()
        ->json('data.id');

    // Task created, then every kind of change, then deleted.
    $taskId = $this->actingAs($this->member)
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/tasks", [
            'title' => 'Ship it',
            'project_id' => $projectId,
        ])
        ->assertCreated()
        ->json('data.id');

    $this->actingAs($this->member)
        ->patchJson("/api/v1/workspaces/{$this->workspace->id}/tasks/{$taskId}", [
            'title' => 'Ship it later',
            'status' => TaskStatus::InProgress->value,
            'priority' => TaskPriority::High->value,
            'assignee_id' => $this->member->id,
        ])
        ->assertOk();

    $this->actingAs($this->member)
        ->deleteJson("/api/v1/workspaces/{$this->workspace->id}/tasks/{$taskId}")
        ->assertNoContent();

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/workspaces/{$this->workspace->id}/projects/{$projectId}")
        ->assertNoContent();

    // Member invited, role changed, removed.
    $this->actingAs($this->admin)
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/invitations", [
            'email' => 'invitee@example.com',
            'role' => 'user',
        ])
        ->assertCreated();

    $this->actingAs($this->admin)
        ->patchJson("/api/v1/workspaces/{$this->workspace->id}/members/{$this->membership->id}", [
            'role' => WorkspaceRole::Admin->value,
        ])
        ->assertOk();

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/workspaces/{$this->workspace->id}/members/{$this->membership->id}")
        ->assertNoContent();

    expect(typesRecorded())->toBe([
        ActivityType::ProjectCreated->value,
        ActivityType::TaskCreated->value,
        ActivityType::TaskStatusChanged->value,
        ActivityType::TaskPriorityChanged->value,
        ActivityType::TaskAssigned->value,
        ActivityType::TaskUpdated->value,
        ActivityType::TaskDeleted->value,
        ActivityType::ProjectArchived->value,
        ActivityType::MemberInvited->value,
        ActivityType::MemberRoleChanged->value,
        ActivityType::MemberRemoved->value,
    ]);

    // Every one of them belongs to this workspace and nothing else.
    expect(Activity::where('workspace_id', '!=', $this->workspace->id)->count())->toBe(0);
});

it('records member.role_changed with the old and new role', function () {
    $this->actingAs($this->admin)
        ->patchJson("/api/v1/workspaces/{$this->workspace->id}/members/{$this->membership->id}", [
            'role' => WorkspaceRole::Admin->value,
        ])
        ->assertOk();

    $activity = Activity::sole();

    expect($activity->type)->toBe(ActivityType::MemberRoleChanged)
        ->and($activity->user_id)->toBe($this->admin->id)
        ->and($activity->subject_type)->toBe('workspace_member')
        ->and($activity->subject_id)->toBe($this->membership->id)
        ->and($activity->metadata)->toEqual([
            'user_id' => $this->member->id,
            'old_role' => WorkspaceRole::User->value,
            'new_role' => WorkspaceRole::Admin->value,
        ]);
});

it('records nothing when a role change is a no-op', function () {
    $this->actingAs($this->admin)
        ->patchJson("/api/v1/workspaces/{$this->workspace->id}/members/{$this->membership->id}", [
            'role' => WorkspaceRole::User->value,
        ])
        ->assertOk();

    expect(Activity::count())->toBe(0);
});

it('records member.removed with who was removed', function () {
    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/workspaces/{$this->workspace->id}/members/{$this->membership->id}")
        ->assertNoContent();

    $activity = Activity::sole();

    expect($activity->type)->toBe(ActivityType::MemberRemoved)
        ->and($activity->subject_id)->toBe($this->membership->id)
        ->and($activity->metadata)->toEqual([
            'user_id' => $this->member->id,
            'role' => WorkspaceRole::User->value,
        ]);
});

it('keeps a removed member\'s activity and still resolves its subject', function () {
    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/workspaces/{$this->workspace->id}/members/{$this->membership->id}")
        ->assertNoContent();

    $activity = Activity::sole();

    expect($activity->subject)->not->toBeNull()
        ->and($activity->subject->is($this->membership))->toBeTrue()
        ->and($activity->subject->trashed())->toBeTrue();
});

it('keeps task activity after the task is soft deleted', function () {
    $task = Task::factory()->forProject($this->project)->create();

    $this->actingAs($this->member)
        ->deleteJson("/api/v1/workspaces/{$this->workspace->id}/tasks/{$task->id}")
        ->assertNoContent();

    $activity = Activity::where('type', ActivityType::TaskDeleted)->sole();

    expect(Task::find($task->id))->toBeNull()
        ->and($activity->subject)->not->toBeNull()
        ->and($activity->subject->trashed())->toBeTrue();

    // And it is still served by the feed.
    $this->actingAs($this->admin)
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/activities?type=".ActivityType::TaskDeleted->value)
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('keeps invitation activity after the invitation is revoked', function () {
    $invitation = WorkspaceInvitation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'invited_by' => $this->admin->id,
    ]);
    $activity = Activity::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->admin->id,
        'type' => ActivityType::MemberInvited,
        'subject_type' => 'workspace_invitation',
        'subject_id' => $invitation->id,
    ]);

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/workspaces/{$this->workspace->id}/invitations/{$invitation->id}")
        ->assertNoContent();

    $activity->refresh();

    expect($activity->subject)->not->toBeNull()
        ->and($activity->subject->trashed())->toBeTrue();
});

it('records an accepted invitation against the accepting user', function () {
    $token = bin2hex(random_bytes(32));
    $invitee = User::factory()->create(['email' => 'invitee@example.com']);

    WorkspaceInvitation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'email' => 'invitee@example.com',
        'invited_by' => $this->admin->id,
        'token_hash' => WorkspaceInvitation::hashToken($token),
    ]);

    $this->actingAs($invitee)->postJson("/api/v1/invitations/{$token}/accept")->assertCreated();

    $activity = Activity::where('type', ActivityType::InvitationAccepted)->sole();

    expect($activity->workspace_id)->toBe($this->workspace->id)
        ->and($activity->user_id)->toBe($invitee->id);
});
