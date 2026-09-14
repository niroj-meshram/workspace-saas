<?php

use App\Enums\InvitationStatus;
use App\Models\User;
use App\Models\WorkspaceInvitation;

beforeEach(function () {
    [$this->workspace, $this->admin] = workspaceWithAdmin();
});

function anInvitation(array $attributes = []): WorkspaceInvitation
{
    return WorkspaceInvitation::factory()->create(array_merge([
        'workspace_id' => test()->workspace->id,
        'invited_by' => test()->admin->id,
    ], $attributes));
}

it('lets an admin list invitations', function () {
    anInvitation(['email' => 'a@example.com']);
    anInvitation(['email' => 'b@example.com']);

    $this->actingAs($this->admin)
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/invitations")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.invited_by.id', $this->admin->id);
});

it('reports the derived status of each invitation', function () {
    anInvitation(['email' => 'pending@example.com']);
    anInvitation(['email' => 'accepted@example.com', 'accepted_at' => now()]);
    WorkspaceInvitation::factory()->expired()->create([
        'workspace_id' => $this->workspace->id,
        'invited_by' => $this->admin->id,
        'email' => 'expired@example.com',
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/invitations")
        ->assertOk();

    $statuses = collect($response->json('data'))->pluck('status', 'email');

    expect($statuses['pending@example.com'])->toBe(InvitationStatus::Pending->value)
        ->and($statuses['accepted@example.com'])->toBe(InvitationStatus::Accepted->value)
        ->and($statuses['expired@example.com'])->toBe(InvitationStatus::Expired->value);
});

it('keeps accepted and expired invitations listed for history', function () {
    anInvitation(['email' => 'accepted@example.com', 'accepted_at' => now()]);
    WorkspaceInvitation::factory()->expired()->create([
        'workspace_id' => $this->workspace->id,
        'invited_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/invitations")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('never exposes the token hash in the listing', function () {
    $invitation = anInvitation();

    $response = $this->actingAs($this->admin)
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/invitations")
        ->assertOk();

    expect($response->getContent())->not->toContain($invitation->token_hash)
        ->and(array_keys($response->json('data.0')))->toBe([
            'id', 'email', 'role', 'status', 'expires_at', 'accepted_at',
            'invited_by', 'created_at', 'updated_at',
        ]);
});

it('lists only the invitations of the workspace in the route', function () {
    [$other, $otherAdmin] = workspaceWithAdmin();
    WorkspaceInvitation::factory()->create([
        'workspace_id' => $other->id,
        'invited_by' => $otherAdmin->id,
    ]);
    $mine = anInvitation();

    $response = $this->actingAs($this->admin)
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/invitations")
        ->assertOk();

    expect($response->json('data.*.id'))->toBe([$mine->id]);
});

it('paginates with standard metadata', function () {
    foreach (range(1, 5) as $i) {
        anInvitation(['email' => "user{$i}@example.com"]);
    }

    $this->actingAs($this->admin)
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/invitations?per_page=2")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 5)
        ->assertJsonPath('meta.per_page', 2);

    $this->actingAs($this->admin)
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/invitations?per_page=5000")
        ->assertJsonPath('meta.per_page', 100);
});

it('forbids a non-admin member from listing invitations', function () {
    anInvitation();
    $member = User::factory()->create();
    memberOf($this->workspace, $member);

    $this->actingAs($member)
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/invitations")
        ->assertForbidden();
});

it('lets an admin revoke a pending invitation', function () {
    $invitation = anInvitation();

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/workspaces/{$this->workspace->id}/invitations/{$invitation->id}")
        ->assertNoContent();

    expect($invitation->fresh()->trashed())->toBeTrue();
});

it('keeps the revoked row for history but drops it from the listing', function () {
    $invitation = anInvitation();

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/workspaces/{$this->workspace->id}/invitations/{$invitation->id}")
        ->assertNoContent();

    expect(WorkspaceInvitation::withTrashed()->find($invitation->id))->not->toBeNull();

    $this->actingAs($this->admin)
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/invitations")
        ->assertJsonPath('data', []);
});

it('frees the email for a fresh invitation once revoked', function () {
    $invitation = anInvitation(['email' => 'invitee@example.com']);

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/workspaces/{$this->workspace->id}/invitations/{$invitation->id}")
        ->assertNoContent();

    $this->actingAs($this->admin)
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/invitations", [
            'email' => 'invitee@example.com',
            'role' => 'user',
        ])
        ->assertCreated();
});

it('refuses to revoke an accepted invitation', function () {
    $invitation = anInvitation(['accepted_at' => now()]);

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/workspaces/{$this->workspace->id}/invitations/{$invitation->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', 'This invitation has already been accepted.');

    expect($invitation->fresh()->trashed())->toBeFalse();
});

it('forbids a non-admin member from revoking an invitation', function () {
    $invitation = anInvitation();
    $member = User::factory()->create();
    memberOf($this->workspace, $member);

    $this->actingAs($member)
        ->deleteJson("/api/v1/workspaces/{$this->workspace->id}/invitations/{$invitation->id}")
        ->assertForbidden();

    expect($invitation->fresh()->trashed())->toBeFalse();
});

it('answers 404 for an already revoked invitation', function () {
    $invitation = anInvitation();
    $invitation->delete();

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/workspaces/{$this->workspace->id}/invitations/{$invitation->id}")
        ->assertNotFound();
});

it('requires authentication', function () {
    $invitation = anInvitation();

    $this->getJson("/api/v1/workspaces/{$this->workspace->id}/invitations")->assertUnauthorized();
    $this->deleteJson("/api/v1/workspaces/{$this->workspace->id}/invitations/{$invitation->id}")
        ->assertUnauthorized();
});
