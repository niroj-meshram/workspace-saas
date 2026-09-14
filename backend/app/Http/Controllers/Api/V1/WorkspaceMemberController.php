<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tenancy\ChangeMemberRole;
use App\Actions\Tenancy\RemoveMember;
use App\Http\Concerns\ResolvesPagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Members\UpdateWorkspaceMemberRequest;
use App\Http\Resources\WorkspaceMemberResource;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Tenancy\WorkspaceContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The {member} route parameter is resolved through the workspace relationship
 * (the route group declares scopeBindings), so a membership from another
 * workspace — or a removed one — is a 404 before any of this runs.
 */
class WorkspaceMemberController extends Controller
{
    use ResolvesPagination;

    public function __construct(private readonly WorkspaceContext $context) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', WorkspaceMember::class);

        $members = $this->context->workspace()
            ->members()
            ->with('user')
            ->orderBy('created_at')
            ->paginate($this->perPage($request));

        return WorkspaceMemberResource::collection($members);
    }

    public function update(
        UpdateWorkspaceMemberRequest $request,
        Workspace $workspace,
        WorkspaceMember $member,
        ChangeMemberRole $action,
    ): WorkspaceMemberResource {
        $this->authorize('update', $member);

        $member = $action->handle(
            $this->context->workspace(),
            $request->user(),
            $member,
            $request->role(),
        );

        return WorkspaceMemberResource::make($member->load('user'));
    }

    public function destroy(
        Request $request,
        Workspace $workspace,
        WorkspaceMember $member,
        RemoveMember $action,
    ): Response {
        $this->authorize('delete', $member);

        $action->handle($this->context->workspace(), $request->user(), $member);

        return response()->noContent();
    }
}
