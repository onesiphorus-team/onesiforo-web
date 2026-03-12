<?php

use App\Enums\MeetingAttendanceStatus;

it('has the correct cases', function () {
    expect(MeetingAttendanceStatus::cases())->toHaveCount(5);
    expect(MeetingAttendanceStatus::Pending->value)->toBe('pending');
    expect(MeetingAttendanceStatus::Confirmed->value)->toBe('confirmed');
    expect(MeetingAttendanceStatus::Joined->value)->toBe('joined');
    expect(MeetingAttendanceStatus::Completed->value)->toBe('completed');
    expect(MeetingAttendanceStatus::Skipped->value)->toBe('skipped');
});

it('identifies active statuses', function () {
    expect(MeetingAttendanceStatus::Joined->isActive())->toBeTrue();
    expect(MeetingAttendanceStatus::Confirmed->isActive())->toBeFalse();
    expect(MeetingAttendanceStatus::Completed->isActive())->toBeFalse();
});
