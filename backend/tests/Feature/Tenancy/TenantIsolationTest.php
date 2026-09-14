<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Cross-tenant access (PROJECT_SPEC.md §17)
|--------------------------------------------------------------------------
|
| A user in workspace A must never reach workspace B. Everything here asserts
| 404 rather than 403: an inaccessible tenant resource must be
| indistinguishable from one that does not exist (PROJECT_SPEC.md §14).
|
*/

beforeEach(function () {
    [$this->workspaceA, $this->userA] = workspaceWithAdmin();
    [$this->workspaceB, $this->userB, $this->memberB] = workspaceWithAdmin();
});

it('blocks reading another workspace', function () {
    $this->actingAs($this->userA)
        ->getJson("/api/v1/workspaces/{$this->workspaceB->id}")
        ->assertNotFound();
});

it('blocks updating another workspace', function () {
    $this->actingAs($this->userA)
        ->patchJson("/api/v1/workspaces/{$this->workspaceB->id}", ['name' => 'Hijacked'])
        ->assertNotFound();

    expect($this->workspaceB->fresh()->name)->not->toBe('Hijacked');
});

it('blocks deleting another workspace', function () {
    $this->actingAs($this->userA)
        ->deleteJson("/api/v1/workspaces/{$this->workspaceB->id}")
        ->assertNotFound();

    expect($this->workspaceB->fresh()->trashed())->toBeFalse();
});

it('blocks listing another workspace\'s members', function () {
    $this->actingAs($this->userA)
        ->getJson("/api/v1/workspaces/{$this->workspaceB->id}/members")
        ->assertNotFound();
});

it('blocks changing a role in another workspace', function () {
    $this->actingAs($this->userA)
        ->patchJson("/api/v1/workspaces/{$this->workspaceB->id}/members/{$this->memberB->id}", [
            'role' => WorkspaceRole::User->value,
        ])
        ->assertNotFound();

    expect($this->memberB->fresh()->role)->toBe(WorkspaceRole::Admin);
});

it('blocks removing a member of another workspace', function () {
    $this->actingAs($this->userA)
        ->deleteJson("/api/v1/workspaces/{$this->workspaceB->id}/members/{$this->memberB->id}")
        ->assertNotFound();

    expect($this->memberB->fresh()->trashed())->toBeFalse();
});

it('blocks addressing a membership from another workspace through your own workspace', function () {
    // An admin of A, using A's id in the route, but pointing at B's membership.
    $this->actingAs($this->userA)
        ->patchJson("/api/v1/workspaces/{$this->workspaceA->id}/members/{$this->memberB->id}", [
            'role' => WorkspaceRole::User->value,
        ])
        ->assertNotFound();

    expect($this->memberB->fresh()->role)->toBe(WorkspaceRole::Admin);
});

it('blocks removing a membership from another workspace through your own workspace', function () {
    $this->actingAs($this->userA)
        ->deleteJson("/api/v1/workspaces/{$this->workspaceA->id}/members/{$this->memberB->id}")
        ->assertNotFound();

    expect($this->memberB->fresh()->trashed())->toBeFalse();
});

it('requires membership, not merely a valid workspace id', function () {
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->getJson("/api/v1/workspaces/{$this->workspaceA->id}")
        ->assertNotFound();
});

it('stops granting access once the membership is soft deleted', function () {
    $user = User::factory()->create();
    $member = memberOf($this->workspaceA, $user);

    $this->actingAs($user)->getJson("/api/v1/workspaces/{$this->workspaceA->id}")->assertOk();

    $member->delete();

    $this->actingAs($user)->getJson("/api/v1/workspaces/{$this->workspaceA->id}")->assertNotFound();
});

it('answers a missing workspace and an inaccessible one identically', function () {
    $missing = $this->actingAs($this->userA)
        ->getJson('/api/v1/workspaces/'.Str::uuid()->toString());

    $inaccessible = $this->actingAs($this->userA)
        ->getJson("/api/v1/workspaces/{$this->workspaceB->id}");

    $missing->assertNotFound();
    $inaccessible->assertNotFound();

    // Identical bodies: the API must not leak which workspace ids are real.
    expect($missing->json())->toBe($inaccessible->json());
});

it('never lets a workspace id in the payload override the route', function () {
    $member = User::factory()->create();
    memberOf($this->workspaceA, $member, WorkspaceRole::Admin);

    $this->actingAs($member)
        ->patchJson("/api/v1/workspaces/{$this->workspaceA->id}", [
            'name' => 'Renamed',
            'workspace_id' => $this->workspaceB->id,
            'id' => $this->workspaceB->id,
        ])
        ->assertOk();

    expect($this->workspaceA->fresh()->name)->toBe('Renamed')
        ->and($this->workspaceB->fresh()->name)->not->toBe('Renamed');
});
