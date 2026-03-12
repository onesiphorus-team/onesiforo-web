<?php

declare(strict_types=1);

use App\Enums\MeetingAttendanceStatus;
use App\Enums\MeetingInstanceStatus;
use App\Enums\MeetingJoinMode;
use App\Models\Congregation;
use App\Models\MeetingInstance;
use App\Models\OnesiBox;
use App\Models\Recipient;
use App\Models\User;
use App\Notifications\MeetingUpcomingNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    // Wednesday 18:35 — 25 minutes before a 19:00 midweek meeting
    Carbon::setTestNow(Carbon::parse('2026-03-11 18:35', 'Europe/Rome'));
    Notification::fake();
});

it('creates meeting instance and attendances for upcoming meeting', function (): void {
    $congregation = Congregation::factory()->create([
        'midweek_day' => Carbon::WEDNESDAY,
        'midweek_time' => '19:00',
        'timezone' => 'Europe/Rome',
    ]);
    $recipient = Recipient::factory()->create(['congregation_id' => $congregation->id]);
    $box = OnesiBox::factory()->create([
        'recipient_id' => $recipient->id,
        'meeting_join_mode' => MeetingJoinMode::Manual,
    ]);

    $this->artisan('meetings:check-upcoming')->assertExitCode(0);

    expect(MeetingInstance::query()->count())->toBe(1);

    $instance = MeetingInstance::query()->first();
    expect($instance->congregation_id)->toBe($congregation->id);
    expect($instance->type->value)->toBe('midweek');
    expect($instance->status)->toBe(MeetingInstanceStatus::Notified);
    expect($instance->zoom_url)->toBe($congregation->zoom_url);

    expect($instance->attendances)->toHaveCount(1);
    expect($instance->attendances->first()->onesi_box_id)->toBe($box->id);
    expect($instance->attendances->first()->status)->toBe(MeetingAttendanceStatus::Pending);
});

it('is idempotent — does not create duplicates on second run', function (): void {
    $congregation = Congregation::factory()->create([
        'midweek_day' => Carbon::WEDNESDAY,
        'midweek_time' => '19:00',
        'timezone' => 'Europe/Rome',
    ]);
    $recipient = Recipient::factory()->create(['congregation_id' => $congregation->id]);
    OnesiBox::factory()->create(['recipient_id' => $recipient->id]);

    $this->artisan('meetings:check-upcoming')->assertExitCode(0);
    $this->artisan('meetings:check-upcoming')->assertExitCode(0);

    expect(MeetingInstance::query()->count())->toBe(1);
});

it('skips inactive congregations', function (): void {
    Congregation::factory()->inactive()->create([
        'midweek_day' => Carbon::WEDNESDAY,
        'midweek_time' => '19:00',
    ]);

    $this->artisan('meetings:check-upcoming')->assertExitCode(0);

    expect(MeetingInstance::query()->count())->toBe(0);
});

it('dispatches notifications to caregivers', function (): void {
    Notification::fake();

    $congregation = Congregation::factory()->create([
        'midweek_day' => Carbon::WEDNESDAY,
        'midweek_time' => '19:00',
        'timezone' => 'Europe/Rome',
    ]);
    $recipient = Recipient::factory()->create(['congregation_id' => $congregation->id]);
    $box = OnesiBox::factory()->create([
        'recipient_id' => $recipient->id,
        'meeting_notifications_enabled' => true,
    ]);
    $caregiver = User::factory()->create();
    $box->caregivers()->attach($caregiver);

    $this->artisan('meetings:check-upcoming')->assertExitCode(0);

    Notification::assertSentTo($caregiver, MeetingUpcomingNotification::class);
});

it('skips congregations with no upcoming meeting in window', function (): void {
    Congregation::factory()->create([
        'midweek_day' => Carbon::THURSDAY, // tomorrow, not within 30 min
        'midweek_time' => '19:00',
        'timezone' => 'Europe/Rome',
    ]);

    $this->artisan('meetings:check-upcoming')->assertExitCode(0);

    expect(MeetingInstance::query()->count())->toBe(0);
});
