<?php

namespace App\Http\Resources;

use App\Dashboard\DashboardData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DashboardData
 */
class DashboardResource extends JsonResource
{
    /**
     * The three sections from PROJECT_SPEC.md §12, assembled from the existing
     * task and activity resources so the dashboard cannot drift away from what
     * those endpoints return.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'stats' => $this->stats,
            'my_tasks' => TaskResource::collection($this->myTasks),
            'recent_activity' => ActivityResource::collection($this->recentActivity),
        ];
    }
}
