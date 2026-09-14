<?php

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Cross-tenant access for projects (PROJECT_SPEC.md §17)
|--------------------------------------------------------------------------
|
| Everything asserts 404: an inaccessible tenant resource must look exactly
| like one that does not exist (PROJECT_SPEC.md §14).
|
*/

beforeEach(function () {
    [$this->workspaceA, $this->adminA] = workspaceWithAdmin();
    [$this->workspaceB, $this->adminB] = workspaceWithAdmin();

    $this->projectB = Project::factory()->create([
        'workspace_id' => $this->workspaceB->id,
        'name' => 'Their project',
    ]);
});

it('blocks reading a project in another workspace', function () {
    $this->actingAs($this->adminA)
        ->getJson("/api/v1/workspaces/{$this->workspaceB->id}/projects/{$this->projectB->id}")
        ->assertNotFound();
});

it('blocks listing another workspace\'s projects', function () {
    $this->actingAs($this->adminA)
        ->getJson("/api/v1/workspaces/{$this->workspaceB->id}/projects")
        ->assertNotFound();
});

it('blocks creating a project in another workspace', function () {
    $this->actingAs($this->adminA)
        ->postJson("/api/v1/workspaces/{$this->workspaceB->id}/projects", ['name' => 'Injected'])
        ->assertNotFound();

    expect(Project::where('name', 'Injected')->count())->toBe(0);
});

it('blocks updating a project in another workspace', function () {
    $this->actingAs($this->adminA)
        ->patchJson("/api/v1/workspaces/{$this->workspaceB->id}/projects/{$this->projectB->id}", [
            'name' => 'Hijacked',
        ])
        ->assertNotFound();

    expect($this->projectB->fresh()->name)->toBe('Their project');
});

it('blocks archiving a project in another workspace', function () {
    $this->actingAs($this->adminA)
        ->deleteJson("/api/v1/workspaces/{$this->workspaceB->id}/projects/{$this->projectB->id}")
        ->assertNotFound();

    expect($this->projectB->fresh()->status)->toBe(ProjectStatus::Active);
});

it('blocks addressing another workspace\'s project through your own workspace', function () {
    // Admin of A, using A's id in the route, pointing at B's project.
    $this->actingAs($this->adminA)
        ->getJson("/api/v1/workspaces/{$this->workspaceA->id}/projects/{$this->projectB->id}")
        ->assertNotFound();
});

it('blocks updating another workspace\'s project through your own workspace', function () {
    $this->actingAs($this->adminA)
        ->patchJson("/api/v1/workspaces/{$this->workspaceA->id}/projects/{$this->projectB->id}", [
            'name' => 'Hijacked',
        ])
        ->assertNotFound();

    expect($this->projectB->fresh()->name)->toBe('Their project');
});

it('blocks archiving another workspace\'s project through your own workspace', function () {
    $this->actingAs($this->adminA)
        ->deleteJson("/api/v1/workspaces/{$this->workspaceA->id}/projects/{$this->projectB->id}")
        ->assertNotFound();

    expect($this->projectB->fresh()->status)->toBe(ProjectStatus::Active);
});

it('answers a missing project and an inaccessible one identically', function () {
    $missing = $this->actingAs($this->adminA)
        ->getJson("/api/v1/workspaces/{$this->workspaceA->id}/projects/".Str::uuid()->toString());

    $inaccessible = $this->actingAs($this->adminA)
        ->getJson("/api/v1/workspaces/{$this->workspaceA->id}/projects/{$this->projectB->id}");

    $missing->assertNotFound();
    $inaccessible->assertNotFound();

    expect($missing->json())->toBe($inaccessible->json());
});

it('requires membership rather than a valid workspace id', function () {
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->getJson("/api/v1/workspaces/{$this->workspaceB->id}/projects/{$this->projectB->id}")
        ->assertNotFound();
});

it('keeps project name uniqueness scoped per workspace', function () {
    Project::factory()->create(['workspace_id' => $this->workspaceA->id, 'name' => 'Shared']);

    $this->actingAs($this->adminB)
        ->postJson("/api/v1/workspaces/{$this->workspaceB->id}/projects", ['name' => 'Shared'])
        ->assertCreated();

    expect(Project::where('name', 'Shared')->count())->toBe(2);
});
