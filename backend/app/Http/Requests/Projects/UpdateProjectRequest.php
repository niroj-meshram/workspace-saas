<?php

namespace App\Http\Requests\Projects;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Tenancy\WorkspaceContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    /**
     * Authorized by the ProjectPolicy in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * PATCH semantics: every field is optional, and only what is sent changes.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(WorkspaceContext $context): array
    {
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('projects', 'name')
                    ->where('workspace_id', $context->workspace()->getKey())
                    ->ignore($this->project()->getKey()),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'required', Rule::enum(ProjectStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A project with this name already exists in this workspace.',
        ];
    }

    /**
     * The project resolved by the route's scoped binding.
     */
    public function project(): Project
    {
        $project = $this->route('project');

        abort_unless($project instanceof Project, 404);

        return $project;
    }

    /**
     * The validated changes, with status as an enum.
     *
     * @return array{name?: string, description?: string|null, status?: ProjectStatus}
     */
    public function changes(): array
    {
        $changes = $this->safe()->only(['name', 'description', 'status']);

        if (array_key_exists('status', $changes)) {
            $changes['status'] = ProjectStatus::from($changes['status']);
        }

        return $changes;
    }
}
