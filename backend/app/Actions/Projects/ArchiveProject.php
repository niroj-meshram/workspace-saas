<?php

namespace App\Actions\Projects;

use App\Actions\Activity\RecordActivity;
use App\Enums\ActivityType;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class ArchiveProject
{
    public function __construct(private readonly RecordActivity $activity) {}

    /**
     * Archiving is not deletion (PROJECT_SPEC.md §11). The row stays, stays
     * readable, and keeps its tasks and activity; only new tasks are refused
     * (enforced in Phase 7).
     *
     * Idempotent: archiving an already archived project changes nothing and
     * records no second activity.
     */
    public function handle(Workspace $workspace, User $actor, Project $project): Project
    {
        if ($project->status === ProjectStatus::Archived) {
            return $project;
        }

        return DB::transaction(function () use ($workspace, $actor, $project): Project {
            $previous = $project->status;

            $project->status = ProjectStatus::Archived;
            $project->save();

            $this->activity->handle(
                $workspace,
                $actor,
                ActivityType::ProjectArchived,
                $project,
                [
                    'old_status' => $previous->value,
                    'new_status' => ProjectStatus::Archived->value,
                ],
            );

            return $project;
        });
    }
}
