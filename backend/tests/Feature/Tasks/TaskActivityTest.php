<?php

use App\Enums\ActivityType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    [$this->workspace, $this->admin] = workspaceWithAdmin();
    $this->member = User::factory()->create();
    memberOf($this->workspace, $this->member);
    $this->project = Project::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->task = Task::factory()->forProject($this->project)->create(['title' => 'Ship it']);
});

function patchForActivity(array $payload)
{
    return test()->actingAs(test()->member)->patchJson(
        '/api/v1/workspaces/'.test()->workspace->id.'/tasks/'.test()->task->id,
        $payload,
    );
}

it('records task.created', function () {
    $id = $this->actingAs($this->member)
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/tasks", [
            'title' => 'New task',
            'project_id' => $this->project->id,
        ])
        ->assertCreated()
        ->json('data.id');

    $activity = Activity::sole();

    expect($activity->type)->toBe(ActivityType::TaskCreated)
        ->and($activity->workspace_id)->toBe($this->workspace->id)
        ->and($activity->user_id)->toBe($this->member->id)
        ->and($activity->subject_type)->toBe('task')
        ->and($activity->subject_id)->toBe($id)
        ->and($activity->metadata)->toEqual(['title' => 'New task']);
});

it('records task.status_changed with old and new values', function () {
    patchForActivity(['status' => TaskStatus::Done->value])->assertOk();

    $activity = Activity::sole();

    expect($activity->type)->toBe(ActivityType::TaskStatusChanged)
        ->and($activity->metadata)->toEqual([
            'old_status' => TaskStatus::Todo->value,
            'new_status' => TaskStatus::Done->value,
        ]);
});

it('records task.priority_changed with old and new values', function () {
    patchForActivity(['priority' => TaskPriority::High->value])->assertOk();

    $activity = Activity::sole();

    expect($activity->type)->toBe(ActivityType::TaskPriorityChanged)
        ->and($activity->metadata)->toEqual([
            'old_priority' => TaskPriority::Medium->value,
            'new_priority' => TaskPriority::High->value,
        ]);
});

it('records task.assigned when a task is assigned', function () {
    patchForActivity(['assignee_id' => $this->member->id])->assertOk();

    $activity = Activity::sole();

    expect($activity->type)->toBe(ActivityType::TaskAssigned)
        ->and($activity->metadata)->toEqual([
            'old_assignee_id' => null,
            'new_assignee_id' => $this->member->id,
        ]);
});

it('records task.assigned when a task is unassigned', function () {
    // Assigned explicitly: assignee_id is not mass assignable, by design
    // (PROJECT_SPEC.md §17).
    $this->task->assignee_id = $this->member->id;
    $this->task->save();

    patchForActivity(['assignee_id' => null])->assertOk();

    expect(Activity::sole()->metadata)->toEqual([
        'old_assignee_id' => $this->member->id,
        'new_assignee_id' => null,
    ]);
});

it('records task.updated for content changes', function () {
    patchForActivity(['title' => 'Ship it later', 'due_date' => '2026-12-24'])->assertOk();

    $activity = Activity::sole();

    expect($activity->type)->toBe(ActivityType::TaskUpdated)
        ->and($activity->metadata)->toEqual([
            'changes' => [
                'title' => ['old' => 'Ship it', 'new' => 'Ship it later'],
                'due_date' => ['old' => null, 'new' => '2026-12-24'],
            ],
        ]);
});

it('records task.deleted', function () {
    $this->actingAs($this->member)
        ->deleteJson("/api/v1/workspaces/{$this->workspace->id}/tasks/{$this->task->id}")
        ->assertNoContent();

    $activity = Activity::sole();

    expect($activity->type)->toBe(ActivityType::TaskDeleted)
        ->and($activity->subject_id)->toBe($this->task->id)
        ->and($activity->metadata)->toEqual(['title' => 'Ship it']);
});

it('still resolves the subject of a deleted task', function () {
    $this->actingAs($this->member)
        ->deleteJson("/api/v1/workspaces/{$this->workspace->id}/tasks/{$this->task->id}")
        ->assertNoContent();

    $subject = Activity::sole()->subject;

    expect($subject)->toBeInstanceOf(Task::class)
        ->and($subject->trashed())->toBeTrue()
        ->and($subject->title)->toBe('Ship it');
});

it('records one activity per kind of change in a single request', function () {
    patchForActivity([
        'title' => 'Renamed',
        'status' => TaskStatus::InProgress->value,
        'priority' => TaskPriority::High->value,
        'assignee_id' => $this->member->id,
    ])->assertOk();

    expect(Activity::pluck('type')->map->value->sort()->values()->all())->toBe([
        ActivityType::TaskAssigned->value,
        ActivityType::TaskPriorityChanged->value,
        ActivityType::TaskStatusChanged->value,
        ActivityType::TaskUpdated->value,
    ]);
});

it('records nothing when a request changes nothing', function () {
    patchForActivity(['title' => 'Ship it', 'status' => TaskStatus::Todo->value])->assertOk();

    expect(Activity::count())->toBe(0);
});

it('records nothing for an empty patch', function () {
    patchForActivity([])->assertOk();

    expect(Activity::count())->toBe(0);
});

it('records nothing when the request is rejected', function () {
    $this->actingAs(User::factory()->create())
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/tasks", [
            'title' => 'Nope',
            'project_id' => $this->project->id,
        ])
        ->assertNotFound();

    patchForActivity(['status' => 'cancelled'])->assertStatus(422);

    expect(Activity::count())->toBe(0);
});

it('rolls the activity back with the task when the write fails', function () {
    DB::listen(function ($query) {
        if (str_contains($query->sql, 'insert into "activities"')) {
            throw new RuntimeException('activity insert failed');
        }
    });

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($this->member)
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/tasks", [
            'title' => 'Doomed',
            'project_id' => $this->project->id,
        ]))
        ->toThrow(RuntimeException::class, 'activity insert failed');

    expect(Task::where('title', 'Doomed')->count())->toBe(0)
        ->and(Activity::count())->toBe(0);
});

it('keeps task activity inside its own workspace', function () {
    [$other] = workspaceWithAdmin();

    patchForActivity(['status' => TaskStatus::Done->value])->assertOk();

    expect(Activity::where('workspace_id', $other->id)->count())->toBe(0)
        ->and(Activity::where('workspace_id', $this->workspace->id)->count())->toBe(1);
});
