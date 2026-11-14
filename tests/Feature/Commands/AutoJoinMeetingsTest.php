<?php

declare(strict_types=1);

use App\Enums\MeetingAttendanceStatus;
use App\Enums\MeetingJoinMode;
use App\Enums\OnesiBoxStatus;
use App\Exceptions\OnesiBoxOfflineException;
use App\Models\MeetingAttendance;
use App\Models\MeetingInstance;
use App\Models\OnesiBox;
use App\Services\OnesiBoxCommandService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    $this->instance = MeetingInstance::factory()->notified()->create([
        'scheduled_at' => Carbon::parse('2026-03-11 19:00', 'UTC'),
    ]);
});

function autoJoinAttendance(OnesiBox $box): MeetingAttendance
{
    return MeetingAttendance::factory()->auto()->create([
        'meeting_instance_id' => test()->instance->id,
        'onesi_box_id' => $box->id,
        'status' => MeetingAttendanceStatus::Pending,
    ]);
}

function expectJoinCommands(int $times): void
{
    $mock = Mockery::mock(OnesiBoxCommandService::class);
    $times === 0
        ? $mock->shouldNotReceive('sendZoomUrlCommand')
        : $mock->shouldReceive('sendZoomUrlCommand')->times($times);
    app()->instance(OnesiBoxCommandService::class, $mock);
}

it('joins the meeting from 15 minutes before its start, not earlier', function (string $now, int $joins): void {
    Carbon::setTestNow(Carbon::parse($now, 'UTC'));
    expectJoinCommands($joins);
    $attendance = autoJoinAttendance(OnesiBox::factory()->online()->create(['meeting_join_mode' => MeetingJoinMode::Auto]));

    $this->artisan('meetings:auto-join')->assertExitCode(0);

    expect($attendance->fresh()->status)->toBe($joins === 1 ? MeetingAttendanceStatus::Joined : MeetingAttendanceStatus::Pending);
})->with([
    '20 minutes before' => ['2026-03-11 18:40', 0],
    '15 minutes before' => ['2026-03-11 18:45', 1],
    '5 minutes before' => ['2026-03-11 18:55', 1],
]);

it('keeps trying while the box is off and joins it as soon as it is back, even after the start', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-11 18:45', 'UTC'));
    Log::spy();
    expectJoinCommands(1);
    $box = OnesiBox::factory()->online()->create(['meeting_join_mode' => MeetingJoinMode::Auto, 'last_seen_at' => now()->subMinutes(30)]);
    $attendance = autoJoinAttendance($box);

    $this->artisan('meetings:auto-join')->assertExitCode(0);
    expect($attendance->fresh()->status)->toBe(MeetingAttendanceStatus::Pending);
    Log::shouldNotHaveReceived('warning');

    Carbon::setTestNow(Carbon::parse('2026-03-11 19:20', 'UTC'));
    $box->update(['last_seen_at' => now()]);

    $this->artisan('meetings:auto-join')->assertExitCode(0);
    expect($attendance->fresh()->status)->toBe(MeetingAttendanceStatus::Joined);
});

it('stops trying 90 minutes after the start', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-11 20:35', 'UTC'));
    expectJoinCommands(0);
    autoJoinAttendance(OnesiBox::factory()->online()->create(['meeting_join_mode' => MeetingJoinMode::Auto]));

    $this->artisan('meetings:auto-join')->assertExitCode(0);
});

it('does not send the join again to a box already in the meeting', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-11 18:55', 'UTC'));
    expectJoinCommands(0);
    $attendance = autoJoinAttendance(OnesiBox::factory()->online()->create([
        'meeting_join_mode' => MeetingJoinMode::Auto,
        'status' => OnesiBoxStatus::Calling,
        'current_meeting_id' => '123',
        'current_meeting_url' => $this->instance->zoom_url,
    ]));

    $this->artisan('meetings:auto-join')->assertExitCode(0);

    expect($attendance->fresh()->status)->toBe(MeetingAttendanceStatus::Joined);
});

it('skips manual-mode boxes', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-11 18:55', 'UTC'));
    expectJoinCommands(0);
    $box = OnesiBox::factory()->online()->create(['meeting_join_mode' => MeetingJoinMode::Manual]);
    MeetingAttendance::factory()->create([
        'meeting_instance_id' => $this->instance->id,
        'onesi_box_id' => $box->id,
        'join_mode' => MeetingJoinMode::Manual,
    ]);

    $this->artisan('meetings:auto-join')->assertExitCode(0);
});

it('skips already joined attendances', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-11 18:55', 'UTC'));
    expectJoinCommands(0);
    MeetingAttendance::factory()->auto()->joined()->create([
        'meeting_instance_id' => $this->instance->id,
    ]);

    $this->artisan('meetings:auto-join')->assertExitCode(0);
});

it('logs a structured warning and keeps attendance pending when the box goes offline while joining', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-11 18:55', 'UTC'));
    Log::spy();
    $mock = Mockery::mock(OnesiBoxCommandService::class);
    $mock->shouldReceive('sendZoomUrlCommand')->once()->andThrow(new OnesiBoxOfflineException('Box unreachable'));
    app()->instance(OnesiBoxCommandService::class, $mock);
    $box = OnesiBox::factory()->online()->create(['meeting_join_mode' => MeetingJoinMode::Auto]);
    $attendance = autoJoinAttendance($box);

    $this->artisan('meetings:auto-join')->assertExitCode(0);

    expect($attendance->fresh()->status)->toBe(MeetingAttendanceStatus::Pending);
    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'Auto-join skipped')
            && ($context['onesi_box_id'] ?? null) === $box->id
            && ($context['meeting_instance_id'] ?? null) === $this->instance->id
            && array_key_exists('exception_message', $context));
});
