<?php

use App\Enums\ActivityType;
use App\Enums\ProjectStatus;
use App\Models\Activity;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('records an activity when a project is created', function () {
    [$workspace, $admin] = workspaceWithAdmin();

    $id = $this->actingAs($admin)
        ->postJson("/api/v1/workspaces/{$workspace->id}/projects", ['name' => 'Launch'])
        ->assertCreated()
        ->json('data.id');

    $activity = Activity::sole();

    expect($activity->type)->toBe(ActivityType::ProjectCreated)
        ->and($activity->workspace_id)->toBe($workspace->id)
        ->and($activity->user_id)->toBe($admin->id)
        ->and($activity->subject_type)->toBe('project')
        ->and($activity->subject_id)->toBe($id)
        ->and($activity->metadata)->toEqual(['name' => 'Launch']);
});

it('resolves the activity subject back to the project', function () {
    [$workspace, $admin] = workspaceWithAdmin();

    $this->actingAs($admin)
        ->postJson("/api/v1/workspaces/{$workspace->id}/projects", ['name' => 'Launch'])
        ->assertCreated();

    expect(Activity::sole()->subject)->toBeInstanceOf(Project::class)
        ->and(Activity::sole()->subject->name)->toBe('Launch');
});

it('records an activity when a project is archived', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    $project = Project::factory()->create(['workspace_id' => $workspace->id]);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/workspaces/{$workspace->id}/projects/{$project->id}")
        ->assertNoContent();

    $activity = Activity::sole();

    expect($activity->type)->toBe(ActivityType::ProjectArchived)
        ->and($activity->subject_id)->toBe($project->id)
        ->and($activity->user_id)->toBe($admin->id)
        ->and($activity->metadata)->toEqual([
            'old_status' => ProjectStatus::Active->value,
            'new_status' => ProjectStatus::Archived->value,
        ]);
});

it('records the archive whichever endpoint triggers it', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    $project = Project::factory()->create(['workspace_id' => $workspace->id]);

    $this->actingAs($admin)
        ->patchJson("/api/v1/workspaces/{$workspace->id}/projects/{$project->id}", [
            'status' => ProjectStatus::Archived->value,
        ])
        ->assertOk();

    expect(Activity::where('type', ActivityType::ProjectArchived)->count())->toBe(1);
});

it('records nothing for a plain rename', function () {
    [$workspace, $admin] = workspaceWithAdmin();
    $project = Project::factory()->create(['workspace_id' => $workspace->id]);

    $this->actingAs($admin)
        ->patchJson("/api/v1/workspaces/{$workspace->id}/projects/{$project->id}", ['name' => 'Renamed'])
        ->assertOk();

    expect(Activity::count())->toBe(0);
});

it('does not record activity when creation is rejected', function () {
    [$workspace] = workspaceWithAdmin();
    $member = User::factory()->create();
    memberOf($workspace, $member);

    $this->actingAs($member)
        ->postJson("/api/v1/workspaces/{$workspace->id}/projects", ['name' => 'Launch'])
        ->assertForbidden();

    expect(Activity::count())->toBe(0);
});

it('rolls the activity back with the project when the write fails', function () {
    [$workspace, $admin] = workspaceWithAdmin();

    DB::listen(function ($query) {
        if (str_contains($query->sql, 'insert into "activities"')) {
            throw new RuntimeException('activity insert failed');
        }
    });

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($admin)
        ->postJson("/api/v1/workspaces/{$workspace->id}/projects", ['name' => 'Launch']))
        ->toThrow(RuntimeException::class, 'activity insert failed');

    expect(Project::count())->toBe(0)
        ->and(Activity::count())->toBe(0);
});

it('keeps activity out of other workspaces', function () {
    [$workspaceA, $adminA] = workspaceWithAdmin();
    [$workspaceB] = workspaceWithAdmin();

    $this->actingAs($adminA)
        ->postJson("/api/v1/workspaces/{$workspaceA->id}/projects", ['name' => 'Launch'])
        ->assertCreated();

    expect(Activity::where('workspace_id', $workspaceB->id)->count())->toBe(0)
        ->and(Activity::where('workspace_id', $workspaceA->id)->count())->toBe(1);
});
