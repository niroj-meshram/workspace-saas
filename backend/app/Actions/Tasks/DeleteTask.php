<?php

namespace App\Actions\Tasks;

use App\Actions\Activity\RecordActivity;
use App\Enums\ActivityType;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class DeleteTask
{
    public function __construct(private readonly RecordActivity $activity) {}

    /**
     * Soft delete: the row stays, drops out of every normal query through the
     * soft-delete scope, and its history is retained (PROJECT_SPEC.md §11).
     * The activity keeps resolving the task afterwards because Activity's
     * subject relation includes trashed records (PROJECT_SPEC.md §9).
     */
    public function handle(Workspace $workspace, User $actor, Task $task): void
    {
        DB::transaction(function () use ($workspace, $actor, $task): void {
            $this->activity->handle(
                $workspace,
                $actor,
                ActivityType::TaskDeleted,
                $task,
                ['title' => $task->title],
            );

            $task->delete();
        });
    }
}
