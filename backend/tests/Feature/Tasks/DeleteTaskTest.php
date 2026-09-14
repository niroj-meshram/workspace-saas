<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    [$this->workspace, $this->admin] = workspaceWithAdmin();
    $this->member = User::factory()->create();
    memberOf($this->workspace, $this->member);
    $this->project = Project::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->task = Task::factory()->forProject($this->project)->create();
});

it('lets a member delete a task', function () {
    $this->actingAs($this->member)
        ->deleteJson("/api/v1/workspaces/{$this->workspace->id}/tasks/{$this->task->id}")
        ->assertNoContent();

    expect($this->task->fresh()->trashed())->toBeTrue();
});

it('soft deletes rather than erasing the row', function () {
    $this->actingAs($this->member)
        ->deleteJson("/api/v1/workspaces/{$this->workspace->id}/tasks/{$this->task->id}")
        ->assertNoContent();

    expect(Task::find($this->task->id))->toBeNull()
        ->and(Task::withTrashed()->find($this->task->id))->not->toBeNull()
        ->and(DB::table('tasks')->where('id', $this->task->id)->exists())->toBeTrue();
});

it('hides a deleted task from reads and writes', function () {
    $this->actingAs($this->member)
        ->deleteJson("/api/v1/workspaces/{$this->workspace->id}/tasks/{$this->task->id}")
        ->assertNoContent();

    $this->actingAs($this->member)
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/tasks/{$this->task->id}")
        ->assertNotFound();

    $this->actingAs($this->member)
        ->patchJson("/api/v1/workspaces/{$this->workspace->id}/tasks/{$this->task->id}", ['title' => 'x'])
        ->assertNotFound();

    $this->actingAs($this->member)
        ->deleteJson("/api/v1/workspaces/{$this->workspace->id}/tasks/{$this->task->id}")
        ->assertNotFound();
});

it('keeps the project and its other tasks intact', function () {
    $sibling = Task::factory()->forProject($this->project)->create();

    $this->actingAs($this->member)
        ->deleteJson("/api/v1/workspaces/{$this->workspace->id}/tasks/{$this->task->id}")
        ->assertNoContent();

    expect(Project::find($this->project->id))->not->toBeNull()
        ->and(Task::find($sibling->id))->not->toBeNull();
});

it('answers 404 for a non-member', function () {
    $this->actingAs(User::factory()->create())
        ->deleteJson("/api/v1/workspaces/{$this->workspace->id}/tasks/{$this->task->id}")
        ->assertNotFound();

    expect($this->task->fresh()->trashed())->toBeFalse();
});

it('requires authentication', function () {
    $this->deleteJson("/api/v1/workspaces/{$this->workspace->id}/tasks/{$this->task->id}")
        ->assertUnauthorized();

    expect($this->task->fresh()->trashed())->toBeFalse();
});
