<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Cross-tenant access for tasks (PROJECT_SPEC.md §17)
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    [$this->workspaceA, $this->adminA] = workspaceWithAdmin();
    [$this->workspaceB, $this->adminB] = workspaceWithAdmin();

    $this->projectB = Project::factory()->create(['workspace_id' => $this->workspaceB->id]);
    $this->taskB = Task::factory()->forProject($this->projectB)->create(['title' => 'Their task']);
});

it('blocks listing another workspace\'s tasks', function () {
    $this->actingAs($this->adminA)
        ->getJson("/api/v1/workspaces/{$this->workspaceB->id}/tasks")
        ->assertNotFound();
});

it('blocks reading a task in another workspace', function () {
    $this->actingAs($this->adminA)
        ->getJson("/api/v1/workspaces/{$this->workspaceB->id}/tasks/{$this->taskB->id}")
        ->assertNotFound();
});

it('blocks creating a task in another workspace', function () {
    $this->actingAs($this->adminA)
        ->postJson("/api/v1/workspaces/{$this->workspaceB->id}/tasks", [
            'title' => 'Injected',
            'project_id' => $this->projectB->id,
        ])
        ->assertNotFound();

    expect(Task::where('title', 'Injected')->count())->toBe(0);
});

it('blocks updating a task in another workspace', function () {
    $this->actingAs($this->adminA)
        ->patchJson("/api/v1/workspaces/{$this->workspaceB->id}/tasks/{$this->taskB->id}", [
            'title' => 'Hijacked',
        ])
        ->assertNotFound();

    expect($this->taskB->fresh()->title)->toBe('Their task');
});

it('blocks deleting a task in another workspace', function () {
    $this->actingAs($this->adminA)
        ->deleteJson("/api/v1/workspaces/{$this->workspaceB->id}/tasks/{$this->taskB->id}")
        ->assertNotFound();

    expect($this->taskB->fresh()->trashed())->toBeFalse();
});

it('blocks addressing another workspace\'s task through your own workspace', function () {
    $this->actingAs($this->adminA)
        ->getJson("/api/v1/workspaces/{$this->workspaceA->id}/tasks/{$this->taskB->id}")
        ->assertNotFound();

    $this->actingAs($this->adminA)
        ->patchJson("/api/v1/workspaces/{$this->workspaceA->id}/tasks/{$this->taskB->id}", [
            'title' => 'Hijacked',
        ])
        ->assertNotFound();

    $this->actingAs($this->adminA)
        ->deleteJson("/api/v1/workspaces/{$this->workspaceA->id}/tasks/{$this->taskB->id}")
        ->assertNotFound();

    $this->taskB->refresh();

    expect($this->taskB->title)->toBe('Their task')
        ->and($this->taskB->trashed())->toBeFalse();
});

it('answers a missing task and an inaccessible one identically', function () {
    $missing = $this->actingAs($this->adminA)
        ->getJson("/api/v1/workspaces/{$this->workspaceA->id}/tasks/".Str::uuid()->toString());

    $inaccessible = $this->actingAs($this->adminA)
        ->getJson("/api/v1/workspaces/{$this->workspaceA->id}/tasks/{$this->taskB->id}");

    $missing->assertNotFound();
    $inaccessible->assertNotFound();

    expect($missing->json())->toBe($inaccessible->json());
});

it('stops granting task access once the membership is removed', function () {
    $user = User::factory()->create();
    $member = memberOf($this->workspaceA, $user);
    $project = Project::factory()->create(['workspace_id' => $this->workspaceA->id]);
    $task = Task::factory()->forProject($project)->create();

    $this->actingAs($user)
        ->getJson("/api/v1/workspaces/{$this->workspaceA->id}/tasks/{$task->id}")
        ->assertOk();

    $member->delete();

    $this->actingAs($user)
        ->getJson("/api/v1/workspaces/{$this->workspaceA->id}/tasks/{$task->id}")
        ->assertNotFound();
});

it('keeps the database from accepting a cross-tenant project even if validation is bypassed', function () {
    // Defence in depth: the composite foreign key on (project_id, workspace_id)
    // refuses the write regardless of what the application layer does.
    expect(fn () => Task::factory()->create([
        'workspace_id' => $this->workspaceA->id,
        'project_id' => $this->projectB->id,
    ]))->toThrow(QueryException::class);
});
