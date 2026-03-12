<?php

declare(strict_types=1);

use App\Enums\MeetingAttendanceStatus;
use App\Enums\MeetingJoinMode;
use App\Models\MeetingAttendance;
use App\Models\MeetingInstance;
use App\Models\OnesiBox;
use App\Services\OnesiBoxCommandService;
use Carbon\Carbon;

beforeEach(function (): void {
    // 5 minutes before meeting
    Carbon::setTestNow(Carbon::parse('2026-03-11 18:55', 'UTC'));
});

it('dispatches join command for auto-mode boxes 5 min before meeting', function (): void {
    $mock = Mockery::mock(OnesiBoxCommandService::class);
    $mock->shouldReceive('sendZoomUrlCommand')->once();
    app()->instance(OnesiBoxCommandService::class, $mock);

    $box = OnesiBox::factory()->create([
        'meeting_join_mode' => MeetingJoinMode::Auto,
        'is_active' => true,
    ]);
    $instance = MeetingInstance::factory()->notified()->create([
        'scheduled_at' => Carbon::parse('2026-03-11 19:00', 'UTC'),
    ]);
    MeetingAttendance::factory()->auto()->create([
        'meeting_instance_id' => $instance->id,
        'onesi_box_id' => $box->id,
        'status' => MeetingAttendanceStatus::Pending,
    ]);

    $this->artisan('meetings:auto-join')->assertExitCode(0);

    $attendance = MeetingAttendance::query()->first();
    expect($attendance->status)->toBe(MeetingAttendanceStatus::Joined);
    expect($attendance->joined_at)->not->toBeNull();
});

it('skips manual-mode boxes', function (): void {
    $mock = Mockery::mock(OnesiBoxCommandService::class);
    $mock->shouldNotReceive('sendZoomUrlCommand');
    app()->instance(OnesiBoxCommandService::class, $mock);

    $box = OnesiBox::factory()->create([
        'meeting_join_mode' => MeetingJoinMode::Manual,
    ]);
    $instance = MeetingInstance::factory()->notified()->create([
        'scheduled_at' => Carbon::parse('2026-03-11 19:00', 'UTC'),
    ]);
    MeetingAttendance::factory()->create([
        'meeting_instance_id' => $instance->id,
        'onesi_box_id' => $box->id,
        'join_mode' => MeetingJoinMode::Manual,
    ]);

    $this->artisan('meetings:auto-join')->assertExitCode(0);
});

it('skips already joined attendances', function (): void {
    $mock = Mockery::mock(OnesiBoxCommandService::class);
    $mock->shouldNotReceive('sendZoomUrlCommand');
    app()->instance(OnesiBoxCommandService::class, $mock);

    $instance = MeetingInstance::factory()->notified()->create([
        'scheduled_at' => Carbon::parse('2026-03-11 19:00', 'UTC'),
    ]);
    MeetingAttendance::factory()->auto()->joined()->create([
        'meeting_instance_id' => $instance->id,
    ]);

    $this->artisan('meetings:auto-join')->assertExitCode(0);
});
