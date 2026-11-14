<?php

declare(strict_types=1);

use App\Actions\Meetings\JoinCongregationMeetingAction;
use App\Actions\ProcessHeartbeatAction;
use App\Enums\MeetingAttendanceStatus;
use App\Enums\MeetingJoinMode;
use App\Enums\MeetingType;
use App\Enums\OnesiBoxStatus;
use App\Models\Congregation;
use App\Models\MeetingAttendance;
use App\Models\MeetingInstance;
use App\Models\OnesiBox;
use App\Models\Recipient;
use App\Notifications\BoxActivityNotification;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
    config(['services.telegram-bot-api.support_chat_id' => '-1001234567890']);
    $this->travelTo(Date::parse('2026-11-19 18:06:00', 'UTC'));

    $this->congregation = Congregation::factory()->create(['timezone' => 'Europe/Rome']);
    $this->box = OnesiBox::factory()->online()->create([
        'recipient_id' => Recipient::factory()->create(['congregation_id' => $this->congregation->id])->id,
        'status' => OnesiBoxStatus::Idle,
    ]);
    // 19:00 in Rome: started six minutes ago.
    $this->meeting = MeetingInstance::factory()->create([
        'congregation_id' => $this->congregation->id,
        'type' => MeetingType::Midweek,
        'scheduled_at' => Date::parse('2026-11-19 18:00:00', 'UTC'),
    ]);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function autoAttendance(array $attributes = []): MeetingAttendance
{
    return MeetingAttendance::factory()->auto()->create([
        'meeting_instance_id' => test()->meeting->id,
        'onesi_box_id' => test()->box->id,
        ...$attributes,
    ]);
}

/**
 * @return list<string>
 */
function missedJoinAlerts(): array
{
    $alerts = [];
    Notification::assertSentOnDemand(BoxActivityNotification::class, function (BoxActivityNotification $notification) use (&$alerts): bool {
        $alerts[] = $notification->html;

        return true;
    });

    return $alerts;
}

it('alerts when the box was off and could not join the meeting', function (): void {
    $this->box->update(['last_seen_at' => now()->subMinutes(20)]);
    autoAttendance();

    $this->artisan('meetings:check-attendance')->assertSuccessful();

    expect(missedJoinAlerts())->toHaveCount(1)
        ->and(missedJoinAlerts()[0])->toContain('Adunanza infrasettimanale iniziata alle 19:00, ma il box non è collegato', 'spento o non connesso', 'lo collegherà da solo appena si riaccende', route('dashboard.show', $this->box));
});

it('alerts when the box was unreachable at join time and is on now', function (): void {
    autoAttendance(['status' => MeetingAttendanceStatus::Pending]);

    $this->artisan('meetings:check-attendance')->assertSuccessful();

    expect(missedJoinAlerts()[0])->toContain('collegamento automatico non è ancora partito');
});

it('alerts when the join was sent but the box is not in the meeting', function (): void {
    autoAttendance(['status' => MeetingAttendanceStatus::Joined, 'joined_at' => now()->subMinutes(10)]);

    $this->artisan('meetings:check-attendance')->assertSuccessful();

    expect(missedJoinAlerts()[0])->toContain('il box non è entrato nella riunione');
});

it('alerts only once for the same meeting', function (): void {
    autoAttendance();

    $this->artisan('meetings:check-attendance')->assertSuccessful();
    $this->travel(1)->minute();
    $this->artisan('meetings:check-attendance')->assertSuccessful();

    expect(missedJoinAlerts())->toHaveCount(1);
});

it('stays quiet when the box is in the meeting', function (): void {
    $this->box->update(['status' => OnesiBoxStatus::Calling, 'current_meeting_id' => '123', 'current_meeting_url' => $this->meeting->zoom_url]);
    autoAttendance(['status' => MeetingAttendanceStatus::Joined, 'joined_at' => now()->subMinutes(10)]);

    $this->artisan('meetings:check-attendance')->assertSuccessful();

    Notification::assertNothingSent();
});

it('leaves the box time to join, and stops checking long after the start', function (string $scheduledAt): void {
    $this->meeting->update(['scheduled_at' => Date::parse($scheduledAt, 'UTC')]);
    autoAttendance();

    $this->artisan('meetings:check-attendance')->assertSuccessful();

    Notification::assertNothingSent();
})->with([
    'started 2 minutes ago' => '2026-11-19 18:04:00',
    'started 40 minutes ago' => '2026-11-19 17:26:00',
]);

it('ignores manual boxes, skipped meetings and boxes with alerts turned off', function (Closure $setUp): void {
    $setUp();

    $this->artisan('meetings:check-attendance')->assertSuccessful();

    Notification::assertNothingSent();
})->with([
    'manual join' => [fn () => MeetingAttendance::factory()->create(['meeting_instance_id' => test()->meeting->id, 'onesi_box_id' => test()->box->id])],
    'skipped' => [fn () => autoAttendance(['status' => MeetingAttendanceStatus::Skipped])],
    'alerts off' => [function (): void {
        test()->box->update(['activity_notifications_enabled' => false]);
        autoAttendance();
    }],
    'inactive box' => [function (): void {
        test()->box->update(['is_active' => false, 'last_seen_at' => now()->subDays(30)]);
        autoAttendance();
    }],
    'ad-hoc meeting' => [function (): void {
        test()->meeting->update(['type' => MeetingType::Adhoc]);
        autoAttendance(['status' => MeetingAttendanceStatus::Joined, 'joined_at' => now()->subMinutes(6)]);
    }],
]);

it('stays quiet after the caregiver left on purpose a meeting joined by hand before its start', function (): void {
    $start = Date::parse('2026-11-19 18:00:00', 'UTC');
    $this->meeting->delete();
    $this->box->update(['meeting_join_mode' => MeetingJoinMode::Auto]);

    // 18:20 in Rome: the caregiver taps "Adunanza".
    $this->travelTo($start->copy()->subMinutes(40));
    $this->box->update(['last_seen_at' => now()]);
    resolve(JoinCongregationMeetingAction::class)->execute($this->box);

    // 18:30: the scheduled meeting is prepared; the box is in the Zoom room.
    $this->travelTo($start->copy()->subMinutes(30));
    $meeting = MeetingInstance::factory()->create([
        'congregation_id' => $this->congregation->id,
        'type' => MeetingType::Midweek,
        'scheduled_at' => $start,
        'zoom_url' => $this->congregation->zoom_url,
    ]);
    $scheduled = MeetingAttendance::factory()->auto()->create(['meeting_instance_id' => $meeting->id, 'onesi_box_id' => $this->box->id]);
    $this->box->update(['status' => OnesiBoxStatus::Calling, 'current_meeting_url' => $this->congregation->zoom_url, 'last_seen_at' => now()]);

    // 18:45: the automatic join finds the box already in the meeting.
    $this->travelTo($start->copy()->subMinutes(15));
    $this->box->update(['last_seen_at' => now()]);
    $this->artisan('meetings:auto-join')->assertSuccessful();
    expect($scheduled->fresh()->status)->toBe(MeetingAttendanceStatus::Joined);

    // 19:10: the caregiver leaves; the box reports idle.
    $this->travelTo($start->copy()->addMinutes(10));
    resolve(ProcessHeartbeatAction::class)($this->box->fresh(), ['status' => 'idle']);
    Notification::fake();

    $this->travelTo($start->copy()->addMinutes(11));
    $this->artisan('meetings:check-attendance')->assertSuccessful();

    expect(MeetingAttendance::query()->pluck('status')->all())->toBe([MeetingAttendanceStatus::Completed, MeetingAttendanceStatus::Completed]);
    Notification::assertNothingSent();
});
