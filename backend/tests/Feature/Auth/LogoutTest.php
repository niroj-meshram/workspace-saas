<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('logs out an authenticated user', function () {
    $user = User::factory()->create([
        'email' => 'niraj@example.com',
        'password' => Hash::make('correct-horse-battery'),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'niraj@example.com',
        'password' => 'correct-horse-battery',
    ])->assertOk();

    $this->assertAuthenticatedAs($user);

    $this->postJson('/api/v1/auth/logout')->assertNoContent();

    $this->assertGuest();
});

it('invalidates the session so protected endpoints are refused afterwards', function () {
    $user = User::factory()->create([
        'email' => 'niraj@example.com',
        'password' => Hash::make('correct-horse-battery'),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'niraj@example.com',
        'password' => 'correct-horse-battery',
    ])->assertOk();

    $sessionId = session()->getId();

    $this->postJson('/api/v1/auth/logout')->assertNoContent();

    expect(session()->getId())->not->toBe($sessionId);

    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('rejects logging out when not authenticated', function () {
    $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
});
