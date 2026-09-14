<?php

namespace App\Actions\Projects;

use App\Actions\Activity\RecordActivity;
use App\Enums\ActivityType;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class CreateProject
{
    public function __construct(private readonly RecordActivity $activity) {}

    /**
     * Projects are always created active; archiving is a later transition
     * (PROJECT_SPEC.md §8).
     *
     * The project and its activity record are written together so history can
     * never describe a project that was rolled back.
     */
    public function handle(
        Workspace $workspace,
        User $actor,
        string $name,
        ?string $description = null,
    ): Project {
        return DB::transaction(function () use ($workspace, $actor, $name, $description): Project {
            $project = new Project;
            $project->name = $name;
            $project->description = $description;
            $project->status = ProjectStatus::Active;

            // workspace_id comes from the resolved tenant via the relationship,
            // never from request input (PROJECT_SPEC.md §7).
            $workspace->projects()->save($project);

            $this->activity->handle(
                $workspace,
                $actor,
                ActivityType::ProjectCreated,
                $project,
                ['name' => $project->name],
            );

            return $project;
        });
    }
}
