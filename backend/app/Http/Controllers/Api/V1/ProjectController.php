<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Projects\ArchiveProject;
use App\Actions\Projects\CreateProject;
use App\Actions\Projects\UpdateProject;
use App\Http\Concerns\ResolvesPagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\StoreProjectRequest;
use App\Http\Requests\Projects\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\Workspace;
use App\Tenancy\WorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The {project} route parameter is resolved through the workspace relationship
 * (the route group declares scopeBindings), so a project belonging to another
 * workspace is a 404 before any of this runs (PROJECT_SPEC.md §7, §14).
 */
class ProjectController extends Controller
{
    use ResolvesPagination;

    public function __construct(private readonly WorkspaceContext $context) {}

    /**
     * Archived projects are included: they remain viewable
     * (PROJECT_SPEC.md §8).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Project::class);

        $projects = $this->context->workspace()
            ->projects()
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return ProjectResource::collection($projects);
    }

    public function store(StoreProjectRequest $request, CreateProject $action): JsonResponse
    {
        $this->authorize('create', Project::class);

        $project = $action->handle(
            $this->context->workspace(),
            $request->user(),
            $request->string('name')->toString(),
            $request->input('description'),
        );

        return ProjectResource::make($project)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Workspace $workspace, Project $project): ProjectResource
    {
        $this->authorize('view', $project);

        return ProjectResource::make($project);
    }

    public function update(
        UpdateProjectRequest $request,
        Workspace $workspace,
        Project $project,
        UpdateProject $action,
    ): ProjectResource {
        $this->authorize('update', $project);

        $project = $action->handle(
            $this->context->workspace(),
            $request->user(),
            $project,
            $request->changes(),
        );

        return ProjectResource::make($project);
    }

    /**
     * Projects have no soft delete: the schema uses active/archived instead
     * (PROJECT_SPEC.md §11), so DELETE archives. Nothing is physically removed
     * and the project's tasks and activity are preserved.
     */
    public function destroy(
        Request $request,
        Workspace $workspace,
        Project $project,
        ArchiveProject $action,
    ): Response {
        $this->authorize('delete', $project);

        $action->handle($this->context->workspace(), $request->user(), $project);

        return response()->noContent();
    }
}
