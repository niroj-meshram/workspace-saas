<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('registers a user with valid data', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Niraj',
        'email' => 'niraj@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Niraj')
        ->assertJsonPath('data.email', 'niraj@example.com')
        ->assertJsonStructure(['data' => ['id', 'name', 'email', 'created_at', 'updated_at']]);

    $user = User::firstWhere('email', 'niraj@example.com');

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Niraj');
});

it('hashes the password instead of storing it', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Niraj',
        'email' => 'niraj@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertCreated();

    $user = User::firstWhere('email', 'niraj@example.com');

    expect($user->password)->not->toBe('correct-horse-battery')
        ->and(Hash::check('correct-horse-battery', $user->password))->toBeTrue();
});

it('does not establish a session on registration', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Niraj',
        'email' => 'niraj@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertCreated();

    $this->assertGuest();
});

it('does not create a workspace on registration', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Niraj',
        'email' => 'niraj@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertCreated();

    expect(DB::table('workspaces')->count())->toBe(0)
        ->and(DB::table('workspace_members')->count())->toBe(0);
});

it('never exposes the password hash in the response', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Niraj',
        'email' => 'niraj@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ]);

    $response->assertCreated()
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.remember_token')
        ->assertJsonMissingPath('data.deleted_at');

    expect(array_keys($response->json('data')))
        ->toBe(['id', 'name', 'email', 'created_at', 'updated_at']);
});

it('rejects a payload that is missing required fields', function () {
    $this->postJson('/api/v1/auth/register', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

it('rejects an invalid email address', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Niraj',
        'email' => 'not-an-email',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});

it('rejects a password shorter than the configured minimum', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Niraj',
        'email' => 'niraj@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertStatus(422)->assertJsonValidationErrors('password');
});

it('rejects a password that is not confirmed', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Niraj',
        'email' => 'niraj@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'something-else',
    ])->assertStatus(422)->assertJsonValidationErrors('password');
});

it('rejects a name longer than 255 characters', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => str_repeat('a', 256),
        'email' => 'niraj@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertStatus(422)->assertJsonValidationErrors('name');
});

it('rejects a duplicate email address', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Niraj',
        'email' => 'taken@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertStatus(422)->assertJsonValidationErrors('email');

    expect(User::where('email', 'taken@example.com')->count())->toBe(1);
});

it('rejects a duplicate email regardless of casing', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Niraj',
        'email' => 'TAKEN@Example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});

it('keeps a soft deleted account\'s email reserved', function () {
    User::factory()->create(['email' => 'gone@example.com'])->delete();

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Niraj',
        'email' => 'gone@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});

it('throttles repeated registration attempts', function () {
    foreach (range(1, 5) as $i) {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Niraj',
            'email' => "user{$i}@example.com",
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertCreated();
    }

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Niraj',
        'email' => 'user6@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertStatus(429);
});
