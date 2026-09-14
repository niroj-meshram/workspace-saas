<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Invitations\AcceptInvitation;
use App\Http\Controllers\Controller;
use App\Http\Resources\WorkspaceResource;
use App\Models\WorkspaceInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Acceptance lives outside the workspace-scoped routes: the caller is not a
 * member yet, so there is no tenant context to resolve. It still requires
 * authentication — opening the link is public, using it is not
 * (PROJECT_SPEC.md §10).
 */
class InvitationAcceptanceController extends Controller
{
    /**
     * The URL carries the raw token; the database only holds its digest, so
     * the lookup hashes first. An unknown token is a 404 like any other
     * missing resource.
     */
    public function __invoke(Request $request, string $token, AcceptInvitation $action): JsonResponse
    {
        $invitation = WorkspaceInvitation::query()
            ->where('token_hash', WorkspaceInvitation::hashToken($token))
            ->first();

        abort_if($invitation === null, 404);

        // Email matching is the authorization for this route.
        $this->authorize('accept', $invitation);

        $member = $action->handle($invitation, $request->user());

        return WorkspaceResource::make($member->workspace)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
