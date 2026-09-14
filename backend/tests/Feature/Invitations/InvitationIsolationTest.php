<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Cross-tenant access for invitations (PROJECT_SPEC.md §17)
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    [$this->workspaceA, $this->adminA] = workspaceWithAdmin();
    [$this->workspaceB, $this->adminB] = workspaceWithAdmin();

    $this->invitationB = WorkspaceInvitation::factory()->create([
        'workspace_id' => $this->workspaceB->id,
        'email' => 'theirs@example.com',
        'invited_by' => $this->adminB->id,
    ]);
});

it('blocks listing another workspace\'s invitations', function () {
    $this->actingAs($this->adminA)
        ->getJson("/api/v1/workspaces/{$this->workspaceB->id}/invitations")
        ->assertNotFound();
});

it('blocks inviting into another workspace', function () {
    $this->actingAs($this->adminA)
        ->postJson("/api/v1/workspaces/{$this->workspaceB->id}/invitations", [
            'email' => 'injected@example.com',
            'role' => 'admin',
        ])
        ->assertNotFound();

    expect(WorkspaceInvitation::where('email', 'injected@example.com')->count())->toBe(0);
});

it('blocks revoking another workspace\'s invitation', function () {
    $this->actingAs($this->adminA)
        ->deleteJson("/api/v1/workspaces/{$this->workspaceB->id}/invitations/{$this->invitationB->id}")
        ->assertNotFound();

    expect($this->invitationB->fresh()->trashed())->toBeFalse();
});

it('blocks revoking another workspace\'s invitation through your own workspace', function () {
    $this->actingAs($this->adminA)
        ->deleteJson("/api/v1/workspaces/{$this->workspaceA->id}/invitations/{$this->invitationB->id}")
        ->assertNotFound();

    expect($this->invitationB->fresh()->trashed())->toBeFalse();
});

it('answers a missing invitation and an inaccessible one identically', function () {
    $missing = $this->actingAs($this->adminA)
        ->deleteJson("/api/v1/workspaces/{$this->workspaceA->id}/invitations/".Str::uuid()->toString());

    $inaccessible = $this->actingAs($this->adminA)
        ->deleteJson("/api/v1/workspaces/{$this->workspaceA->id}/invitations/{$this->invitationB->id}");

    $missing->assertNotFound();
    $inaccessible->assertNotFound();

    expect($missing->json())->toBe($inaccessible->json());
});

it('does not let a token from one workspace grant access to another', function () {
    $token = bin2hex(random_bytes(32));
    $invitee = User::factory()->create(['email' => 'invitee@example.com']);

    WorkspaceInvitation::factory()->create([
        'workspace_id' => $this->workspaceB->id,
        'email' => 'invitee@example.com',
        'invited_by' => $this->adminB->id,
        'token_hash' => WorkspaceInvitation::hashToken($token),
    ]);

    $this->actingAs($invitee)->postJson("/api/v1/invitations/{$token}/accept")->assertCreated();

    // Membership landed in workspace B only.
    expect(WorkspaceMember::where('user_id', $invitee->id)->pluck('workspace_id')->all())
        ->toBe([$this->workspaceB->id]);

    $this->actingAs($invitee)
        ->getJson("/api/v1/workspaces/{$this->workspaceA->id}")
        ->assertNotFound();
});

it('does not let an admin of one workspace accept an invitation addressed elsewhere', function () {
    $token = bin2hex(random_bytes(32));

    WorkspaceInvitation::factory()->create([
        'workspace_id' => $this->workspaceB->id,
        'email' => 'someone@example.com',
        'invited_by' => $this->adminB->id,
        'token_hash' => WorkspaceInvitation::hashToken($token),
    ]);

    $this->actingAs($this->adminA)
        ->postJson("/api/v1/invitations/{$token}/accept")
        ->assertForbidden();

    expect(WorkspaceMember::where('user_id', $this->adminA->id)->count())->toBe(1);
});

it('ignores a workspace_id or role supplied when accepting', function () {
    $token = bin2hex(random_bytes(32));
    $invitee = User::factory()->create(['email' => 'invitee@example.com']);

    WorkspaceInvitation::factory()->create([
        'workspace_id' => $this->workspaceB->id,
        'email' => 'invitee@example.com',
        'role' => WorkspaceRole::User,
        'invited_by' => $this->adminB->id,
        'token_hash' => WorkspaceInvitation::hashToken($token),
    ]);

    $this->actingAs($invitee)
        ->postJson("/api/v1/invitations/{$token}/accept", [
            'workspace_id' => $this->workspaceA->id,
            'role' => 'admin',
        ])
        ->assertCreated();

    $member = WorkspaceMember::where('user_id', $invitee->id)->sole();

    expect($member->workspace_id)->toBe($this->workspaceB->id)
        ->and($member->role)->toBe(WorkspaceRole::User);
});
