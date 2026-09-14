<?php

namespace App\Http\Requests\Projects;

use App\Tenancy\WorkspaceContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    /**
     * Authorized by the ProjectPolicy in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * status is deliberately not accepted: projects are created active and
     * archived by a later transition, so the archive always goes through the
     * action that records it (PROJECT_SPEC.md §8).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(WorkspaceContext $context): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                // Scoped to the resolved tenant, never to a workspace id from
                // the payload (PROJECT_SPEC.md §7, §8).
                Rule::unique('projects', 'name')
                    ->where('workspace_id', $context->workspace()->getKey()),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
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
}
