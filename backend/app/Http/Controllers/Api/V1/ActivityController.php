<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesPagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Activities\IndexActivityRequest;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Tenancy\WorkspaceContext;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read-only (PROJECT_SPEC.md §12). This controller has one action on purpose:
 * activities are written by the business actions that cause them, never by a
 * request, so there is no store, update or destroy to expose.
 */
class ActivityController extends Controller
{
    use ResolvesPagination;

    public function __construct(private readonly WorkspaceContext $context) {}

    public function index(IndexActivityRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Activity::class);

        $activities = $this->context->workspace()
            ->activities()
            ->with('user')
            ->filter($request->filters())
            // Newest first, with the id as tiebreaker: created_at is stored at
            // second precision, so a burst of activity from one operation
            // would otherwise come back in an arbitrary order.
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        return ActivityResource::collection($activities);
    }
}
