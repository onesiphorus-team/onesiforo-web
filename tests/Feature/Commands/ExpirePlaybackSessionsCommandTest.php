<?php

declare(strict_types=1);

use App\Enums\PlaybackSessionStatus;
use App\Models\OnesiBox;
use App\Models\PlaybackSession;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use Illuminate\Support\Facades\Date;

beforeEach(function (): void {
    $this->onesiBox = OnesiBox::factory()->online()->create();
    $this->playlist = Playlist::factory()->forOnesiBox($this->onesiBox)->create();
    PlaylistItem::factory()->for($this->playlist)->atPosition(0)->create();
});

test('expired session is marked as completed', function (): void {
    Date::setTestNow('2026-02-14 12:00:00');

    $session = PlaybackSession::factory()
        ->forOnesiBox($this->onesiBox)
        ->forPlaylist($this->playlist)
        ->active()
        ->withDuration(30)
        ->create(['started_at' => Date::now()->subMinutes(60)]);

    $this->artisan('app:expire-sessions')
        ->expectsOutputToContain('Expired 1 playback session(s).')
        ->assertSuccessful();

    $session->refresh();

    expect($session->status)->toBe(PlaybackSessionStatus::Completed);
    expect($session->ended_at)->not->toBeNull();
});

test('non-expired session stays active', function (): void {
    Date::setTestNow('2026-02-14 12:00:00');

    $session = PlaybackSession::factory()
        ->forOnesiBox($this->onesiBox)
        ->forPlaylist($this->playlist)
        ->active()
        ->withDuration(60)
        ->create(['started_at' => Date::now()->subMinutes(10)]);

    $this->artisan('app:expire-sessions')
        ->expectsOutputToContain('No expired sessions found.')
        ->assertSuccessful();

    $session->refresh();

    expect($session->status)->toBe(PlaybackSessionStatus::Active);
    expect($session->ended_at)->toBeNull();
});

test('already completed sessions are not affected', function (): void {
    Date::setTestNow('2026-02-14 12:00:00');

    $endedAt = Date::now()->subMinutes(30);

    $session = PlaybackSession::factory()
        ->forOnesiBox($this->onesiBox)
        ->forPlaylist($this->playlist)
        ->completed()
        ->create([
            'started_at' => Date::now()->subMinutes(120),
            'ended_at' => $endedAt,
        ]);

    $this->artisan('app:expire-sessions')
        ->expectsOutputToContain('No expired sessions found.')
        ->assertSuccessful();

    $session->refresh();

    expect($session->status)->toBe(PlaybackSessionStatus::Completed);
    expect($session->ended_at->toDateTimeString())->toBe($endedAt->toDateTimeString());
});

test('multiple expired sessions are all completed', function (): void {
    Date::setTestNow('2026-02-14 12:00:00');

    $session1 = PlaybackSession::factory()
        ->forOnesiBox($this->onesiBox)
        ->forPlaylist($this->playlist)
        ->active()
        ->withDuration(10)
        ->create(['started_at' => Date::now()->subMinutes(60)]);

    $otherBox = OnesiBox::factory()->online()->create();
    $otherPlaylist = Playlist::factory()->forOnesiBox($otherBox)->create();
    PlaylistItem::factory()->for($otherPlaylist)->atPosition(0)->create();

    $session2 = PlaybackSession::factory()
        ->forOnesiBox($otherBox)
        ->forPlaylist($otherPlaylist)
        ->active()
        ->withDuration(15)
        ->create(['started_at' => Date::now()->subMinutes(45)]);

    $this->artisan('app:expire-sessions')
        ->expectsOutputToContain('Expired 2 playback session(s).')
        ->assertSuccessful();

    expect($session1->fresh()->status)->toBe(PlaybackSessionStatus::Completed);
    expect($session2->fresh()->status)->toBe(PlaybackSessionStatus::Completed);
});

test('session at exact boundary is expired', function (): void {
    Date::setTestNow('2026-02-14 12:00:00');

    $session = PlaybackSession::factory()
        ->forOnesiBox($this->onesiBox)
        ->forPlaylist($this->playlist)
        ->active()
        ->withDuration(30)
        ->create(['started_at' => Date::now()->subMinutes(30)]);

    $this->artisan('app:expire-sessions')
        ->expectsOutputToContain('Expired 1 playback session(s).')
        ->assertSuccessful();

    $session->refresh();
    expect($session->status)->toBe(PlaybackSessionStatus::Completed);
});
