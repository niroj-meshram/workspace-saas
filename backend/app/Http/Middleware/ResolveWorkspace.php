<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use App\Tenancy\WorkspaceContext;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the tenant for a workspace-scoped route (PROJECT_SPEC.md §7):
 *
 *   authenticated user -> resolve workspace from route -> verify active
 *   membership -> establish context -> (policies authorize) -> controller
 *
 * Membership is what grants access, never the workspace UUID on its own. A
 * workspace the user is not an active member of is answered with 404, the same
 * as one that does not exist, so the API cannot be used to discover which
 * workspace ids are real (PROJECT_SPEC.md §14).
 */
class ResolveWorkspace
{
    public function __construct(private readonly WorkspaceContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $workspace = $this->resolveWorkspace($request);

        // Goes through the relationship, so the soft-delete scope applies and
        // a removed membership is inactive for free.
        $member = $workspace->members()
            ->whereBelongsTo($request->user())
            ->first();

        abort_if($member === null, 404);

        $this->context->establish($workspace, $member);

        return $next($request);
    }

    /**
     * SubstituteBindings only resolves route parameters that the controller
     * action type-hints, so a controller that reads the workspace from the
     * context instead would leave a raw string here. Resolve it if needed and
     * put the model back on the route, so tenant resolution never depends on a
     * controller's method signature.
     *
     * @throws ModelNotFoundException on a malformed id
     */
    private function resolveWorkspace(Request $request): Workspace
    {
        $workspace = $request->route('workspace');

        if ($workspace instanceof Workspace) {
            return $workspace;
        }

        $workspace = (new Workspace)->resolveRouteBinding($workspace);

        abort_if($workspace === null, 404);

        $request->route()->setParameter('workspace', $workspace);

        return $workspace;
    }
}
