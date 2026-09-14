<?php

namespace App\Actions\Activity;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;

/**
 * The write side of the Activity module (PROJECT_SPEC.md §9).
 *
 * Every module records history through this one entry point, so the append-only
 * rules live in a single place: activities are never updated or deleted, the
 * subject is stored as a morph-map alias rather than a class name, and the
 * workspace always comes from the caller's resolved tenant.
 *
 * Callers are expected to invoke this inside the transaction of the operation
 * being recorded, so history cannot survive a rolled-back change.
 *
 * The read side (GET /workspaces/{workspace}/activities) is Phase 10.
 */
class RecordActivity
{
    /**
     * @param  User|null  $actor  null for system-generated events
     * @param  array<string, mixed>  $metadata
     */
    public function handle(
        Workspace $workspace,
        ?User $actor,
        ActivityType $type,
        Model $subject,
        array $metadata = [],
    ): Activity {
        $activity = new Activity;
        $activity->workspace_id = $workspace->getKey();
        $activity->user_id = $actor?->getKey();
        $activity->type = $type;
        $activity->metadata = $metadata;

        // Writes subject_type/subject_id through the morph map.
        $activity->subject()->associate($subject);

        $activity->save();

        return $activity;
    }
}
