<?php

declare(strict_types=1);

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Enums\MeetingAttendanceStatus;
use App\Enums\MeetingJoinMode;
use App\Enums\OnesiBoxStatus;
use App\Exceptions\OnesiBoxOfflineException;
use App\Models\Command;
use App\Models\MeetingAttendance;
use App\Models\MeetingInstance;
use App\Models\OnesiBox;
use App\Services\OnesiBoxCommandService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
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

function autoJoinBox(array $attributes = []): OnesiBox
{
    return OnesiBox::factory()->online()->create(['meeting_join_mode' => MeetingJoinMode::Auto, 'status' => OnesiBoxStatus::Idle, ...$attributes]);
}

/**
 * @return Collection<int, Command>
 */
function joinCommands(): Collection
{
    return Command::query()->where('type', CommandType::JoinZoom)->oldest('id')->get();
}

/**
 * Runs the command at the given time, with the box answering (or not) as it did until then.
 */
function runAutoJoinAt(string $now, ?OnesiBox $onlineBox = null): void
{
    Carbon::setTestNow(Carbon::parse($now, 'UTC'));
    $onlineBox?->update(['last_seen_at' => now()]);

    test()->artisan('meetings:auto-join')->assertExitCode(0);
}

it('joins the meeting from 15 minutes before its start, not earlier', function (string $now, int $joins): void {
    $attendance = autoJoinAttendance(autoJoinBox());

    runAutoJoinAt($now);

    expect(joinCommands())->toHaveCount($joins)
        ->and($attendance->fresh()->status)->toBe(MeetingAttendanceStatus::Pending);
})->with([
    '20 minutes before' => ['2026-03-11 18:40', 0],
    '15 minutes before' => ['2026-03-11 18:45', 1],
    '5 minutes before' => ['2026-03-11 18:55', 1],
]);

it('sends the join once and marks the attendance joined only when the box reports being in the meeting', function (): void {
    $box = autoJoinBox();
    $attendance = autoJoinAttendance($box);

    runAutoJoinAt('2026-03-11 18:45', $box);
    runAutoJoinAt('2026-03-11 18:46', $box);
    runAutoJoinAt('2026-03-11 18:47', $box);

    expect(joinCommands())->toHaveCount(1)
        ->and(joinCommands()[0]->payload['meeting_url'])->toBe($this->instance->zoom_url)
        ->and($attendance->fresh()->status)->toBe(MeetingAttendanceStatus::Pending)
        ->and($attendance->fresh()->joined_at)->toBeNull();

    joinCommands()[0]->markAsCompleted();
    $box->update(['status' => OnesiBoxStatus::Calling, 'current_meeting_id' => '123', 'current_meeting_url' => $this->instance->zoom_url]);
    runAutoJoinAt('2026-03-11 18:48', $box);

    expect(joinCommands())->toHaveCount(1)
        ->and($attendance->fresh()->status)->toBe(MeetingAttendanceStatus::Joined)
        ->and($attendance->fresh()->joined_at)->not->toBeNull();
});

it('keeps trying while the box is off and joins it as soon as it is back, even after the start', function (): void {
    Log::spy();
    $box = autoJoinBox(['last_seen_at' => Carbon::parse('2026-03-11 18:15', 'UTC')]);
    $attendance = autoJoinAttendance($box);

    runAutoJoinAt('2026-03-11 18:45');
    expect(joinCommands())->toHaveCount(0)
        ->and($attendance->fresh()->status)->toBe(MeetingAttendanceStatus::Pending);
    Log::shouldNotHaveReceived('warning');

    runAutoJoinAt('2026-03-11 19:20', $box);

    expect(joinCommands())->toHaveCount(1)
        ->and($attendance->fresh()->status)->toBe(MeetingAttendanceStatus::Pending);
});

it('sends the join again when a box switched off just before the first attempt comes back after that command expired', function (): void {
    // Off since 18:42, but still "online" for five minutes: the first join goes out at 18:45.
    $box = autoJoinBox(['last_seen_at' => Carbon::parse('2026-03-11 18:42', 'UTC')]);
    $attendance = autoJoinAttendance($box);

    runAutoJoinAt('2026-03-11 18:45');
    runAutoJoinAt('2026-03-11 18:46');
    runAutoJoinAt('2026-03-11 19:30');
    expect(joinCommands())->toHaveCount(1)
        ->and($attendance->fresh()->status)->toBe(MeetingAttendanceStatus::Pending);

    // 19:50: the box is back; the join of 18:45 expired at 19:45.
    runAutoJoinAt('2026-03-11 19:50', $box);
    runAutoJoinAt('2026-03-11 19:51', $box);

    expect(joinCommands())->toHaveCount(2)
        ->and(joinCommands()[0]->isExpired())->toBeTrue()
        ->and(joinCommands()[1]->isExpired())->toBeFalse();
});

it('does not queue a second join while one is waiting for the box, whoever sent it', function (): void {
    $box = autoJoinBox();
    autoJoinAttendance($box);

    // 18:50: a caregiver taps "Adunanza" by hand.
    Carbon::setTestNow(Carbon::parse('2026-03-11 18:50', 'UTC'));
    resolve(OnesiBoxCommandService::class)->sendZoomUrlCommand($box, $this->instance->zoom_url, 'Rosa');

    runAutoJoinAt('2026-03-11 18:51', $box);

    expect(joinCommands())->toHaveCount(1);
});

it('tries again a failed join, leaving the box a few minutes between attempts', function (): void {
    $box = autoJoinBox();
    $attendance = autoJoinAttendance($box);

    runAutoJoinAt('2026-03-11 18:45', $box);
    joinCommands()[0]->markAsFailed('E005', 'Zoom non si è aperto');

    runAutoJoinAt('2026-03-11 18:46', $box);
    runAutoJoinAt('2026-03-11 18:49', $box);
    expect(joinCommands())->toHaveCount(1);

    runAutoJoinAt('2026-03-11 18:50', $box);
    runAutoJoinAt('2026-03-11 18:51', $box);

    expect(joinCommands())->toHaveCount(2)
        ->and($attendance->fresh()->status)->toBe(MeetingAttendanceStatus::Pending);
});

it('does not send the join again once the box executed it, even if it is not in the meeting anymore', function (CommandStatus $status): void {
    $box = autoJoinBox();
    $attendance = autoJoinAttendance($box);

    runAutoJoinAt('2026-03-11 18:45', $box);
    // The box joined and someone stopped it by hand before the next run could see it in the call,
    // or someone cancelled the join: either way a person decided, no join loop.
    joinCommands()[0]->update(['status' => $status, 'executed_at' => now()]);

    runAutoJoinAt('2026-03-11 18:46', $box);
    runAutoJoinAt('2026-03-11 19:10', $box);
    runAutoJoinAt('2026-03-11 20:10', $box);

    expect(joinCommands())->toHaveCount(1)
        ->and($attendance->fresh()->status)->toBe(MeetingAttendanceStatus::Pending);
})->with([
    'executed' => [CommandStatus::Completed],
    'cancelled' => [CommandStatus::Cancelled],
]);

it('does not bring the box back into a meeting the caregiver left on purpose', function (): void {
    $box = autoJoinBox();
    $attendance = autoJoinAttendance($box);
    $attendance->update(['status' => MeetingAttendanceStatus::Completed, 'joined_at' => Carbon::parse('2026-03-11 18:46', 'UTC'), 'left_at' => Carbon::parse('2026-03-11 19:10', 'UTC')]);

    runAutoJoinAt('2026-03-11 19:11', $box);
    runAutoJoinAt('2026-03-11 19:30', $box);

    expect(joinCommands())->toHaveCount(0)
        ->and($attendance->fresh()->status)->toBe(MeetingAttendanceStatus::Completed);
});

it('ignores joins sent before the automatic join window', function (): void {
    $box = autoJoinBox();
    autoJoinAttendance($box);
    Command::factory()->for($box, 'onesiBox')->completed()->create([
        'type' => CommandType::JoinZoom,
        'payload' => ['meeting_url' => $this->instance->zoom_url],
        'created_at' => Carbon::parse('2026-03-11 18:00', 'UTC'),
    ]);

    runAutoJoinAt('2026-03-11 18:45', $box);

    expect(joinCommands())->toHaveCount(2);
});

it('stops trying 90 minutes after the start', function (): void {
    autoJoinAttendance(autoJoinBox());

    runAutoJoinAt('2026-03-11 20:35');

    expect(joinCommands())->toHaveCount(0);
});

it('does not send the join again to a box already in the meeting', function (): void {
    $attendance = autoJoinAttendance(autoJoinBox([
        'status' => OnesiBoxStatus::Calling,
        'current_meeting_id' => '123',
        'current_meeting_url' => $this->instance->zoom_url,
    ]));

    runAutoJoinAt('2026-03-11 18:55');

    expect(joinCommands())->toHaveCount(0)
        ->and($attendance->fresh()->status)->toBe(MeetingAttendanceStatus::Joined);
});

it('marks the attendance joined when the box executed the join and is in a call, whatever URL it reports', function (): void {
    $box = autoJoinBox();
    $attendance = autoJoinAttendance($box);

    runAutoJoinAt('2026-03-11 18:45', $box);
    joinCommands()[0]->markAsCompleted();
    $box->update(['status' => OnesiBoxStatus::Calling, 'current_meeting_id' => '123', 'current_meeting_url' => $this->instance->zoom_url.'#success']);

    runAutoJoinAt('2026-03-11 18:46', $box);

    expect(joinCommands())->toHaveCount(1)
        ->and($attendance->fresh()->status)->toBe(MeetingAttendanceStatus::Joined);
});

it('skips manual-mode boxes', function (): void {
    $box = OnesiBox::factory()->online()->create(['meeting_join_mode' => MeetingJoinMode::Manual]);
    MeetingAttendance::factory()->create([
        'meeting_instance_id' => $this->instance->id,
        'onesi_box_id' => $box->id,
        'join_mode' => MeetingJoinMode::Manual,
    ]);

    runAutoJoinAt('2026-03-11 18:55');

    expect(joinCommands())->toHaveCount(0);
});

it('skips already joined attendances', function (): void {
    MeetingAttendance::factory()->auto()->joined()->create([
        'meeting_instance_id' => $this->instance->id,
        'onesi_box_id' => autoJoinBox()->id,
    ]);

    runAutoJoinAt('2026-03-11 18:55');

    expect(joinCommands())->toHaveCount(0);
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
