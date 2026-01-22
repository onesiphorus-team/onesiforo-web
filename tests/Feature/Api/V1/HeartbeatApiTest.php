<?php

declare(strict_types=1);

use App\Enums\OnesiBoxStatus;
use App\Models\OnesiBox;
use App\Models\User;

test('onesibox can send heartbeat with valid token', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        ['status' => OnesiBoxStatus::Idle->value],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'server_time',
                'next_heartbeat',
            ],
        ]);

    expect($onesiBox->fresh()->last_seen_at)->not->toBeNull();
});

test('heartbeat updates last_seen_at timestamp', function (): void {
    $onesiBox = OnesiBox::factory()->neverSeen()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    expect($onesiBox->last_seen_at)->toBeNull();

    $this->postJson(
        route('api.v1.appliances.heartbeat'),
        ['status' => OnesiBoxStatus::Playing->value],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    expect($onesiBox->fresh()->last_seen_at)->not->toBeNull();
});

test('heartbeat fails without bearer token', function (): void {
    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        ['status' => OnesiBoxStatus::Idle->value]
    );

    $response->assertUnauthorized();
});

test('heartbeat fails with invalid token', function (): void {
    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        ['status' => OnesiBoxStatus::Idle->value],
        ['Authorization' => 'Bearer invalid-token-12345']
    );

    $response->assertUnauthorized();
});

test('heartbeat fails when token belongs to a user instead of onesibox', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('user-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        ['status' => OnesiBoxStatus::Idle->value],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertForbidden();
});

test('heartbeat identifies correct onesibox from token', function (): void {
    $onesiBox1 = OnesiBox::factory()->neverSeen()->create(['name' => 'Box1']);
    $onesiBox2 = OnesiBox::factory()->neverSeen()->create(['name' => 'Box2']);

    $token1 = $onesiBox1->createToken('onesibox-api-token');

    // Token1 should only update OnesiBox1, not OnesiBox2
    $this->postJson(
        route('api.v1.appliances.heartbeat'),
        ['status' => OnesiBoxStatus::Idle->value],
        ['Authorization' => "Bearer {$token1->plainTextToken}"]
    )->assertOk();

    // Box1 should be updated, Box2 should remain untouched
    expect(OnesiBox::query()->find($onesiBox1->id)->last_seen_at)->not->toBeNull();
    expect(OnesiBox::query()->find($onesiBox2->id)->last_seen_at)->toBeNull();
});

test('different tokens update their respective onesiboxes', function (): void {
    $onesiBox = OnesiBox::factory()->neverSeen()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    expect(OnesiBox::query()->find($onesiBox->id)->last_seen_at)->toBeNull();

    $this->postJson(
        route('api.v1.appliances.heartbeat'),
        ['status' => OnesiBoxStatus::Playing->value],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    )->assertOk();

    expect(OnesiBox::query()->find($onesiBox->id)->last_seen_at)->not->toBeNull();
});

test('heartbeat fails when onesibox is inactive', function (): void {
    $onesiBox = OnesiBox::factory()->inactive()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        ['status' => OnesiBoxStatus::Idle->value],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertForbidden()
        ->assertJson([
            'message' => 'Appliance disabilitata.',
            'error_code' => 'E003',
        ]);
});

test('heartbeat requires status field', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        [],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

test('heartbeat validates status must be valid enum', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        ['status' => 'invalid-status'],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

test('heartbeat accepts all valid status values', function (OnesiBoxStatus $status): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        ['status' => $status->value],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertOk();
})->with([
    'idle' => OnesiBoxStatus::Idle,
    'playing' => OnesiBoxStatus::Playing,
    'calling' => OnesiBoxStatus::Calling,
    'error' => OnesiBoxStatus::Error,
]);

test('heartbeat validates cpu_usage must be between 0 and 100', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        [
            'status' => OnesiBoxStatus::Idle->value,
            'cpu_usage' => 150,
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['cpu_usage']);
});

test('heartbeat validates memory_usage must be between 0 and 100', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        [
            'status' => OnesiBoxStatus::Idle->value,
            'memory_usage' => -5,
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['memory_usage']);
});

test('heartbeat validates temperature must be between 0 and 150', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        [
            'status' => OnesiBoxStatus::Idle->value,
            'temperature' => 200,
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['temperature']);
});

test('heartbeat validates uptime cannot be negative', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        [
            'status' => OnesiBoxStatus::Idle->value,
            'uptime' => -100,
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['uptime']);
});

test('heartbeat accepts full payload with all optional fields', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        [
            'status' => OnesiBoxStatus::Playing->value,
            'cpu_usage' => 45,
            'memory_usage' => 60,
            'disk_usage' => 30,
            'temperature' => 55.5,
            'uptime' => 86400,
            'current_media' => [
                'url' => 'https://example.com/video.mp4',
                'type' => 'video',
                'position' => 120,
                'duration' => 3600,
            ],
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertOk();
});

test('heartbeat validates current_media requires url and type', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        [
            'status' => OnesiBoxStatus::Playing->value,
            'current_media' => [
                'position' => 120,
            ],
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['current_media.url', 'current_media.type']);
});

test('heartbeat validates current_media url must be valid url', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        [
            'status' => OnesiBoxStatus::Playing->value,
            'current_media' => [
                'url' => 'not-a-valid-url',
                'type' => 'video',
            ],
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['current_media.url']);
});

test('heartbeat validates current_media type must be audio or video', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        [
            'status' => OnesiBoxStatus::Playing->value,
            'current_media' => [
                'url' => 'https://example.com/media.mp4',
                'type' => 'image',
            ],
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['current_media.type']);
});
