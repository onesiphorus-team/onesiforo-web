<?php

declare(strict_types=1);

use App\Actions\Sessions\StartQueuedPlaybackSessionAction;
use App\Enums\CommandType;
use App\Enums\PlaylistSourceType;
use App\Models\OnesiBox;

/**
 * @return array{key: string, title: string, media_type: string, duration_seconds: int, duration_formatted: string, thumbnail_url: string|null, url: string}
 */
function queuedAudio(string $key, int $durationSeconds): array
{
    return [
        'key' => $key,
        'title' => "Cantico {$key}",
        'media_type' => 'audio',
        'duration_seconds' => $durationSeconds,
        'duration_formatted' => '0:00',
        'thumbnail_url' => null,
        'url' => "https://www.jw.org/it/biblioteca-digitale/audio/#it/mediaitems/AudioOriginalSongs/{$key}",
    ];
}

it('starts a session that lasts as long as the queue plus a margin', function (): void {
    $onesiBox = OnesiBox::factory()->online()->create();

    $session = resolve(StartQueuedPlaybackSessionAction::class)->execute($onesiBox, 'audio', [
        queuedAudio('pub-osg_1_AUDIO', 200),
        queuedAudio('pub-osg_2_AUDIO', 250),
    ]);

    expect($session)
        ->media_type->toBe('audio')
        ->duration_minutes->toBe(8 + StartQueuedPlaybackSessionAction::DURATION_MARGIN_MINUTES)
        ->and($session->playlist->source_type)->toBe(PlaylistSourceType::Manual)
        ->and($session->playlist->items()->orderBy('position')->pluck('title')->all())->toBe(['Cantico pub-osg_1_AUDIO', 'Cantico pub-osg_2_AUDIO'])
        ->and($onesiBox->commands()->where('type', CommandType::PlayMedia)->sole()->payload)->toMatchArray([
            'url' => 'https://www.jw.org/it/biblioteca-digitale/audio/#it/mediaitems/AudioOriginalSongs/pub-osg_1_AUDIO',
            'media_type' => 'audio',
        ]);
});

it('refuses an empty queue', function (): void {
    resolve(StartQueuedPlaybackSessionAction::class)->execute(OnesiBox::factory()->online()->create(), 'video', []);
})->throws(InvalidArgumentException::class);
