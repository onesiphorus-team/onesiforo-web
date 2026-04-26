<?php

declare(strict_types=1);

use App\Models\OnesiBox;
use App\Models\PlaybackEvent;

beforeEach(fn () => freezeTestTime('2026-04-26 10:00:00'));
afterEach(fn () => releaseTestTime());

function makePlaybackEventAt(string $createdAt): PlaybackEvent
{
    $box = OnesiBox::factory()->create();

    /** @var PlaybackEvent $event */
    $event = PlaybackEvent::factory()->create(['onesi_box_id' => $box->id]);
    $event->forceFill(['created_at' => $createdAt])->save();

    return $event->fresh();
}

it('deletes playback events older than the default 30-day retention', function (): void {
    $stale = makePlaybackEventAt('2026-03-26 09:59:00'); // 31d 1m ago
    $fresh = makePlaybackEventAt('2026-04-25 12:00:00'); // ~22h ago

    $this->artisan('app:prune-playback-events')->assertSuccessful();

    expect(PlaybackEvent::query()->find($stale->id))->toBeNull()
        ->and(PlaybackEvent::query()->find($fresh->id))->not->toBeNull();
});

it('honours the --days option for a custom retention window', function (): void {
    $oldEnoughForSeven = makePlaybackEventAt('2026-04-18 09:59:00'); // 8d ago
    $tooYoungForSeven = makePlaybackEventAt('2026-04-21 12:00:00'); // <7d ago

    $this->artisan('app:prune-playback-events', ['--days' => 7])->assertSuccessful();

    expect(PlaybackEvent::query()->find($oldEnoughForSeven->id))->toBeNull()
        ->and(PlaybackEvent::query()->find($tooYoungForSeven->id))->not->toBeNull();
});

it('reports zero pruned events when nothing matches', function (): void {
    makePlaybackEventAt('2026-04-25 09:59:00'); // 1d ago, well within retention

    $this->artisan('app:prune-playback-events')
        ->expectsOutput('No old playback events to prune.')
        ->assertSuccessful();
});

it('keeps an event right at the boundary (younger than cutoff)', function (): void {
    $atBoundary = makePlaybackEventAt('2026-03-27 10:00:00'); // exactly 30d ago — NOT < cutoff

    $this->artisan('app:prune-playback-events')->assertSuccessful();

    expect(PlaybackEvent::query()->find($atBoundary->id))->not->toBeNull();
});
