<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;

it('returns only workspaces the user is an active member of', function () {
    $user = User::factory()->create();

    $mine = Workspace::factory()->create(['name' => 'Mine']);
    memberOf($mine, $user, WorkspaceRole::Admin);

    $alsoMine = Workspace::factory()->create(['name' => 'Also mine']);
    memberOf($alsoMine, $user);

    // Someone else's workspace.
    [$theirs] = workspaceWithAdmin();

    $response = $this->actingAs($user)->getJson('/api/v1/workspaces')->assertOk();

    expect($response->json('data.*.id'))
        ->toHaveCount(2)
        ->toContain($mine->id, $alsoMine->id)
        ->not->toContain($theirs->id);
});

it('returns an empty collection when the user belongs to no workspace', function () {
    workspaceWithAdmin();

    $this->actingAs(User::factory()->create())
        ->getJson('/api/v1/workspaces')
        ->assertOk()
        ->assertJsonPath('data', []);
});

it('excludes workspaces the user was removed from', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    $member = memberOf($workspace, $user);

    $this->actingAs($user)->getJson('/api/v1/workspaces')->assertJsonCount(1, 'data');

    $member->delete();

    $this->actingAs($user)->getJson('/api/v1/workspaces')->assertJsonPath('data', []);
});

it('excludes soft deleted workspaces', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    memberOf($workspace, $user, WorkspaceRole::Admin);

    $this->actingAs($user)->getJson('/api/v1/workspaces')->assertJsonCount(1, 'data');

    $workspace->delete();

    $this->actingAs($user)->getJson('/api/v1/workspaces')->assertJsonPath('data', []);
});

it('requires authentication', function () {
    $this->getJson('/api/v1/workspaces')->assertUnauthorized();
});

it('does not expose tenant internals in the payload', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    memberOf($workspace, $user);

    $response = $this->actingAs($user)->getJson('/api/v1/workspaces')->assertOk();

    expect(array_keys($response->json('data.0')))
        ->toBe(['id', 'name', 'role', 'created_at', 'updated_at']);
});

it('reports the viewer\'s own role on each workspace', function () {
    $user = User::factory()->create();
    $asAdmin = Workspace::factory()->create(['name' => 'A admin here']);
    memberOf($asAdmin, $user, WorkspaceRole::Admin);
    $asUser = Workspace::factory()->create(['name' => 'B member here']);
    memberOf($asUser, $user);

    $response = $this->actingAs($user)->getJson('/api/v1/workspaces')->assertOk();

    expect($response->json('data.0.role'))->toBe(WorkspaceRole::Admin->value)
        ->and($response->json('data.1.role'))->toBe(WorkspaceRole::User->value);
});

it('reports the viewer\'s role when reading one workspace', function () {
    $workspace = Workspace::factory()->create();
    $user = User::factory()->create();
    memberOf($workspace, $user);

    $this->actingAs($user)
        ->getJson("/api/v1/workspaces/{$workspace->id}")
        ->assertOk()
        ->assertJsonPath('data.role', WorkspaceRole::User->value);
});

it('reports admin to the creator of a new workspace', function () {
    $this->actingAs(User::factory()->create())
        ->postJson('/api/v1/workspaces', ['name' => 'Acme'])
        ->assertCreated()
        ->assertJsonPath('data.role', WorkspaceRole::Admin->value);
});
