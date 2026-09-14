<?php

use App\Enums\ProjectStatus;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\User;

it('lets any member list the projects', function (WorkspaceRole $role) {
    [$workspace] = workspaceWithAdmin();
    $viewer = User::factory()->create();
    memberOf($workspace, $viewer, $role);

    Project::factory()->count(3)->create(['workspace_id' => $workspace->id]);

    $this->actingAs($viewer)
        ->getJson("/api/v1/workspaces/{$workspace->id}/projects")
        ->assertOk()
        ->assertJsonCount(3, 'data');
})->with([
    'admin' => [WorkspaceRole::Admin],
    'user' => [WorkspaceRole::User],
]);

it('returns the documented project shape', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    Project::factory()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Launch',
        'description' => 'Q4 launch',
    ]);

    $response = $this->actingAs($admin)
        ->getJson("/api/v1/workspaces/{$workspace->id}/projects")
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Launch')
        ->assertJsonPath('data.0.description', 'Q4 launch')
        ->assertJsonPath('data.0.status', ProjectStatus::Active->value);

    expect(array_keys($response->json('data.0')))
        ->toBe(['id', 'name', 'description', 'status', 'created_at', 'updated_at']);
});

it('includes archived projects in the listing', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    Project::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Active one']);
    Project::factory()->archived()->create(['workspace_id' => $workspace->id, 'name' => 'Archived one']);

    $response = $this->actingAs($admin)
        ->getJson("/api/v1/workspaces/{$workspace->id}/projects")
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect($response->json('data.*.status'))
        ->toContain(ProjectStatus::Active->value, ProjectStatus::Archived->value);
});

it('lists only the projects of the workspace in the route', function () {
    [$workspaceA, $adminA] = workspaceWithAdmin();
    [$workspaceB] = workspaceWithAdmin();

    $mine = Project::factory()->create(['workspace_id' => $workspaceA->id]);
    $theirs = Project::factory()->create(['workspace_id' => $workspaceB->id]);

    $response = $this->actingAs($adminA)
        ->getJson("/api/v1/workspaces/{$workspaceA->id}/projects")
        ->assertOk();

    expect($response->json('data.*.id'))->toBe([$mine->id])
        ->not->toContain($theirs->id);
});

it('paginates with standard metadata', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    Project::factory()->count(5)->create(['workspace_id' => $workspace->id]);

    $this->actingAs($admin)
        ->getJson("/api/v1/workspaces/{$workspace->id}/projects?per_page=2")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 5)
        ->assertJsonPath('meta.per_page', 2);
});

it('caps the page size at 100', function () {
    [$workspace, $admin] = workspaceWithAdmin();

    $this->actingAs($admin)
        ->getJson("/api/v1/workspaces/{$workspace->id}/projects?per_page=5000")
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100);
});

it('lets any member read a single project', function (WorkspaceRole $role) {
    [$workspace] = workspaceWithAdmin();
    $viewer = User::factory()->create();
    memberOf($workspace, $viewer, $role);

    $project = Project::factory()->create(['workspace_id' => $workspace->id]);

    $this->actingAs($viewer)
        ->getJson("/api/v1/workspaces/{$workspace->id}/projects/{$project->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $project->id);
})->with([
    'admin' => [WorkspaceRole::Admin],
    'user' => [WorkspaceRole::User],
]);

it('keeps an archived project readable', function () {
    [$workspace] = workspaceWithAdmin();
    $viewer = User::factory()->create();
    memberOf($workspace, $viewer);

    $project = Project::factory()->archived()->create(['workspace_id' => $workspace->id]);

    $this->actingAs($viewer)
        ->getJson("/api/v1/workspaces/{$workspace->id}/projects/{$project->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $project->id)
        ->assertJsonPath('data.status', ProjectStatus::Archived->value);
});

it('requires authentication', function () {
    [$workspace] = workspaceWithAdmin();
    $project = Project::factory()->create(['workspace_id' => $workspace->id]);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/projects")->assertUnauthorized();
    $this->getJson("/api/v1/workspaces/{$workspace->id}/projects/{$project->id}")->assertUnauthorized();
});

it('answers 404 for a non-member', function () {
    [$workspace] = workspaceWithAdmin();
    $project = Project::factory()->create(['workspace_id' => $workspace->id]);

    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->getJson("/api/v1/workspaces/{$workspace->id}/projects")
        ->assertNotFound();

    $this->actingAs($stranger)
        ->getJson("/api/v1/workspaces/{$workspace->id}/projects/{$project->id}")
        ->assertNotFound();
});

it('stops listing projects once the membership is removed', function () {
    [$workspace] = workspaceWithAdmin();
    $user = User::factory()->create();
    $member = memberOf($workspace, $user);
    Project::factory()->create(['workspace_id' => $workspace->id]);

    $this->actingAs($user)->getJson("/api/v1/workspaces/{$workspace->id}/projects")->assertOk();

    $member->delete();

    $this->actingAs($user)->getJson("/api/v1/workspaces/{$workspace->id}/projects")->assertNotFound();
});
