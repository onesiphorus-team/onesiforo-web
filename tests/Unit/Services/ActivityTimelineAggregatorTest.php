<?php

declare(strict_types=1);

use App\Enums\ActivityTimelineKind;
use App\Enums\MeetingAttendanceStatus;
use App\Enums\MeetingType;
use App\Enums\PlaybackEventType;
use App\Models\Congregation;
use App\Models\MeetingAttendance;
use App\Models\MeetingInstance;
use App\Models\OnesiBox;
use App\Models\PlaybackEvent;
use App\Services\ActivityTimelineAggregator;
use Illuminate\Support\Carbon;

beforeEach(fn () => freezeTestTime('2026-04-26 12:00:00'));
afterEach(fn () => releaseTestTime());

function aggregateForToday(OnesiBox $box): Illuminate\Support\Collection
{
    return resolve(ActivityTimelineAggregator::class)
        ->forBox($box, Carbon::now()->startOfDay(), Carbon::now());
}

function makePlayback(OnesiBox $box, PlaybackEventType $event, Carbon $at, string $url = 'https://www.jw.org/audio.mp3', string $type = 'audio'): PlaybackEvent
{
    return PlaybackEvent::factory()->create([
        'onesi_box_id' => $box->id,
        'event' => $event,
        'media_url' => $url,
        'media_type' => $type,
        'created_at' => $at,
    ]);
}

it('returns an empty collection when the box has no events in the window', function (): void {
    $box = OnesiBox::factory()->create();

    expect(aggregateForToday($box))->toBeEmpty();
});

it('emits one entry for a started + completed session with the host as label', function (): void {
    $box = OnesiBox::factory()->create();

    makePlayback($box, PlaybackEventType::Started, Carbon::now()->subMinutes(30));
    makePlayback($box, PlaybackEventType::Completed, Carbon::now()->subMinutes(5));

    $entries = aggregateForToday($box);

    expect($entries)->toHaveCount(1)
        ->and($entries->first()->kind)->toBe(ActivityTimelineKind::Playback)
        ->and($entries->first()->label)->toBe('www.jw.org')
        ->and($entries->first()->iconName)->toBe('speaker-wave')
        ->and($entries->first()->startedAt->equalTo(Carbon::now()->subMinutes(30)))->toBeTrue()
        ->and($entries->first()->endedAt?->equalTo(Carbon::now()->subMinutes(5)))->toBeTrue()
        ->and($entries->first()->metadata)->toBeNull();
});

it('counts a paused + resumed pair as 2 pause-related events with plural metadata', function (): void {
    $box = OnesiBox::factory()->create();
    $base = Carbon::now()->subMinutes(30);

    makePlayback($box, PlaybackEventType::Started, $base);
    makePlayback($box, PlaybackEventType::Paused, $base->copy()->addMinutes(5));
    makePlayback($box, PlaybackEventType::Resumed, $base->copy()->addMinutes(7));
    makePlayback($box, PlaybackEventType::Completed, $base->copy()->addMinutes(20));

    expect(aggregateForToday($box)->first()->metadata)->toBe('2 pause');
});

it('uses the singular form for one pause event', function (): void {
    $box = OnesiBox::factory()->create();
    $base = Carbon::now()->subMinutes(30);

    makePlayback($box, PlaybackEventType::Started, $base);
    makePlayback($box, PlaybackEventType::Paused, $base->copy()->addMinutes(5));
    makePlayback($box, PlaybackEventType::Completed, $base->copy()->addMinutes(20));

    expect(aggregateForToday($box)->first()->metadata)->toBe('1 pausa');
});

it('emits two entries for two consecutive started/completed sessions', function (): void {
    $box = OnesiBox::factory()->create();
    $base = Carbon::now()->subHours(2);

    makePlayback($box, PlaybackEventType::Started, $base, 'https://a.example/track-1.mp3');
    makePlayback($box, PlaybackEventType::Completed, $base->copy()->addMinutes(20), 'https://a.example/track-1.mp3');
    makePlayback($box, PlaybackEventType::Started, $base->copy()->addMinutes(30), 'https://b.example/track-2.mp3');
    makePlayback($box, PlaybackEventType::Completed, $base->copy()->addMinutes(50), 'https://b.example/track-2.mp3');

    $entries = aggregateForToday($box);

    expect($entries)->toHaveCount(2)
        ->and($entries->pluck('label')->all())->toContain('a.example')
        ->and($entries->pluck('label')->all())->toContain('b.example');
});

it('skips orphan close events whose Started predates the window', function (): void {
    $box = OnesiBox::factory()->create();

    makePlayback($box, PlaybackEventType::Completed, Carbon::now()->subMinutes(10));

    expect(aggregateForToday($box))->toBeEmpty();
});

it('emits an in-progress entry for a Started event with no terminal close', function (): void {
    $box = OnesiBox::factory()->create();

    makePlayback($box, PlaybackEventType::Started, Carbon::now()->subMinutes(15), 'https://www.jw.org/video.mp4', 'video');

    $entries = aggregateForToday($box);

    expect($entries)->toHaveCount(1)
        ->and($entries->first()->endedAt)->toBeNull()
        ->and($entries->first()->isInProgress())->toBeTrue()
        ->and($entries->first()->iconName)->toBe('video-camera');
});

it('emits a meeting entry for an attended meeting joined and left in the window', function (): void {
    $box = OnesiBox::factory()->create();
    $congregation = Congregation::factory()->create(['name' => 'Cappelle sul Tavo']);
    $instance = MeetingInstance::factory()->create([
        'congregation_id' => $congregation->id,
        'type' => MeetingType::Weekend,
        'scheduled_at' => Carbon::now()->subHours(2),
    ]);
    MeetingAttendance::factory()->create([
        'meeting_instance_id' => $instance->id,
        'onesi_box_id' => $box->id,
        'status' => MeetingAttendanceStatus::Joined,
        'joined_at' => Carbon::now()->subHours(2),
        'left_at' => Carbon::now()->subHour(),
    ]);

    $entries = aggregateForToday($box);

    expect($entries)->toHaveCount(1)
        ->and($entries->first()->kind)->toBe(ActivityTimelineKind::Meeting)
        ->and($entries->first()->label)->toBe(MeetingType::Weekend->getLabel())
        ->and($entries->first()->iconName)->toBe('phone')
        ->and($entries->first()->metadata)->toBe('Cappelle sul Tavo');
});

it('does not emit skipped meetings', function (): void {
    $box = OnesiBox::factory()->create();
    $instance = MeetingInstance::factory()->create([
        'scheduled_at' => Carbon::now()->subHour(),
    ]);
    MeetingAttendance::factory()->create([
        'meeting_instance_id' => $instance->id,
        'onesi_box_id' => $box->id,
        'status' => MeetingAttendanceStatus::Skipped,
    ]);

    expect(aggregateForToday($box))->toBeEmpty();
});

it('emits an in-progress meeting entry when joined but not yet left', function (): void {
    $box = OnesiBox::factory()->create();
    $instance = MeetingInstance::factory()->create([
        'scheduled_at' => Carbon::now()->subMinutes(10),
    ]);
    MeetingAttendance::factory()->create([
        'meeting_instance_id' => $instance->id,
        'onesi_box_id' => $box->id,
        'status' => MeetingAttendanceStatus::Joined,
        'joined_at' => Carbon::now()->subMinutes(10),
        'left_at' => null,
    ]);

    $entries = aggregateForToday($box);

    expect($entries)->toHaveCount(1)
        ->and($entries->first()->endedAt)->toBeNull();
});

it('orders mixed entries by startedAt DESC', function (): void {
    $box = OnesiBox::factory()->create();

    makePlayback($box, PlaybackEventType::Started, Carbon::now()->subHours(4));
    makePlayback($box, PlaybackEventType::Completed, Carbon::now()->subHours(3));

    $instance = MeetingInstance::factory()->create([
        'scheduled_at' => Carbon::now()->subHour(),
    ]);
    MeetingAttendance::factory()->create([
        'meeting_instance_id' => $instance->id,
        'onesi_box_id' => $box->id,
        'status' => MeetingAttendanceStatus::Joined,
        'joined_at' => Carbon::now()->subHour(),
        'left_at' => Carbon::now()->subMinutes(10),
    ]);

    $entries = aggregateForToday($box);

    expect($entries)->toHaveCount(2)
        ->and($entries->first()->kind)->toBe(ActivityTimelineKind::Meeting)
        ->and($entries->last()->kind)->toBe(ActivityTimelineKind::Playback);
});

it('excludes events outside the window', function (): void {
    $box = OnesiBox::factory()->create();

    makePlayback($box, PlaybackEventType::Started, Carbon::now()->subDay()->subHour());
    makePlayback($box, PlaybackEventType::Completed, Carbon::now()->subDay()->subMinutes(30));

    expect(aggregateForToday($box))->toBeEmpty();
});

it('falls back to "Riproduzione" when media_url has no parseable host', function (): void {
    $box = OnesiBox::factory()->create();
    $base = Carbon::now()->subMinutes(30);

    PlaybackEvent::factory()->create([
        'onesi_box_id' => $box->id,
        'event' => PlaybackEventType::Started,
        'media_url' => '',
        'media_type' => 'audio',
        'created_at' => $base,
    ]);
    makePlayback($box, PlaybackEventType::Completed, $base->copy()->addMinutes(10));

    expect(aggregateForToday($box)->first()->label)->toBe('Riproduzione');
});

it('treats Error as a terminal event that closes the open session', function (): void {
    $box = OnesiBox::factory()->create();
    $base = Carbon::now()->subMinutes(30);

    makePlayback($box, PlaybackEventType::Started, $base);
    makePlayback($box, PlaybackEventType::Error, $base->copy()->addMinutes(5));

    $entries = aggregateForToday($box);

    expect($entries)->toHaveCount(1)
        ->and($entries->first()->endedAt?->equalTo($base->copy()->addMinutes(5)))->toBeTrue();
});
