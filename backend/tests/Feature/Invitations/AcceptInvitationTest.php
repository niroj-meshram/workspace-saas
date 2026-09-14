<?php

use App\Enums\ActivityType;
use App\Enums\WorkspaceRole;
use App\Models\Activity;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\DB;

/**
 * Create an invitation directly and hand back the raw token, the way the email
 * would. Nothing else in the system can recover it.
 *
 * @return array{0: WorkspaceInvitation, 1: string}
 */
function invitationWithToken(array $attributes = []): array
{
    $token = bin2hex(random_bytes(32));

    $invitation = WorkspaceInvitation::factory()->create(array_merge([
        'token_hash' => WorkspaceInvitation::hashToken($token),
    ], $attributes));

    return [$invitation, $token];
}

beforeEach(function () {
    [$this->workspace, $this->admin] = workspaceWithAdmin();
    $this->invitee = User::factory()->create(['email' => 'invitee@example.com']);

    [$this->invitation, $this->token] = invitationWithToken([
        'workspace_id' => $this->workspace->id,
        'email' => 'invitee@example.com',
        'role' => WorkspaceRole::User,
        'invited_by' => $this->admin->id,
    ]);
});

function accept(mixed $actor, ?string $token = null)
{
    return test()->actingAs($actor)->postJson(
        '/api/v1/invitations/'.($token ?? test()->token).'/accept'
    );
}

it('lets the invited user accept', function () {
    accept($this->invitee)
        ->assertCreated()
        ->assertJsonPath('data.id', $this->workspace->id)
        ->assertJsonPath('data.name', $this->workspace->name);
});

it('creates an active membership with the invited role', function () {
    accept($this->invitee)->assertCreated();

    $member = WorkspaceMember::where('user_id', $this->invitee->id)->sole();

    expect($member->workspace_id)->toBe($this->workspace->id)
        ->and($member->role)->toBe(WorkspaceRole::User)
        ->and($member->trashed())->toBeFalse();
});

it('applies the admin role when that is what was invited', function () {
    [$invitation, $token] = invitationWithToken([
        'workspace_id' => $this->workspace->id,
        'email' => 'boss@example.com',
        'role' => WorkspaceRole::Admin,
        'invited_by' => $this->admin->id,
    ]);
    $boss = User::factory()->create(['email' => 'boss@example.com']);

    accept($boss, $token)->assertCreated();

    expect(WorkspaceMember::where('user_id', $boss->id)->sole()->role)
        ->toBe(WorkspaceRole::Admin);
});

it('marks the invitation accepted', function () {
    $this->freezeTime();

    accept($this->invitee)->assertCreated();

    expect($this->invitation->fresh()->accepted_at)->not->toBeNull()
        ->and($this->invitation->fresh()->isAccepted())->toBeTrue();
});

it('grants access to the workspace immediately', function () {
    $this->actingAs($this->invitee)
        ->getJson("/api/v1/workspaces/{$this->workspace->id}")
        ->assertNotFound();

    accept($this->invitee)->assertCreated();

    $this->actingAs($this->invitee)
        ->getJson("/api/v1/workspaces/{$this->workspace->id}")
        ->assertOk();

    $this->actingAs($this->invitee)
        ->getJson('/api/v1/workspaces')
        ->assertJsonCount(1, 'data');
});

it('requires authentication to accept', function () {
    $this->postJson("/api/v1/invitations/{$this->token}/accept")->assertUnauthorized();

    expect(WorkspaceMember::where('user_id', $this->invitee->id)->exists())->toBeFalse();
});

it('refuses a user whose email does not match the invitation', function () {
    $stranger = User::factory()->create(['email' => 'someone.else@example.com']);

    accept($stranger)->assertForbidden();

    expect($this->invitation->fresh()->isAccepted())->toBeFalse()
        ->and(WorkspaceMember::where('user_id', $stranger->id)->exists())->toBeFalse();
});

it('matches the email regardless of casing', function () {
    $invitee = User::factory()->create(['email' => 'mixed@example.com']);
    [, $token] = invitationWithToken([
        'workspace_id' => $this->workspace->id,
        'email' => 'MIXED@example.com',
        'invited_by' => $this->admin->id,
    ]);

    accept($invitee, $token)->assertCreated();
});

it('cannot be accepted twice', function () {
    accept($this->invitee)->assertCreated();

    accept($this->invitee)
        ->assertStatus(409)
        ->assertJsonPath('message', 'This invitation has already been accepted.');

    expect(WorkspaceMember::where('user_id', $this->invitee->id)->count())->toBe(1);
});

it('refuses an expired invitation', function () {
    $this->invitation->expires_at = now()->subDay();
    $this->invitation->save();

    accept($this->invitee)
        ->assertStatus(409)
        ->assertJsonPath('message', 'This invitation has expired.');

    expect(WorkspaceMember::where('user_id', $this->invitee->id)->exists())->toBeFalse()
        ->and($this->invitation->fresh()->isAccepted())->toBeFalse();
});

it('accepts right up to the expiry moment and not after', function () {
    $this->travelTo($this->invitation->expires_at->subSecond());
    accept($this->invitee)->assertCreated();

    [, $token] = invitationWithToken([
        'workspace_id' => $this->workspace->id,
        'email' => 'late@example.com',
        'invited_by' => $this->admin->id,
        'expires_at' => now()->addDays(7),
    ]);
    $late = User::factory()->create(['email' => 'late@example.com']);

    $this->travel(8)->days();
    accept($late, $token)->assertStatus(409);
});

it('answers 404 for an unknown token', function () {
    accept($this->invitee, bin2hex(random_bytes(32)))->assertNotFound();
});

it('answers 404 for a revoked invitation', function () {
    $this->invitation->delete();

    accept($this->invitee)->assertNotFound();

    expect(WorkspaceMember::where('user_id', $this->invitee->id)->exists())->toBeFalse();
});

it('cannot be accepted with the stored hash instead of the token', function () {
    accept($this->invitee, $this->invitation->token_hash)->assertNotFound();

    expect($this->invitation->fresh()->isAccepted())->toBeFalse();
});

it('refuses when the user is already a member', function () {
    memberOf($this->workspace, $this->invitee);

    accept($this->invitee)
        ->assertStatus(409)
        ->assertJsonPath('message', 'That person is already a member of this workspace.');

    expect($this->invitation->fresh()->isAccepted())->toBeFalse();
});

it('records invitation.accepted', function () {
    accept($this->invitee)->assertCreated();

    $activity = Activity::sole();
    $member = WorkspaceMember::where('user_id', $this->invitee->id)->sole();

    expect($activity->type)->toBe(ActivityType::InvitationAccepted)
        ->and($activity->workspace_id)->toBe($this->workspace->id)
        ->and($activity->user_id)->toBe($this->invitee->id)
        ->and($activity->subject_type)->toBe('workspace_invitation')
        ->and($activity->subject_id)->toBe($this->invitation->id)
        ->and($activity->metadata)->toEqual([
            'email' => 'invitee@example.com',
            'role' => WorkspaceRole::User->value,
            'member_id' => $member->id,
        ]);
});

it('rolls the whole acceptance back when the membership cannot be written', function () {
    DB::listen(function ($query) {
        if (str_contains($query->sql, 'insert into "workspace_members"')) {
            throw new RuntimeException('membership insert failed');
        }
    });

    $this->withoutExceptionHandling();

    expect(fn () => accept($this->invitee))
        ->toThrow(RuntimeException::class, 'membership insert failed');

    $this->invitation->refresh();

    expect($this->invitation->isAccepted())->toBeFalse()
        ->and(WorkspaceMember::where('user_id', $this->invitee->id)->exists())->toBeFalse()
        ->and(Activity::count())->toBe(0);
});

it('rolls the membership back when the invitation cannot be marked accepted', function () {
    DB::listen(function ($query) {
        if (str_contains($query->sql, 'update "workspace_invitations"')) {
            throw new RuntimeException('accept mark failed');
        }
    });

    $this->withoutExceptionHandling();

    expect(fn () => accept($this->invitee))
        ->toThrow(RuntimeException::class, 'accept mark failed');

    expect(WorkspaceMember::where('user_id', $this->invitee->id)->exists())->toBeFalse()
        ->and($this->invitation->fresh()->isAccepted())->toBeFalse();
});
