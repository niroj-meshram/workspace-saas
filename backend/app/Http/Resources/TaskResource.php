<?php

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    /**
     * project_id and assignee_id are exposed because they are fields a client
     * legitimately sends back; workspace_id is not, because it comes from the
     * route (PROJECT_SPEC.md §7).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'priority' => $this->priority->value,
            'due_date' => $this->due_date?->toDateString(),
            'project_id' => $this->project_id,
            'assignee_id' => $this->assignee_id,
            'project' => ProjectResource::make($this->whenLoaded('project')),
            'assignee' => UserResource::make($this->whenLoaded('assignee')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
