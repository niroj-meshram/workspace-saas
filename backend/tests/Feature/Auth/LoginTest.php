<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'niraj@example.com',
        'password' => Hash::make('correct-horse-battery'),
    ]);
});

it('logs in with valid credentials', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'niraj@example.com',
        'password' => 'correct-horse-battery',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.id', $this->user->id)
        ->assertJsonPath('data.email', 'niraj@example.com');

    $this->assertAuthenticatedAs($this->user);
});

it('accepts the email address in any casing', function () {
    $this->postJson('/api/v1/auth/login', [
        'email' => 'NIRAJ@Example.com',
        'password' => 'correct-horse-battery',
    ])->assertOk();

    $this->assertAuthenticatedAs($this->user);
});

it('regenerates the session id on login', function () {
    $this->get('/api/v1/health');
    $before = session()->getId();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'niraj@example.com',
        'password' => 'correct-horse-battery',
    ])->assertOk();

    expect(session()->getId())->not->toBe($before);
});

it('rejects an incorrect password', function () {
    $this->postJson('/api/v1/auth/login', [
        'email' => 'niraj@example.com',
        'password' => 'wrong-password',
    ])->assertStatus(422)->assertJsonValidationErrors('email');

    $this->assertGuest();
});

it('rejects an unknown email address', function () {
    $this->postJson('/api/v1/auth/login', [
        'email' => 'nobody@example.com',
        'password' => 'correct-horse-battery',
    ])->assertStatus(422)->assertJsonValidationErrors('email');

    $this->assertGuest();
});

it('does not reveal whether the email exists', function () {
    $unknown = $this->postJson('/api/v1/auth/login', [
        'email' => 'nobody@example.com',
        'password' => 'correct-horse-battery',
    ]);

    $wrongPassword = $this->postJson('/api/v1/auth/login', [
        'email' => 'niraj@example.com',
        'password' => 'wrong-password',
    ]);

    expect($unknown->json('errors.email'))->toBe($wrongPassword->json('errors.email'));
});

it('rejects a soft deleted account', function () {
    $this->user->delete();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'niraj@example.com',
        'password' => 'correct-horse-battery',
    ])->assertStatus(422);

    $this->assertGuest();
});

it('requires both credentials to be present', function () {
    $this->postJson('/api/v1/auth/login', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

it('never exposes the password hash in the login response', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'niraj@example.com',
        'password' => 'correct-horse-battery',
    ]);

    expect(array_keys($response->json('data')))
        ->toBe(['id', 'name', 'email', 'created_at', 'updated_at']);
});

it('throttles repeated failed login attempts', function () {
    foreach (range(1, 5) as $ignored) {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'niraj@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    $this->postJson('/api/v1/auth/login', [
        'email' => 'niraj@example.com',
        'password' => 'correct-horse-battery',
    ])->assertStatus(429);

    $this->assertGuest();
});
