<?php

use App\Enums\WorkspaceRole;
use App\Models\User;

it('lets any member list the members', function (WorkspaceRole $role) {
    [$workspace, $admin] = workspaceWithAdmin();
    $viewer = User::factory()->create();
    memberOf($workspace, $viewer, $role);

    $response = $this->actingAs($viewer)
        ->getJson("/api/v1/workspaces/{$workspace->id}/members")
        ->assertOk();

    expect($response->json('data.*.id'))->toHaveCount(2);
})->with([
    'admin' => [WorkspaceRole::Admin],
    'user' => [WorkspaceRole::User],
]);

it('includes the role and the user for each membership', function () {
    [$workspace, $admin] = workspaceWithAdmin();

    $response = $this->actingAs($admin)
        ->getJson("/api/v1/workspaces/{$workspace->id}/members")
        ->assertOk()
        ->assertJsonPath('data.0.role', WorkspaceRole::Admin->value)
        ->assertJsonPath('data.0.user.id', $admin->id)
        ->assertJsonPath('data.0.user.email', $admin->email);

    expect(array_keys($response->json('data.0')))
        ->toBe(['id', 'role', 'user', 'created_at', 'updated_at']);
});

it('never exposes the member password hash', function () {
    [$workspace, $admin] = workspaceWithAdmin();

    $response = $this->actingAs($admin)->getJson("/api/v1/workspaces/{$workspace->id}/members");

    expect($response->getContent())->not->toContain($admin->password)
        ->and(array_keys($response->json('data.0.user')))
        ->toBe(['id', 'name', 'email', 'created_at', 'updated_at']);
});

it('excludes removed members', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    $removed = memberOf($workspace, User::factory()->create());

    $this->actingAs($admin)
        ->getJson("/api/v1/workspaces/{$workspace->id}/members")
        ->assertJsonCount(2, 'data');

    $removed->delete();

    $response = $this->actingAs($admin)
        ->getJson("/api/v1/workspaces/{$workspace->id}/members")
        ->assertJsonCount(1, 'data');

    expect($response->json('data.*.id'))->not->toContain($removed->id);
});

it('returns only the members of the workspace in the route', function () {
    [$workspaceA, $adminA] = workspaceWithAdmin();
    [$workspaceB] = workspaceWithAdmin();
    memberOf($workspaceB, User::factory()->create());

    $response = $this->actingAs($adminA)
        ->getJson("/api/v1/workspaces/{$workspaceA->id}/members")
        ->assertOk();

    expect($response->json('data.*.id'))->toHaveCount(1);
});

it('paginates with standard metadata', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    foreach (range(1, 4) as $ignored) {
        memberOf($workspace, User::factory()->create());
    }

    $this->actingAs($admin)
        ->getJson("/api/v1/workspaces/{$workspace->id}/members?per_page=2")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 5)
        ->assertJsonPath('meta.per_page', 2);
});

it('caps the page size at 100', function () {
    [$workspace, $admin] = workspaceWithAdmin();

    $this->actingAs($admin)
        ->getJson("/api/v1/workspaces/{$workspace->id}/members?per_page=5000")
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100);
});

it('requires authentication', function () {
    [$workspace] = workspaceWithAdmin();

    $this->getJson("/api/v1/workspaces/{$workspace->id}/members")->assertUnauthorized();
});
