<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;

it('lets any member read the workspace', function (WorkspaceRole $role) {
    $workspace = Workspace::factory()->create(['name' => 'Acme']);
    $user = User::factory()->create();
    memberOf($workspace, $user, $role);

    $this->actingAs($user)
        ->getJson("/api/v1/workspaces/{$workspace->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $workspace->id)
        ->assertJsonPath('data.name', 'Acme');
})->with([
    'admin' => [WorkspaceRole::Admin],
    'user' => [WorkspaceRole::User],
]);

it('lets an admin rename the workspace', function () {
    [$workspace, $admin] = workspaceWithAdmin();

    $this->actingAs($admin)
        ->patchJson("/api/v1/workspaces/{$workspace->id}", ['name' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed');

    expect($workspace->fresh()->name)->toBe('Renamed');
});

it('forbids a non-admin member from renaming the workspace', function () {
    [$workspace] = workspaceWithAdmin();
    $member = User::factory()->create();
    memberOf($workspace, $member);

    $this->actingAs($member)
        ->patchJson("/api/v1/workspaces/{$workspace->id}", ['name' => 'Renamed'])
        ->assertForbidden();

    expect($workspace->fresh()->name)->not->toBe('Renamed');
});

it('validates the name on update', function () {
    [$workspace, $admin] = workspaceWithAdmin();

    $this->actingAs($admin)
        ->patchJson("/api/v1/workspaces/{$workspace->id}", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

it('lets an admin soft delete the workspace', function () {
    [$workspace, $admin] = workspaceWithAdmin();

    $this->actingAs($admin)
        ->deleteJson("/api/v1/workspaces/{$workspace->id}")
        ->assertNoContent();

    expect($workspace->fresh()->trashed())->toBeTrue();
});

it('preserves memberships when the workspace is deleted', function () {
    [$workspace, $admin, $membership] = workspaceWithAdmin();

    $this->actingAs($admin)->deleteJson("/api/v1/workspaces/{$workspace->id}")->assertNoContent();

    expect($membership->fresh())->not->toBeNull()
        ->and($membership->fresh()->trashed())->toBeFalse();
});

it('forbids a non-admin member from deleting the workspace', function () {
    [$workspace] = workspaceWithAdmin();
    $member = User::factory()->create();
    memberOf($workspace, $member);

    $this->actingAs($member)
        ->deleteJson("/api/v1/workspaces/{$workspace->id}")
        ->assertForbidden();

    expect($workspace->fresh()->trashed())->toBeFalse();
});

it('answers 404 for a deleted workspace', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    $workspace->delete();

    $this->actingAs($admin)
        ->getJson("/api/v1/workspaces/{$workspace->id}")
        ->assertNotFound();
});

it('answers 404 for an id that is not a uuid', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/api/v1/workspaces/not-a-uuid')
        ->assertNotFound();
});

it('requires authentication', function () {
    [$workspace] = workspaceWithAdmin();

    $this->getJson("/api/v1/workspaces/{$workspace->id}")->assertUnauthorized();
    $this->patchJson("/api/v1/workspaces/{$workspace->id}", ['name' => 'x'])->assertUnauthorized();
    $this->deleteJson("/api/v1/workspaces/{$workspace->id}")->assertUnauthorized();
});
