<?php

declare(strict_types=1);

use App\Enums\PlaybackEventType;
use App\Models\OnesiBox;
use App\Models\User;

// ============================================
// User Story 3: POST /api/v1/appliances/playback
// ============================================

test('authenticated appliance logs started event with media_url and media_type', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.playback'),
        [
            'event' => 'started',
            'media_url' => 'https://www.jw.org/media/video/example.mp4',
            'media_type' => 'video',
            'duration' => 3600,
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'logged',
                'event_id',
            ],
        ])
        ->assertJsonPath('data.logged', true);

    $this->assertDatabaseHas('playback_events', [
        'onesi_box_id' => $onesiBox->id,
        'event' => PlaybackEventType::Started->value,
        'media_url' => 'https://www.jw.org/media/video/example.mp4',
        'media_type' => 'video',
        'duration' => 3600,
    ]);
});

test('authenticated appliance logs paused event with position', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.playback'),
        [
            'event' => 'paused',
            'media_url' => 'https://www.jw.org/media/audio/example.mp3',
            'media_type' => 'audio',
            'position' => 1234,
            'duration' => 3600,
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertOk()
        ->assertJsonPath('data.logged', true);

    $this->assertDatabaseHas('playback_events', [
        'onesi_box_id' => $onesiBox->id,
        'event' => PlaybackEventType::Paused->value,
        'position' => 1234,
    ]);
});

test('authenticated appliance logs error event with error_message', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.playback'),
        [
            'event' => 'error',
            'media_url' => 'https://www.jw.org/media/video/example.mp4',
            'media_type' => 'video',
            'error_message' => 'Codec video non supportato',
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertOk()
        ->assertJsonPath('data.logged', true);

    $this->assertDatabaseHas('playback_events', [
        'onesi_box_id' => $onesiBox->id,
        'event' => PlaybackEventType::Error->value,
        'error_message' => 'Codec video non supportato',
    ]);
});

test('appliance with invalid token receives 401 Unauthorized', function (): void {
    $response = $this->postJson(
        route('api.v1.appliances.playback'),
        [
            'event' => 'started',
            'media_url' => 'https://www.jw.org/media/video/example.mp4',
            'media_type' => 'video',
        ],
        ['Authorization' => 'Bearer invalid-token-12345']
    );

    $response->assertUnauthorized();
});

test('request without token receives 401 Unauthorized', function (): void {
    $response = $this->postJson(
        route('api.v1.appliances.playback'),
        [
            'event' => 'started',
            'media_url' => 'https://www.jw.org/media/video/example.mp4',
            'media_type' => 'video',
        ]
    );

    $response->assertUnauthorized();
});

test('disabled appliance receives 403 Forbidden', function (): void {
    $onesiBox = OnesiBox::factory()->inactive()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.playback'),
        [
            'event' => 'started',
            'media_url' => 'https://www.jw.org/media/video/example.mp4',
            'media_type' => 'video',
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertForbidden()
        ->assertJson([
            'message' => 'Appliance disabilitata.',
            'error_code' => 'E003',
        ]);
});

test('user token cannot access playback endpoint', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('user-token');

    $response = $this->postJson(
        route('api.v1.appliances.playback'),
        [
            'event' => 'started',
            'media_url' => 'https://www.jw.org/media/video/example.mp4',
            'media_type' => 'video',
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertForbidden();
});

test('validation errors for missing required fields', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.playback'),
        [],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['event', 'media_url', 'media_type']);
});

test('validation errors for invalid media_url', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.playback'),
        [
            'event' => 'started',
            'media_url' => 'not-a-valid-url',
            'media_type' => 'video',
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['media_url']);
});

test('validation errors for invalid event type', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.playback'),
        [
            'event' => 'invalid-event',
            'media_url' => 'https://www.jw.org/media/video/example.mp4',
            'media_type' => 'video',
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['event']);
});

test('validation errors for invalid media_type', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.playback'),
        [
            'event' => 'started',
            'media_url' => 'https://www.jw.org/media/video/example.mp4',
            'media_type' => 'image',
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['media_type']);
});

test('all valid event types are accepted', function (PlaybackEventType $eventType): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.playback'),
        [
            'event' => $eventType->value,
            'media_url' => 'https://www.jw.org/media/video/example.mp4',
            'media_type' => 'video',
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertOk()
        ->assertJsonPath('data.logged', true);
})->with([
    'started' => PlaybackEventType::Started,
    'paused' => PlaybackEventType::Paused,
    'resumed' => PlaybackEventType::Resumed,
    'stopped' => PlaybackEventType::Stopped,
    'completed' => PlaybackEventType::Completed,
    'error' => PlaybackEventType::Error,
]);

test('playback event returns event_id in response', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.playback'),
        [
            'event' => 'started',
            'media_url' => 'https://www.jw.org/media/video/example.mp4',
            'media_type' => 'video',
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $eventId = $response->json('data.event_id');
    expect($eventId)->toBeInt();

    $this->assertDatabaseHas('playback_events', [
        'id' => $eventId,
        'onesi_box_id' => $onesiBox->id,
    ]);
});

test('error_message max length is validated', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.playback'),
        [
            'event' => 'error',
            'media_url' => 'https://www.jw.org/media/video/example.mp4',
            'media_type' => 'video',
            'error_message' => str_repeat('a', 1001),
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['error_message']);
});

test('media_url max length is validated', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.playback'),
        [
            'event' => 'started',
            'media_url' => 'https://www.jw.org/media/video/'.str_repeat('a', 2100).'.mp4',
            'media_type' => 'video',
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['media_url']);
});
