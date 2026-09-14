<?php

namespace App\Http\Requests\Tasks;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\Tasks\Concerns\ValidatesTaskRelations;
use App\Tenancy\WorkspaceContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(WorkspaceContext $context): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'project_id' => [
                'bail',
                'required',
                'uuid',
                $this->projectInWorkspace($context),
                $this->projectAcceptsNewTasks($context),
            ],
            'assignee_id' => $this->activeMemberRules($context),
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
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
     * The validated payload, with enums resolved, ready for the action.
     *
     * Deliberately not named attributes(): FormRequest reserves that for
     * custom validation attribute names.
     *
     * @return array<string, mixed>
     */
    public function taskAttributes(): array
    {
        $attributes = $this->safe()->only([
            'title', 'description', 'project_id', 'assignee_id', 'status', 'priority', 'due_date',
        ]);

        if (isset($attributes['status'])) {
            $attributes['status'] = TaskStatus::from($attributes['status']);
        }

        if (isset($attributes['priority'])) {
            $attributes['priority'] = TaskPriority::from($attributes['priority']);
        }

        return $attributes;
    }
}
