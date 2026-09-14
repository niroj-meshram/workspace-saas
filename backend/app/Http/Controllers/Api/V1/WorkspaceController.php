<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tenancy\CreateWorkspace;
use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspaces\StoreWorkspaceRequest;
use App\Http\Requests\Workspaces\UpdateWorkspaceRequest;
use App\Http\Resources\WorkspaceResource;
use App\Models\Workspace;
use App\Tenancy\WorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class WorkspaceController extends Controller
{
    public function __construct(private readonly WorkspaceContext $context) {}

    /**
     * Workspaces the authenticated user is an active member of.
     *
     * Scoped through the user's own relationship rather than a workspace_id
     * filter: the relationship already excludes removed memberships, and the
     * soft-delete scope on Workspace excludes deleted workspaces
     * (PROJECT_SPEC.md §7, §11).
     *
     * Not paginated — a user belongs to a handful of workspaces and the
     * workspace switcher needs all of them.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $workspaces = $request->user()
            ->workspaces()
            ->orderBy('name')
            ->get()
            ->each(fn (Workspace $workspace) => $workspace->viewer_role = $workspace->pivot->role);

        return WorkspaceResource::collection($workspaces);
    }

    public function store(StoreWorkspaceRequest $request, CreateWorkspace $action): JsonResponse
    {
        $workspace = $action->handle($request->user(), $request->string('name')->toString());
        $workspace->viewer_role = WorkspaceRole::Admin->value;

        return WorkspaceResource::make($workspace)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Workspace $workspace): WorkspaceResource
    {
        $this->authorize('view', $workspace);

        $workspace = $this->context->workspace();
        $workspace->viewer_role = $this->context->role()->value;

        return WorkspaceResource::make($workspace);
    }

    public function update(UpdateWorkspaceRequest $request, Workspace $workspace): WorkspaceResource
    {
        $this->authorize('update', $workspace);

        $workspace->update($request->safe()->only('name'));
        $workspace->viewer_role = $this->context->role()->value;

        return WorkspaceResource::make($workspace);
    }

    /**
     * Soft delete. Related records are preserved and nothing cascades
     * (PROJECT_SPEC.md §11).
     */
    public function destroy(Workspace $workspace): Response
    {
        $this->authorize('delete', $workspace);

        $workspace->delete();

        return response()->noContent();
    }
}
