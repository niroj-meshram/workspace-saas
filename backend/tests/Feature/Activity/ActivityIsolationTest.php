<?php

use App\Models\Activity;
use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    [$this->workspaceA, $this->adminA] = workspaceWithAdmin();
    [$this->workspaceB, $this->adminB] = workspaceWithAdmin();

    $this->projectB = Project::factory()->create(['workspace_id' => $this->workspaceB->id]);
    $this->activityB = Activity::factory()->create([
        'workspace_id' => $this->workspaceB->id,
        'user_id' => $this->adminB->id,
        'subject_type' => 'project',
        'subject_id' => $this->projectB->id,
    ]);
});

it('blocks reading another workspace\'s feed', function () {
    $this->actingAs($this->adminA)
        ->getJson("/api/v1/workspaces/{$this->workspaceB->id}/activities")
        ->assertNotFound();
});

it('never leaks another workspace\'s activity into your own feed', function () {
    $projectA = Project::factory()->create(['workspace_id' => $this->workspaceA->id]);
    $mine = Activity::factory()->create([
        'workspace_id' => $this->workspaceA->id,
        'user_id' => $this->adminA->id,
        'subject_type' => 'project',
        'subject_id' => $projectA->id,
    ]);

    $response = $this->actingAs($this->adminA)
        ->getJson("/api/v1/workspaces/{$this->workspaceA->id}/activities")
        ->assertOk();

    expect($response->json('data.*.id'))->toBe([$mine->id])
        ->not->toContain($this->activityB->id);
});

it('cannot be filtered across tenants by user', function () {
    // adminB's id is a valid uuid, but their activity lives in workspace B.
    $response = $this->actingAs($this->adminA)
        ->getJson("/api/v1/workspaces/{$this->workspaceA->id}/activities?user_id={$this->adminB->id}")
        ->assertOk();

    expect($response->json('data'))->toBe([]);
});

it('records business activity only in the workspace it happened in', function () {
    $this->actingAs($this->adminA)
        ->postJson("/api/v1/workspaces/{$this->workspaceA->id}/projects", ['name' => 'Launch'])
        ->assertCreated();

    expect(Activity::where('workspace_id', $this->workspaceA->id)->count())->toBe(1)
        ->and(Activity::where('workspace_id', $this->workspaceB->id)->count())->toBe(1);

    $this->actingAs($this->adminB)
        ->getJson("/api/v1/workspaces/{$this->workspaceB->id}/activities")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->activityB->id);
});

it('answers a stranger the same as a missing workspace', function () {
    $stranger = User::factory()->create();

    $inaccessible = $this->actingAs($stranger)
        ->getJson("/api/v1/workspaces/{$this->workspaceB->id}/activities");

    $missing = $this->actingAs($stranger)
        ->getJson('/api/v1/workspaces/'.Str::uuid()->toString().'/activities');

    $inaccessible->assertNotFound();
    $missing->assertNotFound();

    expect($inaccessible->json())->toBe($missing->json());
});
