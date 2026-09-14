<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\DB;

it('lets an authenticated user create a workspace', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/workspaces', [
        'name' => 'Acme',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Acme')
        ->assertJsonStructure(['data' => ['id', 'name', 'created_at', 'updated_at']]);

    expect(Workspace::where('name', 'Acme')->exists())->toBeTrue();
});

it('makes the creator an admin', function () {
    $user = User::factory()->create();

    $id = $this->actingAs($user)
        ->postJson('/api/v1/workspaces', ['name' => 'Acme'])
        ->assertCreated()
        ->json('data.id');

    $member = WorkspaceMember::where('workspace_id', $id)->sole();

    expect($member->user_id)->toBe($user->id)
        ->and($member->role)->toBe(WorkspaceRole::Admin);
});

it('requires authentication', function () {
    $this->postJson('/api/v1/workspaces', ['name' => 'Acme'])->assertUnauthorized();

    expect(Workspace::count())->toBe(0);
});

it('validates the workspace name', function (array $payload) {
    $this->actingAs(User::factory()->create())
        ->postJson('/api/v1/workspaces', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
})->with([
    'missing' => [[]],
    'empty' => [['name' => '']],
    'not a string' => [['name' => ['x']]],
    'too long' => [fn () => ['name' => str_repeat('a', 256)]],
]);

it('ignores a user_id supplied in the payload', function () {
    $user = User::factory()->create();
    $victim = User::factory()->create();

    $id = $this->actingAs($user)
        ->postJson('/api/v1/workspaces', [
            'name' => 'Acme',
            'user_id' => $victim->id,
        ])
        ->assertCreated()
        ->json('data.id');

    expect(WorkspaceMember::where('workspace_id', $id)->sole()->user_id)->toBe($user->id);
});

it('rolls back the workspace if the membership cannot be written', function () {
    $user = User::factory()->create();

    // Force the second insert of the transaction to fail.
    DB::listen(function ($query) {
        if (str_contains($query->sql, 'insert into "workspace_members"')) {
            throw new RuntimeException('membership insert failed');
        }
    });

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($user)->postJson('/api/v1/workspaces', ['name' => 'Acme']))
        ->toThrow(RuntimeException::class, 'membership insert failed');

    expect(Workspace::count())->toBe(0)
        ->and(WorkspaceMember::count())->toBe(0);
});
