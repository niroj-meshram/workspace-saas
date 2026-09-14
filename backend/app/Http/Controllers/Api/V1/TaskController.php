<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tasks\CreateTask;
use App\Actions\Tasks\DeleteTask;
use App\Actions\Tasks\UpdateTask;
use App\Http\Concerns\ResolvesPagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\IndexTaskRequest;
use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Models\Workspace;
use App\Tenancy\WorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The {task} route parameter is resolved through the workspace relationship
 * (the route group declares scopeBindings), so a task in another workspace —
 * or a soft-deleted one — is a 404 before any of this runs
 * (PROJECT_SPEC.md §7, §11, §14).
 */
class TaskController extends Controller
{
    use ResolvesPagination;

    public function __construct(private readonly WorkspaceContext $context) {}

    public function index(IndexTaskRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Task::class);

        $tasks = $this->context->workspace()
            ->tasks()
            ->with(['project', 'assignee'])
            ->filter($request->filters())
            ->sorted($request->sort())
            ->paginate($this->perPage($request));

        return TaskResource::collection($tasks);
    }

    public function store(StoreTaskRequest $request, CreateTask $action): JsonResponse
    {
        $this->authorize('create', Task::class);

        $task = $action->handle(
            $this->context->workspace(),
            $request->user(),
            $request->taskAttributes(),
        );

        return TaskResource::make($task->load(['project', 'assignee']))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Workspace $workspace, Task $task): TaskResource
    {
        $this->authorize('view', $task);

        return TaskResource::make($task->load(['project', 'assignee']));
    }

    public function update(
        UpdateTaskRequest $request,
        Workspace $workspace,
        Task $task,
        UpdateTask $action,
    ): TaskResource {
        $this->authorize('update', $task);

        $task = $action->handle(
            $this->context->workspace(),
            $request->user(),
            $task,
            $request->changes(),
        );

        return TaskResource::make($task->load(['project', 'assignee']));
    }

    /**
     * Soft delete (PROJECT_SPEC.md §11): the row is retained and drops out of
     * every normal query, and the task's activity history is preserved.
     */
    public function destroy(
        Request $request,
        Workspace $workspace,
        Task $task,
        DeleteTask $action,
    ): Response {
        $this->authorize('delete', $task);

        $action->handle($this->context->workspace(), $request->user(), $task);

        return response()->noContent();
    }
}
