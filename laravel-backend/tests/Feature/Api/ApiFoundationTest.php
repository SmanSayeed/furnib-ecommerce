<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

it('responds 200 with json on the health endpoint', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJson(['status' => 'ok']);
});

it('rejects a protected endpoint without a token', function () {
    $this->getJson('/api/v1/me')
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'unauthenticated');
});

it('authorizes a protected endpoint with a valid sanctum token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('email', $user->email);
});

it('returns a uniform validation error envelope', function () {
    Route::post('/api/v1/_validate', function (Request $request) {
        $request->validate(['name' => 'required']);
    });

    $this->postJson('/api/v1/_validate', [])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonPath('error.details.name.0', 'The name field is required.');
});

it('hides internal details on server errors when debug is off', function () {
    config(['app.debug' => false]);

    Route::get('/api/v1/_boom', function () {
        throw new RuntimeException('secret internal detail');
    });

    $response = $this->getJson('/api/v1/_boom')->assertStatus(500);

    expect($response->json('error.code'))->toBe('server_error')
        ->and($response->json('error.message'))->toBe('Server error.');
});

it('throttles the otp endpoint after the limit', function () {
    foreach (range(1, 5) as $ignored) {
        $this->postJson('/api/v1/otp/request')->assertOk();
    }

    $this->postJson('/api/v1/otp/request')->assertStatus(429);
});
