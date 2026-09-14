<?php

namespace App\Actions\Tasks;

use App\Actions\Activity\RecordActivity;
use App\Enums\ActivityType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class CreateTask
{
    public function __construct(private readonly RecordActivity $activity) {}

    /**
     * project_id and assignee_id are validated against the current tenant by
     * the form request; they are assigned explicitly here because neither is
     * mass assignable (PROJECT_SPEC.md §17).
     *
     * @param  array{title: string, description?: string|null, project_id: string, assignee_id?: string|null, status?: TaskStatus, priority?: TaskPriority, due_date?: string|null}  $attributes
     */
    public function handle(Workspace $workspace, User $actor, array $attributes): Task
    {
        return DB::transaction(function () use ($workspace, $actor, $attributes): Task {
            $task = new Task;
            $task->title = $attributes['title'];
            $task->description = $attributes['description'] ?? null;
            $task->due_date = $attributes['due_date'] ?? null;
            $task->status = $attributes['status'] ?? TaskStatus::Todo;
            $task->priority = $attributes['priority'] ?? TaskPriority::Medium;
            $task->project_id = $attributes['project_id'];
            $task->assignee_id = $attributes['assignee_id'] ?? null;

            // workspace_id comes from the resolved tenant via the relationship,
            // never from request input (PROJECT_SPEC.md §7). The composite
            // foreign key on (project_id, workspace_id) is the database-level
            // backstop for project/workspace consistency.
            $workspace->tasks()->save($task);

            $this->activity->handle(
                $workspace,
                $actor,
                ActivityType::TaskCreated,
                $task,
                ['title' => $task->title],
            );

            return $task;
        });
    }
}
