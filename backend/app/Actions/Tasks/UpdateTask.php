<?php

namespace App\Actions\Tasks;

use App\Actions\Activity\RecordActivity;
use App\Enums\ActivityType;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

/**
 * Applies a PATCH and records what actually changed.
 *
 * Status, priority and assignee each get their own activity type with old/new
 * values (PROJECT_SPEC.md §9); everything else a client can edit is collapsed
 * into a single task.updated entry. One request can therefore produce several
 * activities, and a request that changes nothing produces none.
 */
class UpdateTask
{
    /**
     * Fields a client may edit that do not have a dedicated activity type.
     *
     * @var list<string>
     */
    private const CONTENT_FIELDS = ['title', 'description', 'due_date', 'project_id'];

    public function __construct(private readonly RecordActivity $activity) {}

    /**
     * @param  array<string, mixed>  $attributes  validated changes, keyed by column
     */
    public function handle(Workspace $workspace, User $actor, Task $task, array $attributes): Task
    {
        return DB::transaction(function () use ($workspace, $actor, $task, $attributes): Task {
            // Snapshot through the cast accessors so before/after are directly
            // comparable; getOriginal() would hand back raw column values.
            $before = $this->snapshot($task);

            foreach (['title', 'description', 'due_date', 'status', 'priority', 'project_id', 'assignee_id'] as $field) {
                if (array_key_exists($field, $attributes)) {
                    $task->{$field} = $attributes[$field];
                }
            }

            if (! $task->isDirty()) {
                return $task;
            }

            $task->save();

            $after = $this->snapshot($task);

            $this->recordTransitions($workspace, $actor, $task, $before, $after);

            return $task;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Task $task): array
    {
        return [
            'title' => $task->title,
            'description' => $task->description,
            'due_date' => $task->due_date?->toDateString(),
            'project_id' => $task->project_id,
            'status' => $task->status,
            'priority' => $task->priority,
            'assignee_id' => $task->assignee_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function recordTransitions(
        Workspace $workspace,
        User $actor,
        Task $task,
        array $before,
        array $after,
    ): void {
        if ($before['status'] !== $after['status']) {
            $this->record($workspace, $actor, $task, ActivityType::TaskStatusChanged, [
                'old_status' => $before['status']->value,
                'new_status' => $after['status']->value,
            ]);
        }

        if ($before['priority'] !== $after['priority']) {
            $this->record($workspace, $actor, $task, ActivityType::TaskPriorityChanged, [
                'old_priority' => $before['priority']->value,
                'new_priority' => $after['priority']->value,
            ]);
        }

        // Covers unassignment too: new_assignee_id is simply null.
        if ($before['assignee_id'] !== $after['assignee_id']) {
            $this->record($workspace, $actor, $task, ActivityType::TaskAssigned, [
                'old_assignee_id' => $before['assignee_id'],
                'new_assignee_id' => $after['assignee_id'],
            ]);
        }

        $changes = [];

        foreach (self::CONTENT_FIELDS as $field) {
            if ($before[$field] !== $after[$field]) {
                $changes[$field] = ['old' => $before[$field], 'new' => $after[$field]];
            }
        }

        if ($changes !== []) {
            $this->record($workspace, $actor, $task, ActivityType::TaskUpdated, ['changes' => $changes]);
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function record(
        Workspace $workspace,
        User $actor,
        Task $task,
        ActivityType $type,
        array $metadata,
    ): void {
        $this->activity->handle($workspace, $actor, $type, $task, $metadata);
    }
}
