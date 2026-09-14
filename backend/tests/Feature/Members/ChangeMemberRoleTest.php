<?php

use App\Enums\WorkspaceRole;
use App\Models\User;

it('lets an admin promote a member to admin', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    $member = memberOf($workspace, User::factory()->create());

    $this->actingAs($admin)
        ->patchJson("/api/v1/workspaces/{$workspace->id}/members/{$member->id}", [
            'role' => WorkspaceRole::Admin->value,
        ])
        ->assertOk()
        ->assertJsonPath('data.id', $member->id)
        ->assertJsonPath('data.role', WorkspaceRole::Admin->value);

    expect($member->fresh()->role)->toBe(WorkspaceRole::Admin);
});

it('lets an admin demote another admin while one remains', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    $other = memberOf($workspace, User::factory()->create(), WorkspaceRole::Admin);

    $this->actingAs($admin)
        ->patchJson("/api/v1/workspaces/{$workspace->id}/members/{$other->id}", [
            'role' => WorkspaceRole::User->value,
        ])
        ->assertOk();

    expect($other->fresh()->role)->toBe(WorkspaceRole::User);
});

it('forbids a non-admin member from changing roles', function () {
    [$workspace, $admin, $adminMember] = workspaceWithAdmin();
    $actor = User::factory()->create();
    memberOf($workspace, $actor);

    $this->actingAs($actor)
        ->patchJson("/api/v1/workspaces/{$workspace->id}/members/{$adminMember->id}", [
            'role' => WorkspaceRole::User->value,
        ])
        ->assertForbidden();

    expect($adminMember->fresh()->role)->toBe(WorkspaceRole::Admin);
});

it('forbids a member from promoting themselves', function () {
    [$workspace] = workspaceWithAdmin();
    $actor = User::factory()->create();
    $self = memberOf($workspace, $actor);

    $this->actingAs($actor)
        ->patchJson("/api/v1/workspaces/{$workspace->id}/members/{$self->id}", [
            'role' => WorkspaceRole::Admin->value,
        ])
        ->assertForbidden();

    expect($self->fresh()->role)->toBe(WorkspaceRole::User);
});

it('refuses to demote the last admin', function () {
    [$workspace, $admin, $adminMember] = workspaceWithAdmin();
    memberOf($workspace, User::factory()->create());

    $this->actingAs($admin)
        ->patchJson("/api/v1/workspaces/{$workspace->id}/members/{$adminMember->id}", [
            'role' => WorkspaceRole::User->value,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('role')
        ->assertJsonPath('errors.role.0', 'A workspace must always have at least one admin.');

    expect($adminMember->fresh()->role)->toBe(WorkspaceRole::Admin);
});

it('refuses to demote the last admin even when other admins were removed', function () {
    [$workspace, $admin, $adminMember] = workspaceWithAdmin();
    $secondAdmin = memberOf($workspace, User::factory()->create(), WorkspaceRole::Admin);
    $secondAdmin->delete();

    $this->actingAs($admin)
        ->patchJson("/api/v1/workspaces/{$workspace->id}/members/{$adminMember->id}", [
            'role' => WorkspaceRole::User->value,
        ])
        ->assertStatus(422);

    expect($adminMember->fresh()->role)->toBe(WorkspaceRole::Admin);
});

it('allows setting the last admin back to admin', function () {
    [$workspace, $admin, $adminMember] = workspaceWithAdmin();

    $this->actingAs($admin)
        ->patchJson("/api/v1/workspaces/{$workspace->id}/members/{$adminMember->id}", [
            'role' => WorkspaceRole::Admin->value,
        ])
        ->assertOk();

    expect($adminMember->fresh()->role)->toBe(WorkspaceRole::Admin);
});

it('validates the role', function (mixed $role) {
    [$workspace, $admin] = workspaceWithAdmin();
    $member = memberOf($workspace, User::factory()->create());

    $this->actingAs($admin)
        ->patchJson("/api/v1/workspaces/{$workspace->id}/members/{$member->id}", ['role' => $role])
        ->assertStatus(422)
        ->assertJsonValidationErrors('role');

    expect($member->fresh()->role)->toBe(WorkspaceRole::User);
})->with([
    'unknown role' => ['owner'],
    'empty' => [''],
    'uppercase' => ['ADMIN'],
]);

it('requires a role to be sent', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    $member = memberOf($workspace, User::factory()->create());

    $this->actingAs($admin)
        ->patchJson("/api/v1/workspaces/{$workspace->id}/members/{$member->id}", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('role');
});

it('answers 404 for a membership that was already removed', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    $member = memberOf($workspace, User::factory()->create());
    $member->delete();

    $this->actingAs($admin)
        ->patchJson("/api/v1/workspaces/{$workspace->id}/members/{$member->id}", [
            'role' => WorkspaceRole::Admin->value,
        ])
        ->assertNotFound();
});

it('requires authentication', function () {
    [$workspace, $admin, $adminMember] = workspaceWithAdmin();

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/members/{$adminMember->id}", [
        'role' => WorkspaceRole::User->value,
    ])->assertUnauthorized();
});
