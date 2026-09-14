<?php

namespace App\Http\Requests\Tasks;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\Tasks\Concerns\ValidatesTaskRelations;
use App\Models\Task;
use App\Tenancy\WorkspaceContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    use ValidatesTaskRelations;

    /**
     * Authorized by the TaskPolicy in the controller.
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
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'project_id' => [
                'bail',
                'sometimes',
                'required',
                'uuid',
                $this->projectInWorkspace($context),
                $this->projectAcceptsNewTasks($context, $this->task()),
            ],
            'assignee_id' => array_merge(['sometimes'], $this->activeMemberRules($context)),
            'status' => ['sometimes', 'required', Rule::enum(TaskStatus::class)],
            'priority' => ['sometimes', 'required', Rule::enum(TaskPriority::class)],
            'due_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->relationMessages();
    }

    /**
     * The task resolved by the route's scoped binding.
     */
    public function task(): Task
    {
        $task = $this->route('task');

        abort_unless($task instanceof Task, 404);

        return $task;
    }

    /**
     * The validated changes, with enums resolved.
     *
     * @return array<string, mixed>
     */
    public function changes(): array
    {
        $changes = $this->safe()->only([
            'title', 'description', 'project_id', 'assignee_id', 'status', 'priority', 'due_date',
        ]);

        if (array_key_exists('status', $changes)) {
            $changes['status'] = TaskStatus::from($changes['status']);
        }

        if (array_key_exists('priority', $changes)) {
            $changes['priority'] = TaskPriority::from($changes['priority']);
        }

        return $changes;
    }
}
