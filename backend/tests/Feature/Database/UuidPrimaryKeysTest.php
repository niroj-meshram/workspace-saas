<?php

use App\Models\Activity;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

dataset('models', [
    'user' => [User::class],
    'workspace' => [Workspace::class],
    'workspace member' => [WorkspaceMember::class],
    'project' => [Project::class],
    'task' => [Task::class],
    'invitation' => [WorkspaceInvitation::class],
    'activity' => [Activity::class],
]);

it('generates a version 4 uuid primary key on create', function (string $model) {
    $record = $model::factory()->create();

    expect($record->getKey())->toBeString()
        ->and(Uuid::isValid($record->getKey()))->toBeTrue()
        ->and(Uuid::fromString($record->getKey())->getVersion())->toBe(4);
})->with('models');

it('does not use auto-incrementing integer keys', function (string $model) {
    $instance = new $model;

    expect($instance->getIncrementing())->toBeFalse()
        ->and($instance->getKeyType())->toBe('string');
})->with('models');

it('generates distinct keys for each record', function () {
    $ids = Workspace::factory()->count(5)->create()->pluck('id');

    expect($ids->unique())->toHaveCount(5);
});

it('stores ids in a native uuid column on postgresql', function () {
    $types = DB::table('information_schema.columns')
        ->where('table_schema', 'public')
        ->where('column_name', 'id')
        ->whereIn('table_name', [
            'users', 'workspaces', 'workspace_members',
            'projects', 'tasks', 'workspace_invitations', 'activities',
        ])
        ->pluck('data_type', 'table_name');

    expect($types)->toHaveCount(7)
        ->and($types->unique()->values()->all())->toBe(['uuid']);
})->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'PostgreSQL only.');

it('stores activity metadata in a jsonb column on postgresql', function () {
    $type = DB::table('information_schema.columns')
        ->where('table_schema', 'public')
        ->where('table_name', 'activities')
        ->where('column_name', 'metadata')
        ->value('data_type');

    expect($type)->toBe('jsonb');
})->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'PostgreSQL only.');
