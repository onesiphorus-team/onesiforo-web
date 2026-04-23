<?php

declare(strict_types=1);

use App\Events\PlaybackEventReceived;
use App\Models\OnesiBox;
use Illuminate\Support\Facades\Event;

it('broadcasts PlaybackEventReceived when a playback event is stored', function (): void {
    Event::fake([PlaybackEventReceived::class]);

    $box = OnesiBox::factory()->create();
    $token = $box->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.playback'),
        [
            'event' => 'error',
            'media_url' => 'https://stream.jw.org/6311-4713-5379-2156',
            'media_type' => 'video',
            'error_code' => 'E112',
            'error_message' => 'Ordinal 99 exceeds playlist length 4',
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertOk();

    Event::assertDispatched(PlaybackEventReceived::class, fn ($event): bool => $event->playbackEvent->onesi_box_id === $box->id
        && $event->playbackEvent->error_code === 'E112');
});

it('accepts valid error_code values', function (): void {
    $box = OnesiBox::factory()->create();
    $token = $box->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.playback'),
        [
            'event' => 'error',
            'media_url' => 'https://stream.jw.org/x',
            'media_type' => 'video',
            'error_code' => 'E110',
            'error_message' => 'DNS timeout',
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertOk();
});

it('rejects malformed error_code', function (): void {
    $box = OnesiBox::factory()->create();
    $token = $box->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.playback'),
        [
            'event' => 'error',
            'media_url' => 'https://stream.jw.org/x',
            'media_type' => 'video',
            'error_code' => 'oops',
            'error_message' => 'bad',
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['error_code']);
});
