<?php

declare(strict_types=1);

use App\Models\OnesiBox;
use App\Models\PlaybackSession;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-04-26 14:00:00', 'UTC'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function makeSessionFor(int $startedMinutesAgo, int $durationMinutes, int $position = 0): PlaybackSession
{
    $box = OnesiBox::factory()->create();
    $playlist = Playlist::factory()->forOnesiBox($box)->create();

    return PlaybackSession::factory()->create([
        'onesi_box_id' => $box->id,
        'playlist_id' => $playlist->id,
        'started_at' => Carbon::now()->subMinutes($startedMinutesAgo),
        'duration_minutes' => $durationMinutes,
        'current_position' => $position,
    ]);
}

describe('expiresAt', function (): void {
    it('returns started_at plus duration_minutes', function (): void {
        $session = makeSessionFor(startedMinutesAgo: 10, durationMinutes: 30);

        expect($session->expiresAt()->toDateTimeString())->toBe('2026-04-26 14:20:00');
    });
});

describe('isExpired', function (): void {
    it('is false while the session is still within its window', function (): void {
        $session = makeSessionFor(startedMinutesAgo: 10, durationMinutes: 30);

        expect($session->isExpired())->toBeFalse();
    });

    it('is true once the window has elapsed', function (): void {
        $session = makeSessionFor(startedMinutesAgo: 31, durationMinutes: 30);

        expect($session->isExpired())->toBeTrue();
    });

    it('is true at the exact boundary (expiresAt past, not present)', function (): void {
        // started 30m ago + duration 30m → expires NOW. isPast() requires strictly past.
        $session = makeSessionFor(startedMinutesAgo: 30, durationMinutes: 30);

        // Move test time 1 second forward to avoid the equality edge.
        Carbon::setTestNow(Carbon::now()->addSecond());

        expect($session->isExpired())->toBeTrue();
    });
});

describe('timeRemainingSeconds', function (): void {
    it('returns the seconds left in the window', function (): void {
        $session = makeSessionFor(startedMinutesAgo: 10, durationMinutes: 30);

        expect($session->timeRemainingSeconds())->toBe(20 * 60);
    });

    it('clamps at zero when the window has already elapsed', function (): void {
        $session = makeSessionFor(startedMinutesAgo: 60, durationMinutes: 30);

        expect($session->timeRemainingSeconds())->toBe(0);
    });

    it('returns the duration in seconds when the session just started', function (): void {
        $session = makeSessionFor(startedMinutesAgo: 0, durationMinutes: 15);

        expect($session->timeRemainingSeconds())->toBe(15 * 60);
    });
});

describe('currentItem', function (): void {
    it('returns the playlist item matching current_position', function (): void {
        $box = OnesiBox::factory()->create();
        $playlist = Playlist::factory()->forOnesiBox($box)->create();
        $first = PlaylistItem::factory()->create(['playlist_id' => $playlist->id, 'position' => 0]);
        PlaylistItem::factory()->create(['playlist_id' => $playlist->id, 'position' => 1]);

        $session = PlaybackSession::factory()->create([
            'onesi_box_id' => $box->id,
            'playlist_id' => $playlist->id,
            'current_position' => 0,
        ]);

        expect($session->currentItem()?->id)->toBe($first->id);
    });

    it('returns null when there is no item at current_position', function (): void {
        $box = OnesiBox::factory()->create();
        $playlist = Playlist::factory()->forOnesiBox($box)->create();
        PlaylistItem::factory()->create(['playlist_id' => $playlist->id, 'position' => 0]);

        $session = PlaybackSession::factory()->create([
            'onesi_box_id' => $box->id,
            'playlist_id' => $playlist->id,
            'current_position' => 99,
        ]);

        expect($session->currentItem())->toBeNull();
    });
});
