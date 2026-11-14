<?php

declare(strict_types=1);

use App\Enums\OnesiBoxStatus;
use App\Models\OnesiBox;

/**
 * @param  array<string, mixed>  $payload
 */
function reportPlayback(OnesiBox $onesiBox, array $payload): void
{
    test()->postJson(
        route('api.v1.appliances.playback'),
        ['media_type' => 'video', ...$payload],
        ['Authorization' => 'Bearer '.$onesiBox->createToken('onesibox-api-token')->plainTextToken],
    )->assertOk();
}

it('shows the started media as now playing without waiting for the next heartbeat', function (): void {
    $onesiBox = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Idle,
        'last_seen_at' => now()->subSeconds(25),
    ]);

    reportPlayback($onesiBox, ['event' => 'started', 'media_url' => 'https://www.jw.org/video/a', 'duration' => 300]);

    expect($onesiBox->refresh())
        ->status->toBe(OnesiBoxStatus::Playing)
        ->current_media_url->toBe('https://www.jw.org/video/a')
        ->current_media_type->toBe('video')
        ->current_media_position->toBe(0)
        ->current_media_duration->toBe(300)
        ->and($onesiBox->last_seen_at->diffInSeconds(now()))->toBeLessThan(2);
});

it('replaces the title of the previous media when a new one starts', function (): void {
    $onesiBox = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Playing,
        'current_media_url' => 'https://www.jw.org/video/a',
        'current_media_title' => 'Video A',
    ]);

    reportPlayback($onesiBox, ['event' => 'started', 'media_url' => 'https://www.jw.org/video/b']);

    expect($onesiBox->refresh())
        ->current_media_url->toBe('https://www.jw.org/video/b')
        ->current_media_title->toBeNull();
});

it('clears now playing when the current media ends', function (string $event): void {
    $onesiBox = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Playing,
        'current_media_url' => 'https://www.jw.org/video/a',
        'current_media_type' => 'video',
        'current_media_position' => 120,
    ]);

    reportPlayback($onesiBox, ['event' => $event, 'media_url' => 'https://www.jw.org/video/a']);

    expect($onesiBox->refresh())
        ->status->toBe(OnesiBoxStatus::Idle)
        ->current_media_url->toBeNull()
        ->current_media_position->toBeNull();
})->with(['stopped', 'completed', 'error']);

it('ignores the end of a media that is no longer the current one', function (): void {
    $onesiBox = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Playing,
        'current_media_url' => 'https://www.jw.org/video/b',
    ]);

    reportPlayback($onesiBox, ['event' => 'stopped', 'media_url' => 'https://www.jw.org/video/a']);

    expect($onesiBox->refresh())
        ->status->toBe(OnesiBoxStatus::Playing)
        ->current_media_url->toBe('https://www.jw.org/video/b');
});

it('keeps the box in a call when media starts during it', function (): void {
    $onesiBox = OnesiBox::factory()->online()->create(['status' => OnesiBoxStatus::Calling]);

    reportPlayback($onesiBox, ['event' => 'started', 'media_url' => 'https://www.jw.org/video/a']);

    expect($onesiBox->refresh()->status)->toBe(OnesiBoxStatus::Calling);
});

it('records the position reported with a pause', function (): void {
    $onesiBox = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Playing,
        'current_media_url' => 'https://www.jw.org/video/a',
        'current_media_position' => 10,
    ]);

    reportPlayback($onesiBox, ['event' => 'paused', 'media_url' => 'https://www.jw.org/video/a', 'position' => 95]);

    expect($onesiBox->refresh()->current_media_position)->toBe(95);
});
