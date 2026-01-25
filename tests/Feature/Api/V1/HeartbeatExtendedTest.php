<?php

declare(strict_types=1);

use App\Enums\OnesiBoxStatus;
use App\Models\OnesiBox;

test('heartbeat persists current_media info to onesibox', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        [
            'status' => OnesiBoxStatus::Playing->value,
            'current_media' => [
                'url' => 'https://example.com/video.mp4',
                'type' => 'video',
                'title' => 'Test Video',
                'position' => 120,
                'duration' => 3600,
            ],
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertOk();

    $onesiBox->refresh();
    expect($onesiBox->current_media_url)->toBe('https://example.com/video.mp4');
    expect($onesiBox->current_media_type)->toBe('video');
    expect($onesiBox->current_media_title)->toBe('Test Video');
});

test('heartbeat persists current_meeting info to onesibox', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        [
            'status' => OnesiBoxStatus::Calling->value,
            'current_meeting' => [
                'meeting_id' => '123456789',
            ],
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertOk();

    $onesiBox->refresh();
    expect($onesiBox->current_meeting_id)->toBe('123456789');
});

test('heartbeat persists volume to onesibox', function (): void {
    $onesiBox = OnesiBox::factory()->create(['volume' => 80]);
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        [
            'status' => OnesiBoxStatus::Idle->value,
            'volume' => 60,
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertOk();

    $onesiBox->refresh();
    expect($onesiBox->volume)->toBe(60);
});

test('heartbeat clears media info when not playing', function (): void {
    $onesiBox = OnesiBox::factory()->create([
        'current_media_url' => 'https://example.com/old.mp4',
        'current_media_type' => 'video',
        'current_media_title' => 'Old Video',
    ]);
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        [
            'status' => OnesiBoxStatus::Idle->value,
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertOk();

    $onesiBox->refresh();
    expect($onesiBox->current_media_url)->toBeNull();
    expect($onesiBox->current_media_type)->toBeNull();
    expect($onesiBox->current_media_title)->toBeNull();
});

test('heartbeat clears meeting info when not calling', function (): void {
    $onesiBox = OnesiBox::factory()->create([
        'current_meeting_id' => '123456789',
    ]);
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        [
            'status' => OnesiBoxStatus::Idle->value,
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertOk();

    $onesiBox->refresh();
    expect($onesiBox->current_meeting_id)->toBeNull();
});

test('heartbeat validates volume must be between 0 and 100', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        [
            'status' => OnesiBoxStatus::Idle->value,
            'volume' => 150,
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['volume']);
});

test('heartbeat validates current_meeting requires meeting_id when meeting data provided', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        [
            'status' => OnesiBoxStatus::Calling->value,
            'current_meeting' => [
                'some_other_field' => 'value',
            ],
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['current_meeting.meeting_id']);
});

test('heartbeat accepts audio type for current_media', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        [
            'status' => OnesiBoxStatus::Playing->value,
            'current_media' => [
                'url' => 'https://example.com/audio.mp3',
                'type' => 'audio',
                'title' => 'Test Audio',
            ],
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertOk();

    $onesiBox->refresh();
    expect($onesiBox->current_media_type)->toBe('audio');
});

test('heartbeat updates status field on onesibox', function (): void {
    $onesiBox = OnesiBox::factory()->create(['status' => OnesiBoxStatus::Idle]);
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        [
            'status' => OnesiBoxStatus::Playing->value,
            'current_media' => [
                'url' => 'https://example.com/video.mp4',
                'type' => 'video',
            ],
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertOk();

    $onesiBox->refresh();
    expect($onesiBox->status)->toBe(OnesiBoxStatus::Playing);
});
