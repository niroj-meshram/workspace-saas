<?php

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;

it('lets an admin rename a project', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    $project = Project::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Launch']);

    $this->actingAs($admin)
        ->patchJson("/api/v1/workspaces/{$workspace->id}/projects/{$project->id}", [
            'name' => 'Relaunch',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Relaunch');

    expect($project->fresh()->name)->toBe('Relaunch');
});

it('lets an admin change the description', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    $project = Project::factory()->create(['workspace_id' => $workspace->id, 'description' => 'old']);

    $this->actingAs($admin)
        ->patchJson("/api/v1/workspaces/{$workspace->id}/projects/{$project->id}", [
            'description' => 'new',
        ])
        ->assertOk()
        ->assertJsonPath('data.description', 'new');

    expect($project->fresh()->description)->toBe('new');
});

it('lets an admin clear the description', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    $project = Project::factory()->create(['workspace_id' => $workspace->id, 'description' => 'old']);

    $this->actingAs($admin)
        ->patchJson("/api/v1/workspaces/{$workspace->id}/projects/{$project->id}", [
            'description' => null,
        ])
        ->assertOk()
        ->assertJsonPath('data.description', null);
});

it('leaves untouched fields alone', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    $project = Project::factory()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Launch',
        'description' => 'keep me',
    ]);

    $this->actingAs($admin)
        ->patchJson("/api/v1/workspaces/{$workspace->id}/projects/{$project->id}", [
            'name' => 'Relaunch',
        ])
        ->assertOk();

    expect($project->fresh()->description)->toBe('keep me');
});

it('forbids a non-admin member from updating a project', function () {
    [$workspace] = workspaceWithAdmin();
    $member = User::factory()->create();
    memberOf($workspace, $member);
    $project = Project::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Launch']);

    $this->actingAs($member)
        ->patchJson("/api/v1/workspaces/{$workspace->id}/projects/{$project->id}", [
            'name' => 'Relaunch',
        ])
        ->assertForbidden();

    expect($project->fresh()->name)->toBe('Launch');
});

it('rejects renaming to a name already used in the workspace', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    Project::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Taken']);
    $project = Project::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Launch']);

    $this->actingAs($admin)
        ->patchJson("/api/v1/workspaces/{$workspace->id}/projects/{$project->id}", [
            'name' => 'Taken',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');

    expect($project->fresh()->name)->toBe('Launch');
});

it('allows saving a project under its own unchanged name', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    $project = Project::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Launch']);

    $this->actingAs($admin)
        ->patchJson("/api/v1/workspaces/{$workspace->id}/projects/{$project->id}", [
            'name' => 'Launch',
            'description' => 'updated',
        ])
        ->assertOk();

    expect($project->fresh()->description)->toBe('updated');
});

it('allows a name that is only taken in another workspace', function () {
    [$workspaceA, $adminA] = workspaceWithAdmin();
    [$workspaceB] = workspaceWithAdmin();
    Project::factory()->create(['workspace_id' => $workspaceB->id, 'name' => 'Shared']);
    $project = Project::factory()->create(['workspace_id' => $workspaceA->id, 'name' => 'Launch']);

    $this->actingAs($adminA)
        ->patchJson("/api/v1/workspaces/{$workspaceA->id}/projects/{$project->id}", [
            'name' => 'Shared',
        ])
        ->assertOk();

    expect($project->fresh()->name)->toBe('Shared');
});

it('rejects an unknown status', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    $project = Project::factory()->create(['workspace_id' => $workspace->id]);

    $this->actingAs($admin)
        ->patchJson("/api/v1/workspaces/{$workspace->id}/projects/{$project->id}", [
            'status' => 'deleted',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('status');

    expect($project->fresh()->status)->toBe(ProjectStatus::Active);
});

it('ignores a workspace_id supplied in the payload', function () {
    [$workspaceA, $admin] = workspaceWithAdmin();
    [$workspaceB] = workspaceWithAdmin();
    $project = Project::factory()->create(['workspace_id' => $workspaceA->id]);

    $this->actingAs($admin)
        ->patchJson("/api/v1/workspaces/{$workspaceA->id}/projects/{$project->id}", [
            'name' => 'Renamed',
            'workspace_id' => $workspaceB->id,
        ])
        ->assertOk();

    expect($project->fresh()->workspace_id)->toBe($workspaceA->id);
});

it('requires authentication', function () {
    [$workspace] = workspaceWithAdmin();
    $project = Project::factory()->create(['workspace_id' => $workspace->id]);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/projects/{$project->id}", ['name' => 'x'])
        ->assertUnauthorized();
});
