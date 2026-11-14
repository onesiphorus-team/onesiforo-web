<?php

declare(strict_types=1);

use App\Actions\Meetings\JoinCongregationMeetingAction;
use App\Enums\CommandType;
use App\Enums\MeetingAttendanceStatus;
use App\Enums\MeetingType;
use App\Exceptions\OnesiBoxOfflineException;
use App\Models\Command;
use App\Models\Congregation;
use App\Models\MeetingAttendance;
use App\Models\MeetingInstance;
use App\Models\OnesiBox;
use App\Models\Recipient;

beforeEach(function (): void {
    $this->congregation = Congregation::factory()->create();
    $this->box = OnesiBox::factory()->online()->create([
        'recipient_id' => Recipient::factory()->create(['congregation_id' => $this->congregation->id])->id,
    ])->refresh();
});

it('records an ad-hoc meeting and asks the box to join', function (): void {
    expect(resolve(JoinCongregationMeetingAction::class)->execute($this->box))->toBeTrue();

    $attendance = MeetingAttendance::query()->sole();

    expect($attendance->status)->toBe(MeetingAttendanceStatus::Joined)
        ->and($attendance->meetingInstance->type)->toBe(MeetingType::Adhoc)
        ->and(Command::query()->where('type', CommandType::JoinZoom)->count())->toBe(1);
});

it('records nothing when the box is offline', function (): void {
    $this->box->update(['last_seen_at' => now()->subMinutes(10)]);

    expect(fn () => resolve(JoinCongregationMeetingAction::class)->execute($this->box))
        ->toThrow(OnesiBoxOfflineException::class);

    expect(MeetingInstance::query()->count())->toBe(0)
        ->and(MeetingAttendance::query()->count())->toBe(0)
        ->and(Command::query()->count())->toBe(0);
});
