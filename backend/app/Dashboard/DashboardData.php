<?php

namespace App\Dashboard;

use App\Models\Activity;
use App\Models\Task;
use Illuminate\Support\Collection;

/**
 * The assembled dashboard for one workspace and one viewer
 * (PROJECT_SPEC.md §12, §24).
 *
 * A plain read model: it carries what the endpoint returns and nothing else.
 */
readonly class DashboardData
{
    /**
     * @param  array{projects: int, todo: int, in_progress: int, completed: int}  $stats
     * @param  Collection<int, Task>  $myTasks
     * @param  Collection<int, Activity>  $recentActivity
     */
    public function __construct(
        public array $stats,
        public Collection $myTasks,
        public Collection $recentActivity,
    ) {}
}
