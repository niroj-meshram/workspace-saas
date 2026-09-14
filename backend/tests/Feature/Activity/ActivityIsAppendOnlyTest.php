<?php

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Project;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    [$this->workspace, $this->admin] = workspaceWithAdmin();
    $this->project = Project::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->activity = Activity::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->admin->id,
        'subject_type' => 'project',
        'subject_id' => $this->project->id,
    ]);
});

it('exposes no write route for activities', function (string $method) {
    $response = $this->actingAs($this->admin)->json(
        $method,
        "/api/v1/workspaces/{$this->workspace->id}/activities",
        ['type' => ActivityType::ProjectCreated->value],
    );

    // 405 for a known URI with no such verb; never 200/201.
    expect($response->getStatusCode())->toBe(405);

    expect(Activity::count())->toBe(1);
})->with(['POST', 'PUT', 'PATCH', 'DELETE']);

it('exposes no route for an individual activity', function (string $method) {
    $this->actingAs($this->admin)
        ->json($method, "/api/v1/workspaces/{$this->workspace->id}/activities/{$this->activity->id}")
        ->assertNotFound();

    expect(Activity::count())->toBe(1);
})->with(['GET', 'PATCH', 'PUT', 'DELETE']);

it('refuses to update an activity at the model level', function () {
    // Set directly rather than via update(): nothing on Activity is mass
    // assignable, so update() never reaches the append-only guard.
    $this->activity->type = ActivityType::ProjectArchived;

    expect(fn () => $this->activity->save())
        ->toThrow(RuntimeException::class, 'Activities are append-only and cannot be modified.');

    expect($this->activity->fresh()->type)->not->toBe(ActivityType::ProjectArchived);
});

it('has no mass assignable attributes at all', function () {
    expect(fn () => $this->activity->update(['type' => ActivityType::ProjectArchived]))
        ->toThrow(MassAssignmentException::class);

    expect($this->activity->fresh()->type)->not->toBe(ActivityType::ProjectArchived);
});

it('refuses to delete an activity at the model level', function () {
    expect(fn () => $this->activity->delete())
        ->toThrow(RuntimeException::class, 'Activities are append-only and cannot be deleted.');

    expect(Activity::find($this->activity->id))->not->toBeNull();
});

it('has no soft delete column to hide history behind', function () {
    expect(Schema::hasColumn('activities', 'deleted_at'))->toBeFalse()
        ->and(Schema::hasColumn('activities', 'updated_at'))->toBeFalse();
});

it('refuses to physically delete a workspace that has activity', function () {
    // No cascading deletes: the foreign key is ON DELETE RESTRICT, so history
    // cannot be swept away by removing its parent (PROJECT_SPEC.md §11).
    expect(fn () => DB::table('workspaces')->where('id', $this->workspace->id)->delete())
        ->toThrow(QueryException::class);
});

it('refuses to physically delete a user who has activity', function () {
    expect(fn () => DB::table('users')->where('id', $this->admin->id)->delete())
        ->toThrow(QueryException::class);
});
