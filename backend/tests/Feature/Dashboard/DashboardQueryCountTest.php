<?php

use App\Models\Activity;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| N+1 guard
|--------------------------------------------------------------------------
|
| The dashboard touches three collections. What matters is not the absolute
| query count but that it does not grow with the amount of data: these tests
| measure a small workspace and a much larger one and require the same number
| of queries.
|
*/

beforeEach(function () {
    [$this->workspace, $this->admin] = workspaceWithAdmin();
});

/**
 * Number of database queries a dashboard request makes.
 *
 * The response is asserted to actually carry the nested project and actor, so
 * a flat query count can only mean the relations were resolved in bulk — never
 * that they were quietly left out. Without that check this measurement would
 * pass just as happily with no eager loading at all, because the resources
 * omit relations they were not given rather than lazy-loading them.
 */
function dashboardQueryCount(mixed $actor, string $workspaceId): int
{
    $count = 0;
    DB::listen(function () use (&$count) {
        $count++;
    });

    $response = test()->actingAs($actor)
        ->getJson("/api/v1/workspaces/{$workspaceId}/dashboard")
        ->assertOk();

    expect($response->json('data.my_tasks.0.project.id'))->not->toBeNull()
        ->and($response->json('data.recent_activity.0.user.id'))->not->toBeNull();

    return $count;
}

/**
 * Fill a workspace with $n projects, tasks (assigned to $actor, each in its
 * own project) and activities.
 */
function seedWorkspace(mixed $workspace, mixed $actor, int $n): void
{
    foreach (range(1, $n) as $i) {
        $project = Project::factory()->create(['workspace_id' => $workspace->id]);

        Task::factory()->forProject($project)->create(['assignee_id' => $actor->id]);

        Activity::factory()->create([
            'workspace_id' => $workspace->id,
            // A different actor each time, so a lazy-loaded user relation
            // would cost one query per row.
            'user_id' => User::factory()->create()->id,
            'subject_type' => 'project',
            'subject_id' => $project->id,
        ]);
    }
}

it('does not issue more queries as the workspace grows', function () {
    seedWorkspace($this->workspace, $this->admin, 1);
    $small = dashboardQueryCount($this->admin, $this->workspace->id);

    [$bigger, $owner] = workspaceWithAdmin();
    seedWorkspace($bigger, $owner, 10);
    $large = dashboardQueryCount($owner, $bigger->id);

    expect($large)->toBe($small);
});

it('stays within a small fixed query budget', function () {
    seedWorkspace($this->workspace, $this->admin, 10);

    // Membership resolution, two aggregates, my tasks + their projects,
    // activities + their users. Generous enough not to be brittle, tight
    // enough to catch a per-row query.
    expect(dashboardQueryCount($this->admin, $this->workspace->id))->toBeLessThanOrEqual(10);
});

it('loads the stats without reading any task or project rows', function () {
    seedWorkspace($this->workspace, $this->admin, 5);

    $statements = [];
    DB::listen(function ($query) use (&$statements) {
        $statements[] = $query->sql;
    });

    $this->actingAs($this->admin)
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/dashboard")
        ->assertOk();

    $aggregates = array_values(array_filter(
        $statements,
        fn (string $sql) => str_contains($sql, 'count(*)')
    ));

    // One grouped count for every task status, one count for projects.
    expect($aggregates)->toHaveCount(2)
        ->and(implode(' ', $aggregates))->toContain('group by');
});
