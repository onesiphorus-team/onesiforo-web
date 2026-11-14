<?php

declare(strict_types=1);

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
    // The meeting is at 19:00 in Rome (18:00 UTC): eight minutes to go.
    $this->travelTo(Date::parse('2026-11-19 17:52:00', 'UTC'));

    $congregation = Congregation::factory()->create(['timezone' => 'Europe/Rome']);
    $this->box = OnesiBox::factory()->online()->create([
        'recipient_id' => Recipient::factory()->create(['congregation_id' => $congregation->id])->id,
        'meeting_join_mode' => MeetingJoinMode::Manual,
        'status' => OnesiBoxStatus::Idle,
    ]);
    $this->meeting = MeetingInstance::factory()->create([
        'congregation_id' => $congregation->id,
        'type' => MeetingType::Weekend,
        'scheduled_at' => Date::parse('2026-11-19 18:00:00', 'UTC'),
    ]);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function manualAttendance(array $attributes = []): MeetingAttendance
{
    return MeetingAttendance::factory()->create([
        'meeting_instance_id' => test()->meeting->id,
        'onesi_box_id' => test()->box->id,
        'join_mode' => MeetingJoinMode::Manual,
        'status' => MeetingAttendanceStatus::Pending,
        ...$attributes,
    ]);
}

/**
 * @return list<string>
 */
function reminderAlerts(): array
{
    $alerts = [];
    Notification::assertSentOnDemand(BoxActivityNotification::class, function (BoxActivityNotification $notification) use (&$alerts): bool {
        $alerts[] = $notification->html;

        return true;
    });

    return $alerts;
}

it('reminds that nobody confirmed the join, ten minutes before the meeting, once', function (): void {
    manualAttendance();

    $this->artisan('meetings:check-attendance')->assertSuccessful();
    $this->travel(1)->minute();
    $this->artisan('meetings:check-attendance')->assertSuccessful();

    expect(reminderAlerts())->toHaveCount(1)
        ->and(reminderAlerts()[0])->toContain(
            'Adunanza fine settimana alle 19:00: il box è in modalità manuale e nessuno ha ancora confermato il collegamento',
            route('dashboard.show', $this->box).' › Adunanze › Conferma',
        )
        ->not->toContain('spento');
});

it('also says when the box is off', function (): void {
    $this->box->update(['last_seen_at' => now()->subHour()]);
    manualAttendance();

    $this->artisan('meetings:check-attendance')->assertSuccessful();

    expect(reminderAlerts()[0])->toContain('il box risulta spento o non connesso');
});

it('does not remind too early, nor after the start', function (string $now): void {
    $this->travelTo(Date::parse($now, 'UTC'));
    manualAttendance();

    $this->artisan('meetings:check-attendance')->assertSuccessful();

    Notification::assertNothingSent();
})->with([
    '15 minutes before' => '2026-11-19 17:45:00',
    'after the start' => '2026-11-19 18:03:00',
]);

it('stays quiet once the join was confirmed or skipped, or when the box is already in the meeting', function (Closure $setUp): void {
    $setUp();

    $this->artisan('meetings:check-attendance')->assertSuccessful();

    Notification::assertNothingSent();
})->with([
    'confirmed' => [fn () => manualAttendance(['status' => MeetingAttendanceStatus::Joined, 'joined_at' => now()])],
    'skipped' => [fn () => manualAttendance(['status' => MeetingAttendanceStatus::Skipped])],
    'already in the meeting' => [function (): void {
        test()->box->update(['status' => OnesiBoxStatus::Calling, 'current_meeting_id' => '1', 'current_meeting_url' => test()->meeting->zoom_url]);
        manualAttendance();
    }],
    'alerts turned off' => [function (): void {
        test()->box->update(['activity_notifications_enabled' => false]);
        manualAttendance();
    }],
]);

it('does not remind boxes that join by themselves', function (): void {
    MeetingAttendance::factory()->auto()->create(['meeting_instance_id' => $this->meeting->id, 'onesi_box_id' => $this->box->id]);

    $this->artisan('meetings:check-attendance')->assertSuccessful();

    Notification::assertNothingSent();
});
