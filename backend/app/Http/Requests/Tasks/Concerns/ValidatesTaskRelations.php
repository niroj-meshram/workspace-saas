<?php

namespace App\Http\Requests\Tasks\Concerns;

use App\Enums\ProjectStatus;
use App\Models\Task;
use App\Tenancy\WorkspaceContext;
use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * The two cross-tenant rules from PROJECT_SPEC.md §8 and §17, shared by the
 * create and update requests so they cannot drift apart:
 *
 *   - a task's project must live in the task's own workspace
 *   - an assignee must be an active member of that workspace
 *
 * Both are scoped to the resolved tenant, never to anything in the payload.
 */
trait ValidatesTaskRelations
{
    protected function projectInWorkspace(WorkspaceContext $context): Exists
    {
        return Rule::exists('projects', 'id')
            ->where('workspace_id', $context->workspace()->getKey());
    }

    /**
     * "Archived projects cannot receive new tasks" (PROJECT_SPEC.md §8).
     *
     * Moving an existing task into an archived project counts as receiving
     * one, so it is refused too. A task that already sits in a project which
     * was archived afterwards stays fully editable — §8 says existing tasks
     * remain.
     *
     * @param  Task|null  $current  the task being updated, if any
     */
    protected function projectAcceptsNewTasks(WorkspaceContext $context, ?Task $current = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($context, $current): void {
            if ($current !== null && $value === $current->project_id) {
                return;
            }

            $project = $context->workspace()->projects()->find($value);

            if ($project?->status === ProjectStatus::Archived) {
                $fail('An archived project cannot receive new tasks.');
            }
        };
    }

    /**
     * @return array<int, mixed>
     */
    protected function activeMemberRules(WorkspaceContext $context): array
    {
        return [
            'nullable',
            'uuid',
            Rule::exists('workspace_members', 'user_id')
                ->where('workspace_id', $context->workspace()->getKey())
                ->whereNull('deleted_at'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function relationMessages(): array
    {
        return [
            'project_id.exists' => 'The selected project does not belong to this workspace.',
            'assignee_id.exists' => 'The assignee must be an active member of this workspace.',
        ];
    }
}
