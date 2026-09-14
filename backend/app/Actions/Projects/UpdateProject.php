<?php

namespace App\Actions\Projects;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * A PATCH may rename a project and archive it in the same request, so the whole
 * update is one transaction. The archive transition is delegated rather than
 * applied here, so it is recorded in the activity log exactly once no matter
 * which endpoint triggered it.
 */
class UpdateProject
{
    public function __construct(private readonly ArchiveProject $archive) {}

    /**
     * @param  array{name?: string, description?: string|null, status?: ProjectStatus}  $attributes
     */
    public function handle(Workspace $workspace, User $actor, Project $project, array $attributes): Project
    {
        return DB::transaction(function () use ($workspace, $actor, $project, $attributes): Project {
            // Only the editable content fields are mass assigned; status is
            // routed through the transition below so it cannot skip the
            // activity record.
            $project->fill(Arr::only($attributes, ['name', 'description']));

            $status = $attributes['status'] ?? null;

            if ($status === ProjectStatus::Active) {
                $project->status = ProjectStatus::Active;
            }

            if ($project->isDirty()) {
                $project->save();
            }

            if ($status === ProjectStatus::Archived) {
                $this->archive->handle($workspace, $actor, $project);
            }

            return $project;
        });
    }
}
