<?php

use App\Enums\ActivityType;
use App\Enums\ProjectStatus;
use App\Models\Activity;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('lets an admin archive a project through PATCH', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    $project = Project::factory()->create(['workspace_id' => $workspace->id]);

    $this->actingAs($admin)
        ->patchJson("/api/v1/workspaces/{$workspace->id}/projects/{$project->id}", [
            'status' => ProjectStatus::Archived->value,
        ])
        ->assertOk()
        ->assertJsonPath('data.status', ProjectStatus::Archived->value);

    expect($project->fresh()->status)->toBe(ProjectStatus::Archived);
});

it('archives rather than deletes on DELETE', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    $project = Project::factory()->create(['workspace_id' => $workspace->id]);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/workspaces/{$workspace->id}/projects/{$project->id}")
        ->assertNoContent();

    // The row is still there: projects have no soft delete, they archive
    // (PROJECT_SPEC.md §11).
    expect(Project::find($project->id))->not->toBeNull()
        ->and($project->fresh()->status)->toBe(ProjectStatus::Archived)
        ->and(DB::table('projects')->where('id', $project->id)->exists())->toBeTrue();
});

it('keeps an archived project listed and readable', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    $project = Project::factory()->create(['workspace_id' => $workspace->id]);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/workspaces/{$workspace->id}/projects/{$project->id}")
        ->assertNoContent();

    $this->actingAs($admin)
        ->getJson("/api/v1/workspaces/{$workspace->id}/projects/{$project->id}")
        ->assertOk()
        ->assertJsonPath('data.status', ProjectStatus::Archived->value);

    $this->actingAs($admin)
        ->getJson("/api/v1/workspaces/{$workspace->id}/projects")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('forbids a non-admin member from archiving a project', function () {
    [$workspace] = workspaceWithAdmin();
    $member = User::factory()->create();
    memberOf($workspace, $member);
    $project = Project::factory()->create(['workspace_id' => $workspace->id]);

    $this->actingAs($member)
        ->patchJson("/api/v1/workspaces/{$workspace->id}/projects/{$project->id}", [
            'status' => ProjectStatus::Archived->value,
        ])
        ->assertForbidden();

    expect($project->fresh()->status)->toBe(ProjectStatus::Active);
});

it('forbids a non-admin member from deleting a project', function () {
    [$workspace] = workspaceWithAdmin();
    $member = User::factory()->create();
    memberOf($workspace, $member);
    $project = Project::factory()->create(['workspace_id' => $workspace->id]);

    $this->actingAs($member)
        ->deleteJson("/api/v1/workspaces/{$workspace->id}/projects/{$project->id}")
        ->assertForbidden();

    expect($project->fresh()->status)->toBe(ProjectStatus::Active);
});

it('is idempotent and records the archive only once', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    $project = Project::factory()->create(['workspace_id' => $workspace->id]);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/workspaces/{$workspace->id}/projects/{$project->id}")
        ->assertNoContent();

    $this->actingAs($admin)
        ->deleteJson("/api/v1/workspaces/{$workspace->id}/projects/{$project->id}")
        ->assertNoContent();

    expect(Activity::where('type', ActivityType::ProjectArchived)->count())->toBe(1);
});

it('lets an admin restore an archived project', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    $project = Project::factory()->archived()->create(['workspace_id' => $workspace->id]);

    $this->actingAs($admin)
        ->patchJson("/api/v1/workspaces/{$workspace->id}/projects/{$project->id}", [
            'status' => ProjectStatus::Active->value,
        ])
        ->assertOk()
        ->assertJsonPath('data.status', ProjectStatus::Active->value);

    expect($project->fresh()->status)->toBe(ProjectStatus::Active);
});

it('applies a rename and an archive from the same request', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    $project = Project::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Launch']);

    $this->actingAs($admin)
        ->patchJson("/api/v1/workspaces/{$workspace->id}/projects/{$project->id}", [
            'name' => 'Relaunch',
            'status' => ProjectStatus::Archived->value,
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Relaunch')
        ->assertJsonPath('data.status', ProjectStatus::Archived->value);

    $project->refresh();

    expect($project->name)->toBe('Relaunch')
        ->and($project->status)->toBe(ProjectStatus::Archived);
});

it('requires authentication', function () {
    [$workspace] = workspaceWithAdmin();
    $project = Project::factory()->create(['workspace_id' => $workspace->id]);

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/projects/{$project->id}")
        ->assertUnauthorized();
});
