<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('registers a user and returns an access token', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.user.name', 'Jane Doe')
        ->assertJsonPath('data.user.email', 'jane@example.com')
        ->assertJsonStructure([
            'data' => [
                'user' => ['id', 'name', 'email'],
                'token' => ['access_token', 'token_type', 'expires_in'],
            ],
        ]);

    $this->assertDatabaseHas('users', [
        'email' => 'jane@example.com',
    ]);
});

it('logs in a registered user and returns an access token', function () {
    User::factory()->create([
        'email' => 'jane@example.com',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'password123',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.user.email', 'jane@example.com')
        ->assertJsonStructure([
            'data' => [
                'user' => ['id', 'name', 'email'],
                'token' => ['access_token', 'token_type', 'expires_in'],
            ],
        ]);
});

it('rejects invalid login credentials', function () {
    User::factory()->create([
        'email' => 'jane@example.com',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'wrong-password',
    ]);

    $response
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Invalid credentials.');
});

it('returns the authenticated user profile', function () {
    $user = User::factory()->create();
    $token = auth('api')->login($user);

    $response = $this
        ->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/auth/me');

    $response
        ->assertOk()
        ->assertJsonPath('data.email', $user->email);
});

it('logs out the authenticated user', function () {
    $user = User::factory()->create();
    $token = auth('api')->login($user);

    $response = $this
        ->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/logout');

    $response
        ->assertOk()
        ->assertJsonPath('message', 'Logged out successfully.');
});
