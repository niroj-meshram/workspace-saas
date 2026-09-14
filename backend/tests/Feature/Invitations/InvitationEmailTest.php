<?php

use App\Enums\WorkspaceRole;
use App\Mail\WorkspaceInvitationMail;
use Illuminate\Support\Facades\Mail;

/*
|--------------------------------------------------------------------------
| What the invitation email actually says
|--------------------------------------------------------------------------
|
| The link lands on a sign-in form, so the message has to say that an account
| is needed and which address it must use. Getting that wrong strands the
| invitee, and nothing else in the suite would notice.
|
*/

beforeEach(function () {
    Mail::fake();
    [$this->workspace, $this->admin] = workspaceWithAdmin();
    $this->admin->update(['name' => 'Ada Okafor']);
});

function sendInvitation(string $email = 'invitee@example.com', WorkspaceRole $role = WorkspaceRole::User): string
{
    test()->actingAs(test()->admin)
        ->postJson('/api/v1/workspaces/'.test()->workspace->id.'/invitations', [
            'email' => $email,
            'role' => $role->value,
        ])
        ->assertCreated();

    $rendered = '';

    Mail::assertSent(WorkspaceInvitationMail::class, function ($mail) use (&$rendered) {
        $rendered = $mail->render();

        return true;
    });

    return $rendered;
}

it('says an account is needed', function () {
    expect(sendInvitation())->toContain("You'll need an account to accept");
});

it('names the address the invitee must use', function () {
    expect(sendInvitation('someone@example.com'))->toContain('someone@example.com');
});

it('warns that a different address will not work', function () {
    expect(sendInvitation())->toContain("won't work");
});

it('names the role the way the interface does, not the way the column does', function () {
    expect(sendInvitation('member@example.com'))->toContain('a member')
        ->and(sendInvitation('boss@example.com', WorkspaceRole::Admin))->toContain('an admin');
});

it('names the workspace and who invited them', function () {
    $body = sendInvitation();

    expect($body)->toContain($this->workspace->name)
        ->and($body)->toContain('Ada Okafor');
});

it('carries a working accept link and never the stored hash', function () {
    $body = sendInvitation();

    expect($body)->toMatch('~/invitations/[0-9a-f]{64}~')
        ->and($body)->not->toContain('token_hash');
});

it('tells them when it expires', function () {
    $this->freezeTime();

    expect(sendInvitation())->toContain(now()->addDays(7)->toFormattedDayDateString());
});

it('goes to the invited address', function () {
    sendInvitation('invitee@example.com');

    Mail::assertSent(
        WorkspaceInvitationMail::class,
        fn ($mail) => $mail->hasTo('invitee@example.com')
    );
});
