<?php

use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

beforeEach(function () {
    [$this->workspace, $this->admin] = workspaceWithAdmin();
    $this->member = User::factory()->create();
    memberOf($this->workspace, $this->member);
    $this->project = Project::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->task = Task::factory()->forProject($this->project)->create(['title' => 'Ship it']);
});

function patchTask(mixed $actor, array $payload)
{
    return test()->actingAs($actor)->patchJson(
        '/api/v1/workspaces/'.test()->workspace->id.'/tasks/'.test()->task->id,
        $payload,
    );
}

it('lets a member update a task', function () {
    patchTask($this->member, ['title' => 'Ship it later', 'description' => 'Next sprint'])
        ->assertOk()
        ->assertJsonPath('data.title', 'Ship it later')
        ->assertJsonPath('data.description', 'Next sprint');

    $this->task->refresh();

    expect($this->task->title)->toBe('Ship it later')
        ->and($this->task->description)->toBe('Next sprint');
});

it('lets a member change the status', function () {
    patchTask($this->member, ['status' => TaskStatus::Done->value])
        ->assertOk()
        ->assertJsonPath('data.status', TaskStatus::Done->value);

    expect($this->task->fresh()->status)->toBe(TaskStatus::Done);
});

it('lets a member change the priority', function () {
    patchTask($this->member, ['priority' => TaskPriority::High->value])
        ->assertOk()
        ->assertJsonPath('data.priority', TaskPriority::High->value);

    expect($this->task->fresh()->priority)->toBe(TaskPriority::High);
});

it('lets a member assign and unassign a task', function () {
    patchTask($this->member, ['assignee_id' => $this->member->id])
        ->assertOk()
        ->assertJsonPath('data.assignee_id', $this->member->id);

    patchTask($this->member, ['assignee_id' => null])
        ->assertOk()
        ->assertJsonPath('data.assignee_id', null);

    expect($this->task->fresh()->assignee_id)->toBeNull();
});

it('leaves untouched fields alone', function () {
    $this->task->update(['description' => 'keep me', 'priority' => TaskPriority::High]);

    patchTask($this->member, ['title' => 'Renamed'])->assertOk();

    $this->task->refresh();

    expect($this->task->description)->toBe('keep me')
        ->and($this->task->priority)->toBe(TaskPriority::High);
});

it('lets a member move a task to another project in the same workspace', function () {
    $other = Project::factory()->create(['workspace_id' => $this->workspace->id]);

    patchTask($this->member, ['project_id' => $other->id])
        ->assertOk()
        ->assertJsonPath('data.project_id', $other->id);
});

it('refuses moving a task into an archived project', function () {
    $archived = Project::factory()->archived()->create(['workspace_id' => $this->workspace->id]);

    patchTask($this->member, ['project_id' => $archived->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('project_id');

    expect($this->task->fresh()->project_id)->toBe($this->project->id);
});

it('still allows editing a task whose project was archived afterwards', function () {
    $this->project->update(['status' => ProjectStatus::Archived]);

    patchTask($this->member, ['status' => TaskStatus::Done->value])->assertOk();

    expect($this->task->fresh()->status)->toBe(TaskStatus::Done);
});

it('refuses a project from another workspace', function () {
    [$other] = workspaceWithAdmin();
    $foreign = Project::factory()->create(['workspace_id' => $other->id]);

    patchTask($this->member, ['project_id' => $foreign->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('project_id');

    expect($this->task->fresh()->project_id)->toBe($this->project->id);
});

it('refuses an assignee from another workspace', function () {
    [$other, $otherAdmin] = workspaceWithAdmin();

    patchTask($this->member, ['assignee_id' => $otherAdmin->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('assignee_id');

    expect($this->task->fresh()->assignee_id)->toBeNull();
});

it('rejects an invalid status or priority', function (array $payload, string $field) {
    patchTask($this->member, $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors($field);
})->with([
    'unknown status' => [['status' => 'cancelled'], 'status'],
    'null status' => [['status' => null], 'status'],
    'unknown priority' => [['priority' => 'urgent'], 'priority'],
]);

it('ignores a workspace_id supplied in the payload', function () {
    [$other] = workspaceWithAdmin();

    patchTask($this->member, ['title' => 'Renamed', 'workspace_id' => $other->id])->assertOk();

    expect($this->task->fresh()->workspace_id)->toBe($this->workspace->id);
});

it('answers 404 for a non-member', function () {
    patchTask(User::factory()->create(), ['title' => 'Hijacked'])->assertNotFound();

    expect($this->task->fresh()->title)->toBe('Ship it');
});

it('requires authentication', function () {
    $this->patchJson("/api/v1/workspaces/{$this->workspace->id}/tasks/{$this->task->id}", [
        'title' => 'x',
    ])->assertUnauthorized();
});
