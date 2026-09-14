<?php

use App\Enums\ActivityType;
use App\Enums\WorkspaceRole;
use App\Models\Activity;
use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    [$this->workspace, $this->admin] = workspaceWithAdmin();
    $this->member = User::factory()->create();
    memberOf($this->workspace, $this->member);
    $this->project = Project::factory()->create(['workspace_id' => $this->workspace->id]);
});

function feed(mixed $actor, string $query = '')
{
    return test()->actingAs($actor)->getJson(
        '/api/v1/workspaces/'.test()->workspace->id.'/activities'.$query
    );
}

function anActivity(array $attributes = []): Activity
{
    return Activity::factory()->create(array_merge([
        'workspace_id' => test()->workspace->id,
        'user_id' => test()->admin->id,
        'subject_type' => 'project',
        'subject_id' => test()->project->id,
    ], $attributes));
}

it('lets any active member read the feed', function (WorkspaceRole $role) {
    anActivity();
    $viewer = User::factory()->create();
    memberOf($this->workspace, $viewer, $role);

    feed($viewer)->assertOk()->assertJsonCount(1, 'data');
})->with([
    'admin' => [WorkspaceRole::Admin],
    'user' => [WorkspaceRole::User],
]);

it('returns the documented activity shape', function () {
    anActivity([
        'type' => ActivityType::ProjectCreated,
        'metadata' => ['name' => 'Launch'],
    ]);

    $response = feed($this->admin)->assertOk()
        ->assertJsonPath('data.0.type', ActivityType::ProjectCreated->value)
        ->assertJsonPath('data.0.subject_type', 'project')
        ->assertJsonPath('data.0.subject_id', $this->project->id)
        ->assertJsonPath('data.0.metadata.name', 'Launch')
        ->assertJsonPath('data.0.user.id', $this->admin->id);

    expect(array_keys($response->json('data.0')))->toBe([
        'id', 'type', 'subject_type', 'subject_id', 'metadata',
        'user_id', 'user', 'created_at',
    ]);
});

it('reports a null actor for system generated activity', function () {
    anActivity(['user_id' => null]);

    feed($this->admin)->assertOk()
        ->assertJsonPath('data.0.user_id', null)
        ->assertJsonPath('data.0.user', null);
});

it('returns newest first', function () {
    $older = anActivity(['created_at' => now()->subDay()]);
    $newer = anActivity(['created_at' => now()]);

    expect(feed($this->admin)->json('data.*.id'))->toBe([$newer->id, $older->id]);
});

it('orders consistently when activities share a timestamp', function () {
    $at = now();
    $first = anActivity(['created_at' => $at]);
    $second = anActivity(['created_at' => $at]);
    $third = anActivity(['created_at' => $at]);

    expect(feed($this->admin)->json('data.*.id'))->toBe([$third->id, $second->id, $first->id]);
});

it('paginates 20 per page by default', function () {
    foreach (range(1, 25) as $ignored) {
        anActivity();
    }

    feed($this->admin)->assertOk()
        ->assertJsonCount(20, 'data')
        ->assertJsonPath('meta.per_page', 20)
        ->assertJsonPath('meta.total', 25);
});

it('honours per_page and caps it at 100', function () {
    foreach (range(1, 3) as $ignored) {
        anActivity();
    }

    feed($this->admin, '?per_page=2')->assertJsonPath('meta.per_page', 2)->assertJsonCount(2, 'data');
    feed($this->admin, '?per_page=5000')->assertJsonPath('meta.per_page', 100);
});

it('filters by type', function () {
    $wanted = anActivity(['type' => ActivityType::ProjectArchived]);
    anActivity(['type' => ActivityType::ProjectCreated]);

    $response = feed($this->admin, '?type='.ActivityType::ProjectArchived->value)->assertOk();

    expect($response->json('data.*.id'))->toBe([$wanted->id]);
});

it('filters by user', function () {
    $wanted = anActivity(['user_id' => $this->member->id]);
    anActivity(['user_id' => $this->admin->id]);

    $response = feed($this->admin, "?user_id={$this->member->id}")->assertOk();

    expect($response->json('data.*.id'))->toBe([$wanted->id]);
});

it('combines the type and user filters', function () {
    $wanted = anActivity(['type' => ActivityType::ProjectArchived, 'user_id' => $this->member->id]);
    anActivity(['type' => ActivityType::ProjectArchived, 'user_id' => $this->admin->id]);
    anActivity(['type' => ActivityType::ProjectCreated, 'user_id' => $this->member->id]);

    $response = feed($this->admin, '?type='.ActivityType::ProjectArchived->value."&user_id={$this->member->id}")
        ->assertOk();

    expect($response->json('data.*.id'))->toBe([$wanted->id]);
});

it('still finds activity from someone who was removed from the workspace', function () {
    $former = User::factory()->create();
    $membership = memberOf($this->workspace, $former);
    $activity = anActivity(['user_id' => $former->id]);

    $membership->delete();

    expect(feed($this->admin, "?user_id={$former->id}")->json('data.*.id'))->toBe([$activity->id]);
});

it('rejects an unknown filter value', function (string $query, string $field) {
    feed($this->admin, $query)->assertStatus(422)->assertJsonValidationErrors($field);
})->with([
    'unknown type' => ['?type=workspace.exploded', 'type'],
    'user not a uuid' => ['?user_id=nope', 'user_id'],
]);

it('ignores unknown query parameters', function () {
    anActivity();

    feed($this->admin, '?workspace_id=whatever&subject_type=task&colour=red')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('requires authentication', function () {
    $this->getJson("/api/v1/workspaces/{$this->workspace->id}/activities")->assertUnauthorized();
});

it('answers 404 for a non-member', function () {
    anActivity();

    feed(User::factory()->create())->assertNotFound();
});

it('stops serving the feed once the membership is removed', function () {
    $user = User::factory()->create();
    $membership = memberOf($this->workspace, $user);
    anActivity();

    feed($user)->assertOk();

    $membership->delete();

    feed($user)->assertNotFound();
});
