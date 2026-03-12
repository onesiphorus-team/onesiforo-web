<?php

declare(strict_types=1);

use App\Enums\MeetingAttendanceStatus;
use App\Enums\MeetingInstanceStatus;
use App\Models\MeetingAttendance;
use App\Models\MeetingInstance;
use Carbon\Carbon;

it('closes stale meeting instances older than 4 hours', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-11 23:00', 'UTC'));

    $stale = MeetingInstance::factory()->inProgress()->create([
        'scheduled_at' => Carbon::parse('2026-03-11 18:00', 'UTC'), // 5h ago
    ]);
    $recent = MeetingInstance::factory()->inProgress()->create([
        'scheduled_at' => Carbon::parse('2026-03-11 20:00', 'UTC'), // 3h ago
    ]);

    $this->artisan('meetings:cleanup')->assertExitCode(0);

    expect($stale->fresh()->status)->toBe(MeetingInstanceStatus::Completed);
    expect($recent->fresh()->status)->toBe(MeetingInstanceStatus::InProgress);
});

it('marks pending attendances as skipped for stale instances', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-11 23:00', 'UTC'));

    $instance = MeetingInstance::factory()->notified()->create([
        'scheduled_at' => Carbon::parse('2026-03-11 18:00', 'UTC'),
    ]);
    $pendingAttendance = MeetingAttendance::factory()->create([
        'meeting_instance_id' => $instance->id,
        'status' => MeetingAttendanceStatus::Pending,
    ]);
    $joinedAttendance = MeetingAttendance::factory()->joined()->create([
        'meeting_instance_id' => $instance->id,
    ]);

    $this->artisan('meetings:cleanup')->assertExitCode(0);

    expect($pendingAttendance->fresh()->status)->toBe(MeetingAttendanceStatus::Skipped);
    expect($joinedAttendance->fresh()->status)->toBe(MeetingAttendanceStatus::Completed);
    expect($joinedAttendance->fresh()->left_at)->not->toBeNull();
});
