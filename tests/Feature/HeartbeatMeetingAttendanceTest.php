<?php

declare(strict_types=1);

use App\Enums\MeetingAttendanceStatus;
use App\Models\MeetingAttendance;
use App\Models\MeetingInstance;
use App\Models\OnesiBox;

it('completes active attendance when meeting_url goes null in heartbeat', function (): void {
    $box = OnesiBox::factory()->create([
        'current_meeting_url' => 'https://us05web.zoom.us/j/123',
    ]);

    $instance = MeetingInstance::factory()->inProgress()->create();
    $attendance = MeetingAttendance::factory()->joined()->create([
        'meeting_instance_id' => $instance->id,
        'onesi_box_id' => $box->id,
    ]);

    // Simulate heartbeat with no meeting
    resolve(App\Actions\ProcessHeartbeatAction::class)($box, [
        'status' => 'idle',
        'current_meeting' => null,
    ]);

    $attendance->refresh();
    expect($attendance->status)->toBe(MeetingAttendanceStatus::Completed);
    expect($attendance->left_at)->not->toBeNull();
});

it('does not complete attendance when meeting is still active', function (): void {
    $box = OnesiBox::factory()->create([
        'current_meeting_url' => 'https://us05web.zoom.us/j/123',
    ]);

    $attendance = MeetingAttendance::factory()->joined()->create([
        'onesi_box_id' => $box->id,
    ]);

    resolve(App\Actions\ProcessHeartbeatAction::class)($box, [
        'status' => 'calling',
        'current_meeting' => [
            'meeting_url' => 'https://us05web.zoom.us/j/123',
            'meeting_id' => '123',
            'joined_at' => now()->toISOString(),
        ],
    ]);

    expect($attendance->fresh()->status)->toBe(MeetingAttendanceStatus::Joined);
});
