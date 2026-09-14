<?php

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

beforeEach(function () {
    [$this->workspace, $this->admin] = workspaceWithAdmin();
    $this->member = User::factory()->create();
    memberOf($this->workspace, $this->member);
    $this->project = Project::factory()->create(['workspace_id' => $this->workspace->id]);
});

it('lets any member create a task', function (WorkspaceRole $role) {
    $actor = User::factory()->create();
    memberOf($this->workspace, $actor, $role);

    $response = $this->actingAs($actor)
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/tasks", [
            'title' => 'Ship it',
            'project_id' => $this->project->id,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Ship it')
        ->assertJsonPath('data.project_id', $this->project->id)
        ->assertJsonPath('data.status', TaskStatus::Todo->value)
        ->assertJsonPath('data.priority', TaskPriority::Medium->value)
        ->assertJsonPath('data.assignee_id', null);

    expect(Task::sole()->workspace_id)->toBe($this->workspace->id);
})->with([
    'admin' => [WorkspaceRole::Admin],
    'user' => [WorkspaceRole::User],
]);

it('accepts every task field', function () {
    $response = $this->actingAs($this->member)
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/tasks", [
            'title' => 'Ship it',
            'description' => 'Before Friday',
            'project_id' => $this->project->id,
            'assignee_id' => $this->member->id,
            'status' => TaskStatus::InProgress->value,
            'priority' => TaskPriority::High->value,
            'due_date' => '2026-12-24',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.description', 'Before Friday')
        ->assertJsonPath('data.assignee_id', $this->member->id)
        ->assertJsonPath('data.status', TaskStatus::InProgress->value)
        ->assertJsonPath('data.priority', TaskPriority::High->value)
        ->assertJsonPath('data.due_date', '2026-12-24');
});

it('returns the documented task shape', function () {
    $response = $this->actingAs($this->member)
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/tasks", [
            'title' => 'Ship it',
            'project_id' => $this->project->id,
        ])
        ->assertCreated();

    expect(array_keys($response->json('data')))->toBe([
        'id', 'title', 'description', 'status', 'priority', 'due_date',
        'project_id', 'assignee_id', 'project', 'assignee', 'created_at', 'updated_at',
    ]);
});

it('requires a title and a project', function (array $payload, string $field) {
    $this->actingAs($this->member)
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/tasks", $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors($field);
})->with([
    'no title' => [fn () => ['project_id' => test()->project->id], 'title'],
    'empty title' => [fn () => ['title' => '', 'project_id' => test()->project->id], 'title'],
    'no project' => [['title' => 'Ship it'], 'project_id'],
    'project not a uuid' => [['title' => 'Ship it', 'project_id' => 'nope'], 'project_id'],
]);

it('rejects an invalid status or priority', function (array $payload, string $field) {
    $this->actingAs($this->member)
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/tasks", array_merge([
            'title' => 'Ship it',
            'project_id' => $this->project->id,
        ], $payload))
        ->assertStatus(422)
        ->assertJsonValidationErrors($field);

    expect(Task::count())->toBe(0);
})->with([
    'unknown status' => [['status' => 'archived'], 'status'],
    'uppercase status' => [['status' => 'TODO'], 'status'],
    'unknown priority' => [['priority' => 'urgent'], 'priority'],
    'numeric priority' => [['priority' => 1], 'priority'],
]);

it('rejects a malformed due date', function () {
    $this->actingAs($this->member)
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/tasks", [
            'title' => 'Ship it',
            'project_id' => $this->project->id,
            'due_date' => '24/12/2026',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('due_date');
});

it('refuses a new task in an archived project', function () {
    $archived = Project::factory()->archived()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->member)
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/tasks", [
            'title' => 'Ship it',
            'project_id' => $archived->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('project_id')
        ->assertJsonPath('errors.project_id.0', 'An archived project cannot receive new tasks.');

    expect(Task::count())->toBe(0);
});

it('refuses a project from another workspace', function () {
    [$other] = workspaceWithAdmin();
    $foreignProject = Project::factory()->create(['workspace_id' => $other->id]);

    $this->actingAs($this->member)
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/tasks", [
            'title' => 'Ship it',
            'project_id' => $foreignProject->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('project_id')
        ->assertJsonPath('errors.project_id.0', 'The selected project does not belong to this workspace.');

    expect(Task::count())->toBe(0);
});

it('refuses an assignee from another workspace', function () {
    [$other, $otherAdmin] = workspaceWithAdmin();

    $this->actingAs($this->member)
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/tasks", [
            'title' => 'Ship it',
            'project_id' => $this->project->id,
            'assignee_id' => $otherAdmin->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('assignee_id')
        ->assertJsonPath('errors.assignee_id.0', 'The assignee must be an active member of this workspace.');

    expect(Task::count())->toBe(0);
});

it('refuses an assignee whose membership was removed', function () {
    $removed = User::factory()->create();
    memberOf($this->workspace, $removed)->delete();

    $this->actingAs($this->member)
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/tasks", [
            'title' => 'Ship it',
            'project_id' => $this->project->id,
            'assignee_id' => $removed->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('assignee_id');
});

it('ignores a workspace_id supplied in the payload', function () {
    [$other] = workspaceWithAdmin();

    $this->actingAs($this->member)
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/tasks", [
            'title' => 'Ship it',
            'project_id' => $this->project->id,
            'workspace_id' => $other->id,
        ])
        ->assertCreated();

    expect(Task::sole()->workspace_id)->toBe($this->workspace->id);
});

it('answers 404 for a non-member', function () {
    $this->actingAs(User::factory()->create())
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/tasks", [
            'title' => 'Ship it',
            'project_id' => $this->project->id,
        ])
        ->assertNotFound();

    expect(Task::count())->toBe(0);
});

it('requires authentication', function () {
    $this->postJson("/api/v1/workspaces/{$this->workspace->id}/tasks", [
        'title' => 'Ship it',
        'project_id' => $this->project->id,
    ])->assertUnauthorized();
});
