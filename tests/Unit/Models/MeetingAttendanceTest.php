<?php

use App\Enums\MeetingAttendanceStatus;
use App\Enums\MeetingJoinMode;
use App\Models\MeetingAttendance;
use App\Models\MeetingInstance;
use App\Models\OnesiBox;

it('belongs to a meeting instance and onesi box', function () {
    $instance = MeetingInstance::factory()->create();
    $box = OnesiBox::factory()->create();
    $attendance = MeetingAttendance::factory()->create([
        'meeting_instance_id' => $instance->id,
        'onesi_box_id' => $box->id,
    ]);

    expect($attendance->meetingInstance->id)->toBe($instance->id);
    expect($attendance->onesiBox->id)->toBe($box->id);
});

it('casts join_mode and status correctly', function () {
    $attendance = MeetingAttendance::factory()->create([
        'join_mode' => 'auto',
        'status' => 'pending',
    ]);

    expect($attendance->join_mode)->toBe(MeetingJoinMode::Auto);
    expect($attendance->status)->toBe(MeetingAttendanceStatus::Pending);
});

it('scopes to active attendances', function () {
    MeetingAttendance::factory()->create(['status' => 'joined']);
    MeetingAttendance::factory()->create(['status' => 'completed']);

    expect(MeetingAttendance::active()->count())->toBe(1);
});
