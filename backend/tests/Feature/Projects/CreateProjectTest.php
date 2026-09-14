<?php

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;

it('lets an admin create a project', function () {
    [$workspace, $admin] = workspaceWithAdmin();

    $response = $this->actingAs($admin)
        ->postJson("/api/v1/workspaces/{$workspace->id}/projects", [
            'name' => 'Launch',
            'description' => 'Q4 launch',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Launch')
        ->assertJsonPath('data.description', 'Q4 launch')
        ->assertJsonPath('data.status', ProjectStatus::Active->value);

    $project = Project::sole();

    expect($project->workspace_id)->toBe($workspace->id)
        ->and($project->status)->toBe(ProjectStatus::Active);
});

it('creates a project without a description', function () {
    [$workspace, $admin] = workspaceWithAdmin();

    $this->actingAs($admin)
        ->postJson("/api/v1/workspaces/{$workspace->id}/projects", ['name' => 'Launch'])
        ->assertCreated()
        ->assertJsonPath('data.description', null);
});

it('forbids a non-admin member from creating a project', function () {
    [$workspace] = workspaceWithAdmin();
    $member = User::factory()->create();
    memberOf($workspace, $member);

    $this->actingAs($member)
        ->postJson("/api/v1/workspaces/{$workspace->id}/projects", ['name' => 'Launch'])
        ->assertForbidden();

    expect(Project::count())->toBe(0);
});

it('answers 404 for a non-member', function () {
    [$workspace] = workspaceWithAdmin();

    $this->actingAs(User::factory()->create())
        ->postJson("/api/v1/workspaces/{$workspace->id}/projects", ['name' => 'Launch'])
        ->assertNotFound();

    expect(Project::count())->toBe(0);
});

it('requires authentication', function () {
    [$workspace] = workspaceWithAdmin();

    $this->postJson("/api/v1/workspaces/{$workspace->id}/projects", ['name' => 'Launch'])
        ->assertUnauthorized();
});

it('validates the project name', function (array $payload) {
    [$workspace, $admin] = workspaceWithAdmin();

    $this->actingAs($admin)
        ->postJson("/api/v1/workspaces/{$workspace->id}/projects", $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
})->with([
    'missing' => [[]],
    'empty' => [['name' => '']],
    'not a string' => [['name' => ['x']]],
    'too long' => [fn () => ['name' => str_repeat('a', 256)]],
]);

it('rejects an over-long description', function () {
    [$workspace, $admin] = workspaceWithAdmin();

    $this->actingAs($admin)
        ->postJson("/api/v1/workspaces/{$workspace->id}/projects", [
            'name' => 'Launch',
            'description' => str_repeat('a', 5001),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('description');
});

it('rejects a duplicate project name in the same workspace', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    Project::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Launch']);

    $this->actingAs($admin)
        ->postJson("/api/v1/workspaces/{$workspace->id}/projects", ['name' => 'Launch'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name')
        ->assertJsonPath('errors.name.0', 'A project with this name already exists in this workspace.');

    expect(Project::where('name', 'Launch')->count())->toBe(1);
});

it('rejects a name already taken by an archived project', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    Project::factory()->archived()->create(['workspace_id' => $workspace->id, 'name' => 'Launch']);

    $this->actingAs($admin)
        ->postJson("/api/v1/workspaces/{$workspace->id}/projects", ['name' => 'Launch'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

it('allows the same project name in a different workspace', function () {
    [$workspaceA, $adminA] = workspaceWithAdmin();
    [$workspaceB, $adminB] = workspaceWithAdmin();

    $this->actingAs($adminA)
        ->postJson("/api/v1/workspaces/{$workspaceA->id}/projects", ['name' => 'Launch'])
        ->assertCreated();

    $this->actingAs($adminB)
        ->postJson("/api/v1/workspaces/{$workspaceB->id}/projects", ['name' => 'Launch'])
        ->assertCreated();

    expect(Project::where('name', 'Launch')->count())->toBe(2);
});

it('ignores a workspace_id or status supplied in the payload', function () {
    [$workspaceA, $admin] = workspaceWithAdmin();
    [$workspaceB] = workspaceWithAdmin();

    $this->actingAs($admin)
        ->postJson("/api/v1/workspaces/{$workspaceA->id}/projects", [
            'name' => 'Launch',
            'workspace_id' => $workspaceB->id,
            'status' => ProjectStatus::Archived->value,
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', ProjectStatus::Active->value);

    $project = Project::sole();

    expect($project->workspace_id)->toBe($workspaceA->id)
        ->and($project->status)->toBe(ProjectStatus::Active);
});
