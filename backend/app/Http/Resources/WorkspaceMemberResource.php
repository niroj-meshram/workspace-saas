<?php

namespace App\Http\Resources;

use App\Models\WorkspaceMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkspaceMember
 */
class WorkspaceMemberResource extends JsonResource
{
    /**
     * workspace_id is deliberately absent: the workspace is already identified
     * by the route, and echoing tenant keys invites clients to start sending
     * them back (PROJECT_SPEC.md §7).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role->value,
            'user' => UserResource::make($this->whenLoaded('user')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
