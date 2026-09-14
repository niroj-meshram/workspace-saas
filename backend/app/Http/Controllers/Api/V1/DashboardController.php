<?php

namespace App\Http\Controllers\Api\V1;

use App\Dashboard\WorkspaceDashboard;
use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardResource;
use App\Tenancy\WorkspaceContext;
use Illuminate\Http\Request;

/**
 * Read-only summary of the current workspace (PROJECT_SPEC.md §12, §24).
 */
class DashboardController extends Controller
{
    public function __construct(private readonly WorkspaceContext $context) {}

    public function __invoke(Request $request, WorkspaceDashboard $dashboard): DashboardResource
    {
        $workspace = $this->context->workspace();

        // Reuses the existing "can read this workspace" rule rather than
        // inventing a policy for a view that owns no model.
        $this->authorize('view', $workspace);

        return DashboardResource::make($dashboard->for($workspace, $request->user()));
    }
}
