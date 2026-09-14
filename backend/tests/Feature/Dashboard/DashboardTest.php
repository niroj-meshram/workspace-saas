<?php

use App\Dashboard\WorkspaceDashboard;
use App\Enums\ActivityType;
use App\Enums\TaskStatus;
use App\Enums\WorkspaceRole;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

beforeEach(function () {
    [$this->workspace, $this->admin] = workspaceWithAdmin();
    $this->member = User::factory()->create();
    memberOf($this->workspace, $this->member);
    $this->project = Project::factory()->create(['workspace_id' => $this->workspace->id]);
});

function dashboard(mixed $actor)
{
    return test()->actingAs($actor)->getJson(
        '/api/v1/workspaces/'.test()->workspace->id.'/dashboard'
    );
}

function anActivityFor(array $attributes = []): Activity
{
    return Activity::factory()->create(array_merge([
        'workspace_id' => test()->workspace->id,
        'user_id' => test()->admin->id,
        'subject_type' => 'project',
        'subject_id' => test()->project->id,
    ], $attributes));
}

it('requires authentication', function () {
    $this->getJson("/api/v1/workspaces/{$this->workspace->id}/dashboard")->assertUnauthorized();
});

it('answers 404 for a non-member', function () {
    dashboard(User::factory()->create())->assertNotFound();
});

it('stops serving the dashboard once the membership is removed', function () {
    $user = User::factory()->create();
    $membership = memberOf($this->workspace, $user);

    dashboard($user)->assertOk();

    $membership->delete();

    dashboard($user)->assertNotFound();
});

it('is available to any active member', function (WorkspaceRole $role) {
    $viewer = User::factory()->create();
    memberOf($this->workspace, $viewer, $role);

    dashboard($viewer)->assertOk();
})->with([
    'admin' => [WorkspaceRole::Admin],
    'user' => [WorkspaceRole::User],
]);

it('returns the three documented sections', function () {
    $response = dashboard($this->admin)->assertOk();

    expect(array_keys($response->json('data')))->toBe(['stats', 'my_tasks', 'recent_activity']);

    expect(array_keys($response->json('data.stats')))
        ->toBe(['projects', 'todo', 'in_progress', 'completed']);
});

it('counts projects, todo, in progress and completed tasks', function () {
    Project::factory()->count(2)->create(['workspace_id' => $this->workspace->id]);

    Task::factory()->count(3)->forProject($this->project)->create(['status' => TaskStatus::Todo]);
    Task::factory()->count(2)->forProject($this->project)->create(['status' => TaskStatus::InProgress]);
    Task::factory()->count(4)->forProject($this->project)->create(['status' => TaskStatus::Done]);
    Task::factory()->forProject($this->project)->create(['status' => TaskStatus::Blocked]);

    dashboard($this->admin)->assertOk()
        // The project created in beforeEach plus the two here.
        ->assertJsonPath('data.stats.projects', 3)
        ->assertJsonPath('data.stats.todo', 3)
        ->assertJsonPath('data.stats.in_progress', 2)
        ->assertJsonPath('data.stats.completed', 4);
});

it('reports zeroes for an empty workspace', function () {
    [$empty, $owner] = workspaceWithAdmin();

    $this->actingAs($owner)
        ->getJson("/api/v1/workspaces/{$empty->id}/dashboard")
        ->assertOk()
        ->assertJsonPath('data.stats', [
            'projects' => 0, 'todo' => 0, 'in_progress' => 0, 'completed' => 0,
        ])
        ->assertJsonPath('data.my_tasks', [])
        ->assertJsonPath('data.recent_activity', []);
});

it('counts archived projects too', function () {
    Project::factory()->archived()->create(['workspace_id' => $this->workspace->id]);

    dashboard($this->admin)->assertOk()->assertJsonPath('data.stats.projects', 2);
});

it('excludes soft deleted tasks from the stats', function () {
    $kept = Task::factory()->forProject($this->project)->create(['status' => TaskStatus::Todo]);
    $deleted = Task::factory()->forProject($this->project)->create(['status' => TaskStatus::Todo]);

    dashboard($this->admin)->assertJsonPath('data.stats.todo', 2);

    $deleted->delete();

    dashboard($this->admin)->assertJsonPath('data.stats.todo', 1);

    expect($kept->fresh())->not->toBeNull();
});

it('only lists tasks assigned to the authenticated user', function () {
    $mine = Task::factory()->forProject($this->project)->create(['assignee_id' => $this->member->id]);
    Task::factory()->forProject($this->project)->create(['assignee_id' => $this->admin->id]);
    Task::factory()->forProject($this->project)->create();

    $response = dashboard($this->member)->assertOk();

    expect($response->json('data.my_tasks.*.id'))->toBe([$mine->id]);
});

it('excludes soft deleted tasks from my tasks', function () {
    $kept = Task::factory()->forProject($this->project)->create(['assignee_id' => $this->member->id]);
    $deleted = Task::factory()->forProject($this->project)->create(['assignee_id' => $this->member->id]);

    $deleted->delete();

    expect(dashboard($this->member)->json('data.my_tasks.*.id'))->toBe([$kept->id]);
});

it('includes the project for each of my tasks', function () {
    Task::factory()->forProject($this->project)->create(['assignee_id' => $this->member->id]);

    dashboard($this->member)->assertOk()
        ->assertJsonPath('data.my_tasks.0.project.id', $this->project->id)
        ->assertJsonPath('data.my_tasks.0.project.name', $this->project->name);
});

it('caps my tasks at the documented limit', function () {
    Task::factory()->count(15)->forProject($this->project)->create(['assignee_id' => $this->member->id]);

    dashboard($this->member)->assertOk()
        ->assertJsonCount(WorkspaceDashboard::MY_TASKS_LIMIT, 'data.my_tasks');
});

it('returns recent activity newest first', function () {
    $older = anActivityFor(['created_at' => now()->subDay()]);
    $newer = anActivityFor(['created_at' => now()]);

    expect(dashboard($this->admin)->json('data.recent_activity.*.id'))
        ->toBe([$newer->id, $older->id]);
});

it('caps recent activity at the documented limit', function () {
    foreach (range(1, 15) as $ignored) {
        anActivityFor();
    }

    dashboard($this->admin)->assertOk()
        ->assertJsonCount(WorkspaceDashboard::RECENT_ACTIVITY_LIMIT, 'data.recent_activity');
});

it('includes the actor on recent activity', function () {
    anActivityFor(['user_id' => $this->member->id, 'type' => ActivityType::ProjectCreated]);

    dashboard($this->admin)->assertOk()
        ->assertJsonPath('data.recent_activity.0.user.id', $this->member->id)
        ->assertJsonPath('data.recent_activity.0.type', ActivityType::ProjectCreated->value);
});

it('handles system generated activity with no actor', function () {
    anActivityFor(['user_id' => null]);

    dashboard($this->admin)->assertOk()
        ->assertJsonPath('data.recent_activity.0.user_id', null)
        ->assertJsonPath('data.recent_activity.0.user', null);
});

it('never exposes a password hash', function () {
    Task::factory()->forProject($this->project)->create(['assignee_id' => $this->member->id]);
    anActivityFor(['user_id' => $this->member->id]);

    $response = dashboard($this->admin)->assertOk();

    expect($response->getContent())
        ->not->toContain($this->member->password)
        ->not->toContain($this->admin->password);
});
