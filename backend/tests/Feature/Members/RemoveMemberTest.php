<?php

use App\Enums\ActivityType;
use App\Enums\WorkspaceRole;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\DB;

it('lets an admin remove a member', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    $member = memberOf($workspace, User::factory()->create());

    $this->actingAs($admin)
        ->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$member->id}")
        ->assertNoContent();

    expect($member->fresh()->trashed())->toBeTrue();
});

it('soft deletes rather than erasing the membership', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    $member = memberOf($workspace, User::factory()->create());

    $this->actingAs($admin)
        ->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$member->id}")
        ->assertNoContent();

    expect(WorkspaceMember::withTrashed()->find($member->id))->not->toBeNull()
        ->and(WorkspaceMember::find($member->id))->toBeNull();
});

it('revokes the removed member\'s access to the workspace', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    $user = User::factory()->create();
    $member = memberOf($workspace, $user);

    $this->actingAs($user)->getJson("/api/v1/workspaces/{$workspace->id}")->assertOk();

    $this->actingAs($admin)
        ->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$member->id}")
        ->assertNoContent();

    $this->actingAs($user)->getJson("/api/v1/workspaces/{$workspace->id}")->assertNotFound();
    $this->actingAs($user)->getJson('/api/v1/workspaces')->assertJsonPath('data', []);
});

it('forbids a non-admin member from removing members', function () {
    [$workspace, $admin, $adminMember] = workspaceWithAdmin();
    $actor = User::factory()->create();
    memberOf($workspace, $actor);

    $this->actingAs($actor)
        ->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$adminMember->id}")
        ->assertForbidden();

    expect($adminMember->fresh()->trashed())->toBeFalse();
});

it('forbids a non-admin member from removing themselves', function () {
    [$workspace] = workspaceWithAdmin();
    $actor = User::factory()->create();
    $self = memberOf($workspace, $actor);

    $this->actingAs($actor)
        ->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$self->id}")
        ->assertForbidden();

    expect($self->fresh()->trashed())->toBeFalse();
});

it('refuses to remove the last admin', function () {
    [$workspace, $admin, $adminMember] = workspaceWithAdmin();
    memberOf($workspace, User::factory()->create());

    $this->actingAs($admin)
        ->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$adminMember->id}")
        ->assertStatus(422)
        ->assertJsonValidationErrors('member')
        ->assertJsonPath('errors.member.0', 'A workspace must always have at least one admin.');

    expect($adminMember->fresh()->trashed())->toBeFalse();
});

it('lets an admin remove another admin while one remains', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    $other = memberOf($workspace, User::factory()->create(), WorkspaceRole::Admin);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$other->id}")
        ->assertNoContent();

    expect($other->fresh()->trashed())->toBeTrue();
});

it('lets an admin leave when another admin remains', function () {
    [$workspace, $admin, $adminMember] = workspaceWithAdmin();
    memberOf($workspace, User::factory()->create(), WorkspaceRole::Admin);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$adminMember->id}")
        ->assertNoContent();

    expect($adminMember->fresh()->trashed())->toBeTrue();
});

it('refuses to remove the last admin when the only other admin was already removed', function () {
    [$workspace, $admin, $adminMember] = workspaceWithAdmin();
    memberOf($workspace, User::factory()->create(), WorkspaceRole::Admin)->delete();

    $this->actingAs($admin)
        ->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$adminMember->id}")
        ->assertStatus(422);

    expect($adminMember->fresh()->trashed())->toBeFalse();
});

it('answers 404 for a membership that was already removed', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    $member = memberOf($workspace, User::factory()->create());
    $member->delete();

    $this->actingAs($admin)
        ->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$member->id}")
        ->assertNotFound();
});

it('lets a user be re-added after removal', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    $user = User::factory()->create();
    $member = memberOf($workspace, $user);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$member->id}")
        ->assertNoContent();

    // The partial unique index only covers active rows (PROJECT_SPEC.md §8).
    $readded = memberOf($workspace, $user, WorkspaceRole::Admin);

    expect($readded->exists)->toBeTrue();

    $this->actingAs($user)->getJson("/api/v1/workspaces/{$workspace->id}")->assertOk();
});

it('requires authentication', function () {
    [$workspace, $admin, $adminMember] = workspaceWithAdmin();

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$adminMember->id}")
        ->assertUnauthorized();
});

/*
|--------------------------------------------------------------------------
| Unassigning the removed member's tasks (PROJECT_SPEC.md §8, §18)
|--------------------------------------------------------------------------
*/

it('unassigns every task the removed member held in that workspace', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    $user = User::factory()->create();
    $member = memberOf($workspace, $user);

    $project = Project::factory()->create(['workspace_id' => $workspace->id]);
    $theirs = Task::factory()->count(3)->forProject($project)->create(['assignee_id' => $user->id]);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$member->id}")
        ->assertNoContent();

    foreach ($theirs as $task) {
        expect($task->fresh()->assignee_id)->toBeNull();
    }
});

it('leaves other people\'s tasks assigned', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    $leaver = User::factory()->create();
    $stayer = User::factory()->create();
    $member = memberOf($workspace, $leaver);
    memberOf($workspace, $stayer);

    $project = Project::factory()->create(['workspace_id' => $workspace->id]);
    $theirs = Task::factory()->forProject($project)->create(['assignee_id' => $leaver->id]);
    $someoneElses = Task::factory()->forProject($project)->create(['assignee_id' => $stayer->id]);
    $adminsOwn = Task::factory()->forProject($project)->create(['assignee_id' => $admin->id]);
    $unassigned = Task::factory()->forProject($project)->create();

    $this->actingAs($admin)
        ->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$member->id}")
        ->assertNoContent();

    expect($theirs->fresh()->assignee_id)->toBeNull()
        ->and($someoneElses->fresh()->assignee_id)->toBe($stayer->id)
        ->and($adminsOwn->fresh()->assignee_id)->toBe($admin->id)
        ->and($unassigned->fresh()->assignee_id)->toBeNull();
});

it('does not touch the same person\'s tasks in another workspace', function () {
    // Someone can belong to several workspaces; leaving one says nothing about
    // the work they still hold in the others.
    [$workspaceA, $adminA] = workspaceWithAdmin();
    [$workspaceB] = workspaceWithAdmin();

    $user = User::factory()->create();
    $membershipA = memberOf($workspaceA, $user);
    memberOf($workspaceB, $user);

    $inA = Task::factory()
        ->forProject(Project::factory()->create(['workspace_id' => $workspaceA->id]))
        ->create(['assignee_id' => $user->id]);

    $inB = Task::factory()
        ->forProject(Project::factory()->create(['workspace_id' => $workspaceB->id]))
        ->create(['assignee_id' => $user->id]);

    $this->actingAs($adminA)
        ->deleteJson("/api/v1/workspaces/{$workspaceA->id}/members/{$membershipA->id}")
        ->assertNoContent();

    expect($inA->fresh()->assignee_id)->toBeNull()
        ->and($inB->fresh()->assignee_id)->toBe($user->id);
});

it('keeps their membership of other workspaces intact', function () {
    [$workspaceA, $adminA] = workspaceWithAdmin();
    [$workspaceB] = workspaceWithAdmin();

    $user = User::factory()->create();
    $membershipA = memberOf($workspaceA, $user);
    $membershipB = memberOf($workspaceB, $user);

    $this->actingAs($adminA)
        ->deleteJson("/api/v1/workspaces/{$workspaceA->id}/members/{$membershipA->id}")
        ->assertNoContent();

    expect($membershipA->fresh()->trashed())->toBeTrue()
        ->and($membershipB->fresh()->trashed())->toBeFalse();

    $this->actingAs($user)->getJson("/api/v1/workspaces/{$workspaceB->id}")->assertOk();
    $this->actingAs($user)->getJson("/api/v1/workspaces/{$workspaceA->id}")->assertNotFound();
});

it('leaves already deleted tasks alone', function () {
    // They are out of every listing already; rewriting them would edit history
    // rather than the present.
    [$workspace, $admin] = workspaceWithAdmin();
    $user = User::factory()->create();
    $member = memberOf($workspace, $user);

    $project = Project::factory()->create(['workspace_id' => $workspace->id]);
    $deleted = Task::factory()->forProject($project)->create(['assignee_id' => $user->id]);
    $deleted->delete();

    $this->actingAs($admin)
        ->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$member->id}")
        ->assertNoContent();

    expect(Task::withTrashed()->find($deleted->id)->assignee_id)->toBe($user->id);
});

it('deletes no task and no activity', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    $user = User::factory()->create();
    $member = memberOf($workspace, $user);

    $project = Project::factory()->create(['workspace_id' => $workspace->id]);
    $task = Task::factory()->forProject($project)->create(['assignee_id' => $user->id]);

    $history = Activity::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'type' => ActivityType::TaskAssigned,
        'subject_type' => 'task',
        'subject_id' => $task->id,
        'metadata' => ['old_assignee_id' => null, 'new_assignee_id' => $user->id],
    ]);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$member->id}")
        ->assertNoContent();

    expect(Task::find($task->id))->not->toBeNull()
        ->and($task->fresh()->trashed())->toBeFalse()
        ->and(Activity::find($history->id))->not->toBeNull()
        ->and($history->fresh()->metadata)->toEqual([
            'old_assignee_id' => null,
            'new_assignee_id' => $user->id,
        ]);
});

it('records member.removed alongside the unassignment', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    $user = User::factory()->create();
    $member = memberOf($workspace, $user);

    Task::factory()
        ->forProject(Project::factory()->create(['workspace_id' => $workspace->id]))
        ->create(['assignee_id' => $user->id]);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$member->id}")
        ->assertNoContent();

    $activity = Activity::where('type', ActivityType::MemberRemoved)->sole();

    expect($activity->workspace_id)->toBe($workspace->id)
        ->and($activity->user_id)->toBe($admin->id)
        ->and($activity->subject_id)->toBe($member->id)
        ->and($activity->metadata)->toEqual([
            'user_id' => $user->id,
            'role' => WorkspaceRole::User->value,
        ]);
});

it('rolls the unassignment back when the removal fails', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    $user = User::factory()->create();
    $member = memberOf($workspace, $user);

    $task = Task::factory()
        ->forProject(Project::factory()->create(['workspace_id' => $workspace->id]))
        ->create(['assignee_id' => $user->id]);

    // Fail the membership delete, which runs after the tasks are unassigned.
    DB::listen(function ($query) {
        if (str_contains($query->sql, 'update "workspace_members"')) {
            throw new RuntimeException('membership delete failed');
        }
    });

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($admin)
        ->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$member->id}"))
        ->toThrow(RuntimeException::class, 'membership delete failed');

    // Neither half committed.
    expect($task->fresh()->assignee_id)->toBe($user->id)
        ->and($member->fresh()->trashed())->toBeFalse()
        ->and(Activity::count())->toBe(0);
});

it('leaves everything alone when the last admin cannot be removed', function () {
    [$workspace, $admin, $adminMember] = workspaceWithAdmin();
    memberOf($workspace, User::factory()->create());

    $task = Task::factory()
        ->forProject(Project::factory()->create(['workspace_id' => $workspace->id]))
        ->create(['assignee_id' => $admin->id]);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$adminMember->id}")
        ->assertStatus(422);

    expect($task->fresh()->assignee_id)->toBe($admin->id)
        ->and($adminMember->fresh()->trashed())->toBeFalse();
});

it('cannot be used to unassign another workspace\'s tasks', function () {
    // An admin of A aiming at B's membership: the route never resolves it.
    [$workspaceA, $adminA] = workspaceWithAdmin();
    [$workspaceB, $adminB, $memberB] = workspaceWithAdmin();

    $task = Task::factory()
        ->forProject(Project::factory()->create(['workspace_id' => $workspaceB->id]))
        ->create(['assignee_id' => $adminB->id]);

    $this->actingAs($adminA)
        ->deleteJson("/api/v1/workspaces/{$workspaceA->id}/members/{$memberB->id}")
        ->assertNotFound();

    $this->actingAs($adminA)
        ->deleteJson("/api/v1/workspaces/{$workspaceB->id}/members/{$memberB->id}")
        ->assertNotFound();

    expect($task->fresh()->assignee_id)->toBe($adminB->id)
        ->and($memberB->fresh()->trashed())->toBeFalse();
});
