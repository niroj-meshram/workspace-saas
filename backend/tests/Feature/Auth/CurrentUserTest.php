<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('returns 401 when unauthenticated', function () {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('returns 401 for a browser-style request without an accept header', function () {
    // The exception handler is configured to answer api/* with JSON, so this
    // must not redirect to a non-existent login route.
    $this->get('/api/v1/auth/me')->assertUnauthorized();
});

it('returns the authenticated user', function () {
    $user = User::factory()->create([
        'name' => 'Niraj',
        'email' => 'niraj@example.com',
    ]);

    $this->actingAs($user)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.name', 'Niraj')
        ->assertJsonPath('data.email', 'niraj@example.com');
});

it('wraps the user in a data key', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonStructure(['data' => ['id', 'name', 'email', 'created_at', 'updated_at']]);
});

it('never exposes sensitive fields', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/auth/me')->assertOk();

    expect(array_keys($response->json('data')))
        ->toBe(['id', 'name', 'email', 'created_at', 'updated_at']);

    expect($response->getContent())
        ->not->toContain($user->password)
        ->not->toContain('password')
        ->not->toContain('remember_token');
});

it('returns 401 once the user is soft deleted', function () {
    // A real login, not actingAs: the session guard re-resolves the user from
    // the provider on every request, and the provider honours the SoftDeletes
    // scope. actingAs would keep the in-memory instance and hide that.
    $user = User::factory()->create([
        'email' => 'niraj@example.com',
        'password' => Hash::make('correct-horse-battery'),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'niraj@example.com',
        'password' => 'correct-horse-battery',
    ])->assertOk();

    $this->getJson('/api/v1/auth/me')->assertOk();

    $user->delete();

    // Each real request resolves its guards from scratch; the test client
    // reuses one application instance, so the cached guard is dropped here to
    // reproduce that.
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});
