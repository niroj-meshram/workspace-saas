<?php

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

beforeEach(function () {
    [$this->workspace, $this->admin] = workspaceWithAdmin();
    $this->member = User::factory()->create();
    memberOf($this->workspace, $this->member);
    $this->project = Project::factory()->create(['workspace_id' => $this->workspace->id]);
});

function listTasks(mixed $actor, string $query = '')
{
    return test()->actingAs($actor)->getJson(
        '/api/v1/workspaces/'.test()->workspace->id.'/tasks'.$query
    );
}

it('lets a member list tasks', function () {
    Task::factory()->count(3)->forProject($this->project)->create();

    listTasks($this->member)->assertOk()->assertJsonCount(3, 'data');
});

it('lists only tasks of the workspace in the route', function () {
    [$other] = workspaceWithAdmin();
    $otherProject = Project::factory()->create(['workspace_id' => $other->id]);

    $mine = Task::factory()->forProject($this->project)->create();
    Task::factory()->forProject($otherProject)->create();

    $response = listTasks($this->member)->assertOk();

    expect($response->json('data.*.id'))->toBe([$mine->id]);
});

it('filters by project', function () {
    $otherProject = Project::factory()->create(['workspace_id' => $this->workspace->id]);
    $wanted = Task::factory()->forProject($this->project)->create();
    Task::factory()->forProject($otherProject)->create();

    $response = listTasks($this->member, "?project_id={$this->project->id}")->assertOk();

    expect($response->json('data.*.id'))->toBe([$wanted->id]);
});

it('filters by status', function () {
    $done = Task::factory()->forProject($this->project)->create(['status' => TaskStatus::Done]);
    Task::factory()->forProject($this->project)->create(['status' => TaskStatus::Todo]);

    $response = listTasks($this->member, '?status=done')->assertOk();

    expect($response->json('data.*.id'))->toBe([$done->id]);
});

it('filters by priority', function () {
    $high = Task::factory()->forProject($this->project)->create(['priority' => TaskPriority::High]);
    Task::factory()->forProject($this->project)->create(['priority' => TaskPriority::Low]);

    $response = listTasks($this->member, '?priority=high')->assertOk();

    expect($response->json('data.*.id'))->toBe([$high->id]);
});

it('filters by assignee', function () {
    $mine = Task::factory()->forProject($this->project)->create(['assignee_id' => $this->member->id]);
    Task::factory()->forProject($this->project)->create();

    $response = listTasks($this->member, "?assignee_id={$this->member->id}")->assertOk();

    expect($response->json('data.*.id'))->toBe([$mine->id]);
});

it('combines filters', function () {
    $wanted = Task::factory()->forProject($this->project)->create([
        'status' => TaskStatus::Blocked,
        'priority' => TaskPriority::High,
    ]);
    Task::factory()->forProject($this->project)->create([
        'status' => TaskStatus::Blocked,
        'priority' => TaskPriority::Low,
    ]);

    $response = listTasks($this->member, '?status=blocked&priority=high')->assertOk();

    expect($response->json('data.*.id'))->toBe([$wanted->id]);
});

it('searches the title and the description', function () {
    $byTitle = Task::factory()->forProject($this->project)->create(['title' => 'Deploy the API']);
    $byDescription = Task::factory()->forProject($this->project)->create([
        'title' => 'Unrelated',
        'description' => 'Blocked on the API gateway',
    ]);
    Task::factory()->forProject($this->project)->create(['title' => 'Write docs', 'description' => null]);

    $response = listTasks($this->member, '?search=api')->assertOk();

    expect($response->json('data.*.id'))
        ->toHaveCount(2)
        ->toContain($byTitle->id, $byDescription->id);
});

it('searches case-insensitively', function () {
    $task = Task::factory()->forProject($this->project)->create(['title' => 'Deploy the API']);

    $response = listTasks($this->member, '?search=DEPLOY')->assertOk();

    expect($response->json('data.*.id'))->toBe([$task->id]);
});

it('treats like wildcards in the search term literally', function () {
    Task::factory()->forProject($this->project)->create(['title' => 'Cut costs']);
    $literal = Task::factory()->forProject($this->project)->create(['title' => 'Reduce by 50% overall']);

    $response = listTasks($this->member, '?search='.urlencode('50%'))->assertOk();

    expect($response->json('data.*.id'))->toBe([$literal->id]);
});

it('rejects a project filter from another workspace', function () {
    [$other] = workspaceWithAdmin();
    $foreign = Project::factory()->create(['workspace_id' => $other->id]);

    listTasks($this->member, "?project_id={$foreign->id}")
        ->assertStatus(422)
        ->assertJsonValidationErrors('project_id');
});

it('rejects an unknown filter value', function (string $query, string $field) {
    listTasks($this->member, $query)->assertStatus(422)->assertJsonValidationErrors($field);
})->with([
    'bad status' => ['?status=cancelled', 'status'],
    'bad priority' => ['?priority=urgent', 'priority'],
    'assignee not a uuid' => ['?assignee_id=nope', 'assignee_id'],
]);

it('ignores unknown query parameters', function () {
    Task::factory()->count(2)->forProject($this->project)->create();

    listTasks($this->member, '?workspace_id=whatever&deleted_at=null&colour=red')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('sorts by a whitelisted column ascending and descending', function () {
    $a = Task::factory()->forProject($this->project)->create(['title' => 'Aardvark']);
    $z = Task::factory()->forProject($this->project)->create(['title' => 'Zebra']);

    expect(listTasks($this->member, '?sort=title')->json('data.*.id'))->toBe([$a->id, $z->id]);
    expect(listTasks($this->member, '?sort=-title')->json('data.*.id'))->toBe([$z->id, $a->id]);
});

it('sorts by due date', function () {
    $later = Task::factory()->forProject($this->project)->create(['due_date' => '2026-12-31']);
    $sooner = Task::factory()->forProject($this->project)->create(['due_date' => '2026-01-01']);

    expect(listTasks($this->member, '?sort=due_date')->json('data.*.id'))
        ->toBe([$sooner->id, $later->id]);
});

it('rejects sorting by a column that is not whitelisted', function (string $sort) {
    listTasks($this->member, "?sort={$sort}")
        ->assertStatus(422)
        ->assertJsonValidationErrors('sort');
})->with([
    'workspace_id' => ['workspace_id'],
    'id' => ['id'],
    'status' => ['status'],
    'sql fragment' => ['title; drop table tasks'],
    'unknown column' => ['nonsense'],
]);

it('defaults to newest first', function () {
    $older = Task::factory()->forProject($this->project)->create(['created_at' => now()->subDay()]);
    $newer = Task::factory()->forProject($this->project)->create(['created_at' => now()]);

    expect(listTasks($this->member)->json('data.*.id'))->toBe([$newer->id, $older->id]);
});

it('orders consistently when the sort column ties', function () {
    // created_at is stored at second precision, so these tie on it and the
    // id tiebreaker decides.
    $at = now();
    $first = Task::factory()->forProject($this->project)->create(['created_at' => $at]);
    $second = Task::factory()->forProject($this->project)->create(['created_at' => $at]);
    $third = Task::factory()->forProject($this->project)->create(['created_at' => $at]);

    expect(listTasks($this->member)->json('data.*.id'))
        ->toBe([$third->id, $second->id, $first->id]);

    expect(listTasks($this->member, '?sort=created_at')->json('data.*.id'))
        ->toBe([$first->id, $second->id, $third->id]);
});

it('paginates 20 per page by default', function () {
    Task::factory()->count(25)->forProject($this->project)->create();

    listTasks($this->member)
        ->assertOk()
        ->assertJsonCount(20, 'data')
        ->assertJsonPath('meta.per_page', 20)
        ->assertJsonPath('meta.total', 25);
});

it('honours per_page and caps it at 100', function () {
    Task::factory()->count(3)->forProject($this->project)->create();

    listTasks($this->member, '?per_page=2')->assertJsonPath('meta.per_page', 2);
    listTasks($this->member, '?per_page=5000')->assertJsonPath('meta.per_page', 100);
});

it('excludes soft deleted tasks', function () {
    $kept = Task::factory()->forProject($this->project)->create();
    $deleted = Task::factory()->forProject($this->project)->create();

    $deleted->delete();

    $response = listTasks($this->member)->assertOk()->assertJsonCount(1, 'data');

    expect($response->json('data.*.id'))->toBe([$kept->id]);
});

it('lets a member read a single task', function () {
    $task = Task::factory()->forProject($this->project)->create();

    $this->actingAs($this->member)
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/tasks/{$task->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $task->id)
        ->assertJsonPath('data.project.id', $this->project->id);
});

it('answers 404 for a non-member', function () {
    Task::factory()->forProject($this->project)->create();

    listTasks(User::factory()->create())->assertNotFound();
});

it('requires authentication', function () {
    $this->getJson("/api/v1/workspaces/{$this->workspace->id}/tasks")->assertUnauthorized();
});
