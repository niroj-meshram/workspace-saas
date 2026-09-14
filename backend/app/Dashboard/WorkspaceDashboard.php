<?php

namespace App\Dashboard;

use App\Enums\TaskStatus;
use App\Models\Activity;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Collection;

/**
 * Builds the dashboard for one workspace (PROJECT_SPEC.md §24).
 *
 * Deliberately specific to this one endpoint rather than a generic reporting
 * layer. Every query starts from the workspace relationship, so the tenant
 * boundary comes from the resolved context and never from request input
 * (PROJECT_SPEC.md §7), and soft-deleted tasks are excluded by their global
 * scope for free.
 */
class WorkspaceDashboard
{
    /**
     * Enough to fill the panel without turning the dashboard into a task list;
     * the full lists have their own paginated endpoints.
     */
    public const MY_TASKS_LIMIT = 10;

    public const RECENT_ACTIVITY_LIMIT = 10;

    public function for(Workspace $workspace, User $viewer): DashboardData
    {
        return new DashboardData(
            stats: $this->stats($workspace),
            myTasks: $this->myTasks($workspace, $viewer),
            recentActivity: $this->recentActivity($workspace),
        );
    }

    /**
     * Two aggregate queries, no rows loaded: one count for projects and one
     * grouped count covering every task status at once.
     *
     * @return array{projects: int, todo: int, in_progress: int, completed: int}
     */
    private function stats(Workspace $workspace): array
    {
        $byStatus = $workspace->tasks()
            ->toBase()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'projects' => $workspace->projects()->count(),
            'todo' => (int) $byStatus->get(TaskStatus::Todo->value, 0),
            'in_progress' => (int) $byStatus->get(TaskStatus::InProgress->value, 0),
            // "Completed" in the UI is the done status in the data
            // (PROJECT_SPEC.md §8, §24).
            'completed' => (int) $byStatus->get(TaskStatus::Done->value, 0),
        ];
    }

    /**
     * @return Collection<int, Task>
     */
    private function myTasks(Workspace $workspace, User $viewer): Collection
    {
        return $workspace->tasks()
            ->whereBelongsTo($viewer, 'assignee')
            // Eager loaded so rendering the panel does not fan out into a
            // query per task.
            ->with('project')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::MY_TASKS_LIMIT)
            ->get();
    }

    /**
     * @return Collection<int, Activity>
     */
    private function recentActivity(Workspace $workspace): Collection
    {
        return $workspace->activities()
            ->with('user')
            // created_at is stored at second precision, so the id breaks ties
            // within a burst from a single operation.
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::RECENT_ACTIVITY_LIMIT)
            ->get();
    }
}
