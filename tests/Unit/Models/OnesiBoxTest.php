<?php

declare(strict_types=1);

use App\Enums\OnesiBoxPermission;
use App\Enums\PlaybackSessionStatus;
use App\Models\OnesiBox;
use App\Models\PlaybackSession;
use App\Models\Playlist;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-04-26 12:00:00', 'UTC'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

describe('isOnline', function (): void {
    it('is false when last_seen_at is null', function (): void {
        $box = OnesiBox::factory()->make(['last_seen_at' => null]);

        expect($box->isOnline())->toBeFalse();
    });

    it('is true when the last heartbeat is younger than 5 minutes', function (): void {
        $box = OnesiBox::factory()->make(['last_seen_at' => now()->subMinutes(4)->subSeconds(59)]);

        expect($box->isOnline())->toBeTrue();
    });

    it('is false at the exact 5-minute boundary (strictly inclusive offline)', function (): void {
        // The implementation uses isAfter(now()-5m), so exactly 5m old is false.
        $box = OnesiBox::factory()->make(['last_seen_at' => now()->subMinutes(5)]);

        expect($box->isOnline())->toBeFalse();
    });

    it('is false when older than 5 minutes', function (): void {
        $box = OnesiBox::factory()->make(['last_seen_at' => now()->subMinutes(6)]);

        expect($box->isOnline())->toBeFalse();
    });
});

describe('recordHeartbeat', function (): void {
    it('persists last_seen_at as the current time', function (): void {
        $box = OnesiBox::factory()->create(['last_seen_at' => null]);

        $box->recordHeartbeat();

        expect($box->fresh()->last_seen_at?->toDateTimeString())
            ->toBe('2026-04-26 12:00:00');
    });

    it('also flushes other pending attribute changes alongside the heartbeat', function (): void {
        $box = OnesiBox::factory()->create(['firmware_version' => '1.0.0']);

        $box->firmware_version = '1.0.1';
        $box->recordHeartbeat();

        expect($box->fresh())
            ->firmware_version->toBe('1.0.1')
            ->and($box->fresh()->last_seen_at?->toDateTimeString())->toBe('2026-04-26 12:00:00');
    });
});

describe('userHasFullPermission', function (): void {
    it('returns false when the user has no caregiver pivot', function (): void {
        $box = OnesiBox::factory()->create();
        $user = User::factory()->create();

        expect($box->userHasFullPermission($user))->toBeFalse();
    });

    it('returns false when the caregiver pivot has read-only permission', function (): void {
        $box = OnesiBox::factory()->create();
        $user = User::factory()->create();
        $box->caregivers()->attach($user, ['permission' => OnesiBoxPermission::ReadOnly->value]);

        expect($box->userHasFullPermission($user))->toBeFalse();
    });

    it('returns true when the caregiver pivot has full permission', function (): void {
        $box = OnesiBox::factory()->create();
        $user = User::factory()->create();
        $box->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

        expect($box->userHasFullPermission($user))->toBeTrue();
    });
});

describe('userCanView', function (): void {
    it('returns false when the user is not a caregiver of the box', function (): void {
        $box = OnesiBox::factory()->create();
        $user = User::factory()->create();

        expect($box->userCanView($user))->toBeFalse();
    });

    it('returns true even for a read-only caregiver', function (): void {
        $box = OnesiBox::factory()->create();
        $user = User::factory()->create();
        $box->caregivers()->attach($user, ['permission' => OnesiBoxPermission::ReadOnly->value]);

        expect($box->userCanView($user))->toBeTrue();
    });

    it('returns true for a full-permission caregiver', function (): void {
        $box = OnesiBox::factory()->create();
        $user = User::factory()->create();
        $box->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

        expect($box->userCanView($user))->toBeTrue();
    });
});

describe('activeSession', function (): void {
    it('returns null when there is no playback session', function (): void {
        $box = OnesiBox::factory()->create();

        expect($box->activeSession())->toBeNull();
    });

    it('returns null when all sessions are completed', function (): void {
        $box = OnesiBox::factory()->create();
        $playlist = Playlist::factory()->forOnesiBox($box)->create();
        PlaybackSession::factory()->create([
            'onesi_box_id' => $box->id,
            'playlist_id' => $playlist->id,
            'status' => PlaybackSessionStatus::Completed,
        ]);

        expect($box->activeSession())->toBeNull();
    });

    it('returns the active session when one is running', function (): void {
        $box = OnesiBox::factory()->create();
        $playlist = Playlist::factory()->forOnesiBox($box)->create();
        $session = PlaybackSession::factory()->create([
            'onesi_box_id' => $box->id,
            'playlist_id' => $playlist->id,
            'status' => PlaybackSessionStatus::Active,
        ]);

        expect($box->activeSession()?->id)->toBe($session->id);
    });
});
