<?php

use App\Enums\TaskStatus;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    [$this->workspaceA, $this->adminA] = workspaceWithAdmin();
    [$this->workspaceB, $this->adminB] = workspaceWithAdmin();

    $this->projectA = Project::factory()->create(['workspace_id' => $this->workspaceA->id]);
    $this->projectB = Project::factory()->create(['workspace_id' => $this->workspaceB->id]);
});

function dashboardFor(mixed $actor, string $workspaceId)
{
    return test()->actingAs($actor)->getJson("/api/v1/workspaces/{$workspaceId}/dashboard");
}

it('blocks reading another workspace\'s dashboard', function () {
    dashboardFor($this->adminA, $this->workspaceB->id)->assertNotFound();
});

it('answers a stranger the same as a missing workspace', function () {
    $stranger = User::factory()->create();

    $inaccessible = dashboardFor($stranger, $this->workspaceB->id);
    $missing = dashboardFor($stranger, Str::uuid()->toString());

    $inaccessible->assertNotFound();
    $missing->assertNotFound();

    expect($inaccessible->json())->toBe($missing->json());
});

it('never counts another workspace\'s projects or tasks', function () {
    Project::factory()->count(4)->create(['workspace_id' => $this->workspaceB->id]);
    Task::factory()->count(6)->forProject($this->projectB)->create(['status' => TaskStatus::Todo]);

    Task::factory()->forProject($this->projectA)->create(['status' => TaskStatus::Todo]);

    dashboardFor($this->adminA, $this->workspaceA->id)->assertOk()
        ->assertJsonPath('data.stats.projects', 1)
        ->assertJsonPath('data.stats.todo', 1);
});

it('never lists my tasks from another workspace', function () {
    // The same user is a member of both workspaces and assigned in each.
    memberOf($this->workspaceB, $this->adminA);

    $inA = Task::factory()->forProject($this->projectA)->create(['assignee_id' => $this->adminA->id]);
    $inB = Task::factory()->forProject($this->projectB)->create(['assignee_id' => $this->adminA->id]);

    expect(dashboardFor($this->adminA, $this->workspaceA->id)->json('data.my_tasks.*.id'))
        ->toBe([$inA->id]);

    expect(dashboardFor($this->adminA, $this->workspaceB->id)->json('data.my_tasks.*.id'))
        ->toBe([$inB->id]);
});

it('never shows another workspace\'s activity', function () {
    $inA = Activity::factory()->create([
        'workspace_id' => $this->workspaceA->id,
        'user_id' => $this->adminA->id,
        'subject_type' => 'project',
        'subject_id' => $this->projectA->id,
    ]);
    $inB = Activity::factory()->create([
        'workspace_id' => $this->workspaceB->id,
        'user_id' => $this->adminB->id,
        'subject_type' => 'project',
        'subject_id' => $this->projectB->id,
    ]);

    $response = dashboardFor($this->adminA, $this->workspaceA->id)->assertOk();

    expect($response->json('data.recent_activity.*.id'))
        ->toBe([$inA->id])
        ->not->toContain($inB->id);
});

it('keeps the two dashboards of a dual member completely separate', function () {
    memberOf($this->workspaceB, $this->adminA);

    Task::factory()->count(2)->forProject($this->projectA)->create(['status' => TaskStatus::Done]);
    Task::factory()->count(5)->forProject($this->projectB)->create(['status' => TaskStatus::Done]);

    dashboardFor($this->adminA, $this->workspaceA->id)
        ->assertJsonPath('data.stats.completed', 2)
        ->assertJsonPath('data.stats.projects', 1);

    dashboardFor($this->adminA, $this->workspaceB->id)
        ->assertJsonPath('data.stats.completed', 5)
        ->assertJsonPath('data.stats.projects', 1);
});
