<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Invitations\InviteMember;
use App\Actions\Invitations\RevokeInvitation;
use App\Http\Concerns\ResolvesPagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invitations\StoreInvitationRequest;
use App\Http\Resources\WorkspaceInvitationResource;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Tenancy\WorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The {invitation} route parameter is resolved through the workspace
 * relationship (the route group declares scopeBindings), so an invitation from
 * another workspace — or a revoked one — is a 404 before any of this runs
 * (PROJECT_SPEC.md §7, §14).
 */
class WorkspaceInvitationController extends Controller
{
    use ResolvesPagination;

    public function __construct(private readonly WorkspaceContext $context) {}

    /**
     * Accepted and expired invitations stay listed: §10 keeps them for
     * history. Revoked ones are soft-deleted and drop out.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', WorkspaceInvitation::class);

        $invitations = $this->context->workspace()
            ->invitations()
            ->with('invitedBy')
            ->latest()
            ->paginate($this->perPage($request));

        return WorkspaceInvitationResource::collection($invitations);
    }

    public function store(StoreInvitationRequest $request, InviteMember $action): JsonResponse
    {
        $this->authorize('create', WorkspaceInvitation::class);

        $invitation = $action->handle(
            $this->context->workspace(),
            $request->user(),
            $request->email(),
            $request->role(),
        );

        return WorkspaceInvitationResource::make($invitation->load('invitedBy'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(
        Workspace $workspace,
        WorkspaceInvitation $invitation,
        RevokeInvitation $action,
    ): Response {
        $this->authorize('delete', $invitation);

        $action->handle($invitation);

        return response()->noContent();
    }
}
