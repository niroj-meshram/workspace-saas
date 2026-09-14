<?php

use App\Enums\ActivityType;
use App\Enums\InvitationStatus;
use App\Enums\WorkspaceRole;
use App\Mail\WorkspaceInvitationMail;
use App\Models\Activity;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    [$this->workspace, $this->admin] = workspaceWithAdmin();
});

function invite(mixed $actor, array $payload = [])
{
    return test()->actingAs($actor)->postJson(
        '/api/v1/workspaces/'.test()->workspace->id.'/invitations',
        array_merge(['email' => 'invitee@example.com', 'role' => 'user'], $payload),
    );
}

it('lets an admin invite someone', function () {
    $response = invite($this->admin);

    $response->assertCreated()
        ->assertJsonPath('data.email', 'invitee@example.com')
        ->assertJsonPath('data.role', WorkspaceRole::User->value)
        ->assertJsonPath('data.status', InvitationStatus::Pending->value)
        ->assertJsonPath('data.invited_by.id', $this->admin->id);

    $invitation = WorkspaceInvitation::sole();

    expect($invitation->workspace_id)->toBe($this->workspace->id)
        ->and($invitation->invited_by)->toBe($this->admin->id)
        ->and($invitation->accepted_at)->toBeNull();
});

it('invites with the admin role when asked', function () {
    invite($this->admin, ['role' => 'admin'])
        ->assertCreated()
        ->assertJsonPath('data.role', WorkspaceRole::Admin->value);

    expect(WorkspaceInvitation::sole()->role)->toBe(WorkspaceRole::Admin);
});

it('expires the invitation after seven days', function () {
    $this->freezeTime();

    invite($this->admin)->assertCreated();

    expect(WorkspaceInvitation::sole()->expires_at->toIso8601String())
        ->toBe(now()->addDays(7)->toIso8601String());
});

it('returns the documented invitation shape', function () {
    $response = invite($this->admin)->assertCreated();

    expect(array_keys($response->json('data')))->toBe([
        'id', 'email', 'role', 'status', 'expires_at', 'accepted_at',
        'invited_by', 'created_at', 'updated_at',
    ]);
});

it('forbids a non-admin member from inviting', function () {
    $member = User::factory()->create();
    memberOf($this->workspace, $member);

    invite($member)->assertForbidden();

    expect(WorkspaceInvitation::count())->toBe(0);
});

it('answers 404 for a non-member', function () {
    invite(User::factory()->create())->assertNotFound();

    expect(WorkspaceInvitation::count())->toBe(0);
});

it('requires authentication', function () {
    $this->postJson("/api/v1/workspaces/{$this->workspace->id}/invitations", [
        'email' => 'invitee@example.com',
        'role' => 'user',
    ])->assertUnauthorized();
});

it('validates the payload', function (array $payload, string $field) {
    $this->actingAs($this->admin)
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/invitations", $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors($field);
})->with([
    'no email' => [['role' => 'user'], 'email'],
    'malformed email' => [['email' => 'nope', 'role' => 'user'], 'email'],
    'no role' => [['email' => 'a@example.com'], 'role'],
    'unknown role' => [['email' => 'a@example.com', 'role' => 'owner'], 'role'],
]);

it('lowercases the invited email', function () {
    invite($this->admin, ['email' => 'Invitee@Example.COM'])->assertCreated();

    expect(WorkspaceInvitation::sole()->email)->toBe('invitee@example.com');
});

it('refuses to invite someone who is already an active member', function () {
    $member = User::factory()->create(['email' => 'member@example.com']);
    memberOf($this->workspace, $member);

    invite($this->admin, ['email' => 'member@example.com'])
        ->assertStatus(409)
        ->assertJsonPath('message', 'That person is already a member of this workspace.');

    expect(WorkspaceInvitation::count())->toBe(0);
});

it('allows inviting someone whose membership was removed', function () {
    $former = User::factory()->create(['email' => 'former@example.com']);
    memberOf($this->workspace, $former)->delete();

    invite($this->admin, ['email' => 'former@example.com'])->assertCreated();
});

it('refuses a second pending invitation for the same email', function () {
    invite($this->admin)->assertCreated();

    invite($this->admin)
        ->assertStatus(409)
        ->assertJsonPath('message', 'There is already a pending invitation for this email address.');

    expect(WorkspaceInvitation::count())->toBe(1);
});

it('allows the same email to be invited to a different workspace', function () {
    [$other, $otherAdmin] = workspaceWithAdmin();

    invite($this->admin)->assertCreated();

    $this->actingAs($otherAdmin)
        ->postJson("/api/v1/workspaces/{$other->id}/invitations", [
            'email' => 'invitee@example.com',
            'role' => 'user',
        ])
        ->assertCreated();

    expect(WorkspaceInvitation::where('email', 'invitee@example.com')->count())->toBe(2);
});

it('supersedes an expired invitation instead of blocking a new one', function () {
    $expired = WorkspaceInvitation::factory()->expired()->create([
        'workspace_id' => $this->workspace->id,
        'email' => 'invitee@example.com',
        'invited_by' => $this->admin->id,
    ]);

    invite($this->admin)->assertCreated();

    // The expired one is retained for history, just revoked.
    expect($expired->fresh()->trashed())->toBeTrue()
        ->and(WorkspaceInvitation::where('email', 'invitee@example.com')->count())->toBe(1)
        ->and(WorkspaceInvitation::sole()->isPending())->toBeTrue();
});

it('never stores the token in plaintext', function () {
    invite($this->admin)->assertCreated();

    $invitation = WorkspaceInvitation::sole();

    // Only a 64-character SHA-256 digest is on the row.
    expect($invitation->token_hash)->toMatch('/^[0-9a-f]{64}$/');

    $token = null;

    Mail::assertSent(WorkspaceInvitationMail::class, function ($mail) use (&$token) {
        $token = str($mail->acceptUrl)->afterLast('/')->toString();

        return true;
    });

    expect($token)->toMatch('/^[0-9a-f]{64}$/')
        ->and($invitation->token_hash)->not->toBe($token)
        ->and($invitation->token_hash)->toBe(hash('sha256', $token));
});

it('never exposes the token or its hash in the response', function () {
    $response = invite($this->admin)->assertCreated();

    $token = null;
    Mail::assertSent(WorkspaceInvitationMail::class, function ($mail) use (&$token) {
        $token = str($mail->acceptUrl)->afterLast('/')->toString();

        return true;
    });

    expect($response->getContent())
        ->not->toContain($token)
        ->not->toContain(WorkspaceInvitation::sole()->token_hash)
        ->not->toContain('token');
});

it('sends the invitation email to the invited address', function () {
    invite($this->admin)->assertCreated();

    Mail::assertSent(
        WorkspaceInvitationMail::class,
        fn ($mail) => $mail->hasTo('invitee@example.com')
            && str_starts_with($mail->acceptUrl, config('app.frontend_url').'/invitations/')
    );
});

it('records member.invited', function () {
    invite($this->admin)->assertCreated();

    $activity = Activity::sole();

    expect($activity->type)->toBe(ActivityType::MemberInvited)
        ->and($activity->workspace_id)->toBe($this->workspace->id)
        ->and($activity->user_id)->toBe($this->admin->id)
        ->and($activity->subject_type)->toBe('workspace_invitation')
        ->and($activity->subject_id)->toBe(WorkspaceInvitation::sole()->id)
        ->and($activity->metadata)->toEqual([
            'email' => 'invitee@example.com',
            'role' => WorkspaceRole::User->value,
        ]);
});

it('records no activity when the invitation is refused', function () {
    $member = User::factory()->create(['email' => 'member@example.com']);
    memberOf($this->workspace, $member);

    invite($this->admin, ['email' => 'member@example.com'])->assertStatus(409);

    expect(Activity::count())->toBe(0);
});
