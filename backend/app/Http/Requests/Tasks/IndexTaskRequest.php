<?php

namespace App\Http\Requests\Tasks;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Tenancy\WorkspaceContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Whitelists the list filters and sort (PROJECT_SPEC.md §12). Anything not
 * named here is ignored rather than passed through to the query.
 */
class IndexTaskRequest extends FormRequest
{
    /**
     * Authorized by the TaskPolicy in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(WorkspaceContext $context): array
    {
        $sortable = collect(Task::SORTABLE)
            ->flatMap(fn (string $column) => [$column, '-'.$column])
            ->all();

        return [
            'project_id' => [
                'sometimes',
                'uuid',
                // Scoped to the tenant: filtering by another workspace's
                // project is a validation error, not an empty result.
                Rule::exists('projects', 'id')
                    ->where('workspace_id', $context->workspace()->getKey()),
            ],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            // Not required to still be a member: filtering by someone who has
            // since been removed must still find the tasks they once held.
            'assignee_id' => ['sometimes', 'uuid'],
            'search' => ['sometimes', 'string', 'max:255'],
            'sort' => ['sometimes', 'string', Rule::in($sortable)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sort.in' => 'Tasks can only be sorted by: '.implode(', ', Task::SORTABLE).', optionally prefixed with "-".',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->safe()->only(['project_id', 'status', 'priority', 'assignee_id', 'search']);
    }

    public function sort(): ?string
    {
        return $this->safe()->string('sort')->toString() ?: null;
    }
}
