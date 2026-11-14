<?php

declare(strict_types=1);

use App\Enums\MeetingAttendanceStatus;
use App\Enums\MeetingInstanceStatus;
use App\Enums\MeetingJoinMode;
use App\Enums\MeetingType;
use App\Enums\OnesiBoxPermission;
use App\Livewire\Dashboard\Controls\MeetingSchedule;
use App\Models\Congregation;
use App\Models\MeetingAttendance;
use App\Models\MeetingInstance;
use App\Models\OnesiBox;
use App\Models\Recipient;
use App\Models\User;
use App\Services\OnesiBoxCommandService;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * Whether the "Collega ora" button carries the `disabled` attribute.
 */
function joinNowIsDisabled(string $html): bool
{
    expect(preg_match('/<button[^>]*wire:click="joinNow"[^>]*>/', $html, $matches))->toBe(1);

    // The attribute, not the `disabled:` Tailwind variants in the class list
    return preg_match('/\sdisabled(="[^"]*")?(\s|>)/', (string) preg_replace('/class="[^"]*"/', '', $matches[0])) === 1;
}

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->congregation = Congregation::factory()->create();
    $this->recipient = Recipient::factory()->create(['congregation_id' => $this->congregation->id]);
    $this->box = OnesiBox::factory()->create([
        'recipient_id' => $this->recipient->id,
        'meeting_join_mode' => MeetingJoinMode::Manual,
    ]);
    $this->box->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::Full->value]);
});

it('shows next meeting info', function (): void {
    Livewire::test(MeetingSchedule::class, ['onesiBox' => $this->box])
        ->assertSee($this->congregation->name)
        ->assertSee('Manuale');
});

it('says what each join mode means and what the button switches to', function (): void {
    Livewire::test(MeetingSchedule::class, ['onesiBox' => $this->box])
        ->assertSee('Manuale: lo colleghi tu ogni volta')
        ->assertSee('Passa ad automatico')
        ->assertDontSee('Cambia')
        ->call('toggleJoinMode')
        ->assertSee("Automatico: il box si collega da solo all'ora dell'adunanza")
        ->assertSee('Passa a manuale')
        ->assertDontSee('Passa ad automatico');
});

it('offers "Collega ora" only while the box is reachable, and again as soon as it comes back', function (): void {
    $this->box->update(['last_seen_at' => now()->subHour()]);

    $schedule = Livewire::test(MeetingSchedule::class, ['onesiBox' => $this->box]);

    expect(joinNowIsDisabled($schedule->html()))->toBeTrue();

    $this->box->update(['last_seen_at' => now()]);

    // The component's own poll renders nothing when no meeting is waiting
    $schedule->call('checkPendingAttendance');
    $schedule->dispatch('box-state-changed');

    expect(joinNowIsDisabled($schedule->html()))->toBeFalse();
});

it('tells in plain words that the box is joining the meeting', function (): void {
    $this->box->update(['last_seen_at' => now()]);

    $mock = Mockery::mock(OnesiBoxCommandService::class);
    $mock->shouldReceive('sendZoomUrlCommand')->once();
    app()->instance(OnesiBoxCommandService::class, $mock);

    Livewire::test(MeetingSchedule::class, ['onesiBox' => $this->box])
        ->call('joinNow')
        ->assertDispatched('toast-show', fn (string $event, array $params): bool => $params['slots']['text'] === "Sto collegando il box all'adunanza…");
});

it('can toggle join mode', function (): void {
    Livewire::test(MeetingSchedule::class, ['onesiBox' => $this->box])
        ->call('toggleJoinMode')
        ->assertDispatched('meeting-join-mode-updated');

    expect($this->box->fresh()->meeting_join_mode)->toBe(MeetingJoinMode::Auto);
});

it('can confirm manual join', function (): void {
    $mock = Mockery::mock(OnesiBoxCommandService::class);
    $mock->shouldReceive('sendZoomUrlCommand')->once();
    app()->instance(OnesiBoxCommandService::class, $mock);

    $instance = MeetingInstance::factory()->notified()->create([
        'congregation_id' => $this->congregation->id,
    ]);
    $attendance = MeetingAttendance::factory()->create([
        'meeting_instance_id' => $instance->id,
        'onesi_box_id' => $this->box->id,
        'status' => MeetingAttendanceStatus::Pending,
    ]);

    Livewire::test(MeetingSchedule::class, ['onesiBox' => $this->box])
        ->call('confirmJoin', $attendance->id);

    expect($attendance->fresh()->status)->toBe(MeetingAttendanceStatus::Joined);
});

it('can skip next meeting', function (): void {
    $instance = MeetingInstance::factory()->notified()->create([
        'congregation_id' => $this->congregation->id,
    ]);
    $attendance = MeetingAttendance::factory()->create([
        'meeting_instance_id' => $instance->id,
        'onesi_box_id' => $this->box->id,
        'status' => MeetingAttendanceStatus::Pending,
    ]);

    Livewire::test(MeetingSchedule::class, ['onesiBox' => $this->box])
        ->call('skipMeeting', $attendance->id);

    expect($attendance->fresh()->status)->toBe(MeetingAttendanceStatus::Skipped);
});

it('shows meeting history', function (): void {
    $instance = MeetingInstance::factory()->completed()->create([
        'congregation_id' => $this->congregation->id,
    ]);
    $attendance = MeetingAttendance::factory()->completed()->create([
        'meeting_instance_id' => $instance->id,
        'onesi_box_id' => $this->box->id,
    ]);

    Livewire::test(MeetingSchedule::class, ['onesiBox' => $this->box])
        ->assertSee('Storico adunanze')
        ->assertSee($attendance->status->getLabel());
});

it('can trigger adhoc join', function (): void {
    $mock = Mockery::mock(OnesiBoxCommandService::class);
    $mock->shouldReceive('sendZoomUrlCommand')->once();
    app()->instance(OnesiBoxCommandService::class, $mock);

    Livewire::test(MeetingSchedule::class, ['onesiBox' => $this->box])
        ->call('joinNow');

    expect(MeetingInstance::query()->where('type', 'adhoc')->count())->toBe(1);
    expect(MeetingAttendance::query()->count())->toBe(1);
});

it('joinNow is idempotent when an in-progress session already exists', function (): void {
    $mock = Mockery::mock(OnesiBoxCommandService::class);
    $mock->shouldReceive('sendZoomUrlCommand')->twice();
    app()->instance(OnesiBoxCommandService::class, $mock);

    $component = Livewire::test(MeetingSchedule::class, ['onesiBox' => $this->box]);

    $component->call('joinNow');
    $component->call('joinNow');

    expect(MeetingInstance::query()->count())->toBe(1);
    expect(MeetingAttendance::query()->count())->toBe(1);
});

it('denies toggleJoinMode to a non-caregiver user', function (): void {
    $otherUser = User::factory()->create();

    Livewire::actingAs($otherUser)
        ->test(MeetingSchedule::class, ['onesiBox' => $this->box])
        ->assertForbidden();
});

describe('meeting actions require full control permission', function (): void {
    beforeEach(function (): void {
        $this->fullCaregiver = User::factory()->create();
        $this->box->caregivers()->attach($this->fullCaregiver, ['permission' => OnesiBoxPermission::Full->value]);

        $this->readOnlyCaregiver = User::factory()->create();
        $this->box->caregivers()->attach($this->readOnlyCaregiver, ['permission' => OnesiBoxPermission::ReadOnly->value]);

        $this->instance = MeetingInstance::factory()->notified()->create([
            'congregation_id' => $this->congregation->id,
        ]);
        $this->attendance = MeetingAttendance::factory()->create([
            'meeting_instance_id' => $this->instance->id,
            'onesi_box_id' => $this->box->id,
            'status' => MeetingAttendanceStatus::Pending,
        ]);
    });

    it('still lets a read-only caregiver see the schedule', function (): void {
        Livewire::actingAs($this->readOnlyCaregiver)
            ->test(MeetingSchedule::class, ['onesiBox' => $this->box])
            ->assertOk()
            ->assertSee($this->congregation->name)
            ->assertSee('Prossima adunanza')
            ->assertSee('Storico adunanze');
    });

    it('denies toggleJoinMode to a read-only caregiver', function (): void {
        Livewire::actingAs($this->readOnlyCaregiver)
            ->test(MeetingSchedule::class, ['onesiBox' => $this->box])
            ->call('toggleJoinMode')
            ->assertForbidden();

        expect($this->box->fresh()->meeting_join_mode)->toBe(MeetingJoinMode::Manual);
    });

    it('allows toggleJoinMode to a full permission caregiver', function (): void {
        Livewire::actingAs($this->fullCaregiver)
            ->test(MeetingSchedule::class, ['onesiBox' => $this->box])
            ->call('toggleJoinMode')
            ->assertOk();

        expect($this->box->fresh()->meeting_join_mode)->toBe(MeetingJoinMode::Auto);
    });

    it('denies confirmJoin to a read-only caregiver', function (): void {
        $mock = Mockery::mock(OnesiBoxCommandService::class);
        $mock->shouldNotReceive('sendZoomUrlCommand');
        app()->instance(OnesiBoxCommandService::class, $mock);

        Livewire::actingAs($this->readOnlyCaregiver)
            ->test(MeetingSchedule::class, ['onesiBox' => $this->box])
            ->call('confirmJoin', $this->attendance->id)
            ->assertForbidden();

        expect($this->attendance->fresh()->status)->toBe(MeetingAttendanceStatus::Pending);
    });

    it('allows confirmJoin to a full permission caregiver', function (): void {
        $mock = Mockery::mock(OnesiBoxCommandService::class);
        $mock->shouldReceive('sendZoomUrlCommand')->once();
        app()->instance(OnesiBoxCommandService::class, $mock);

        Livewire::actingAs($this->fullCaregiver)
            ->test(MeetingSchedule::class, ['onesiBox' => $this->box])
            ->call('confirmJoin', $this->attendance->id)
            ->assertOk();

        expect($this->attendance->fresh()->status)->toBe(MeetingAttendanceStatus::Joined);
    });

    it('denies skipMeeting to a read-only caregiver', function (): void {
        Livewire::actingAs($this->readOnlyCaregiver)
            ->test(MeetingSchedule::class, ['onesiBox' => $this->box])
            ->call('skipMeeting', $this->attendance->id)
            ->assertForbidden();

        expect($this->attendance->fresh()->status)->toBe(MeetingAttendanceStatus::Pending);
    });

    it('allows skipMeeting to a full permission caregiver', function (): void {
        Livewire::actingAs($this->fullCaregiver)
            ->test(MeetingSchedule::class, ['onesiBox' => $this->box])
            ->call('skipMeeting', $this->attendance->id)
            ->assertOk();

        expect($this->attendance->fresh()->status)->toBe(MeetingAttendanceStatus::Skipped);
    });

    it('denies joinNow to a read-only caregiver', function (): void {
        $mock = Mockery::mock(OnesiBoxCommandService::class);
        $mock->shouldNotReceive('sendZoomUrlCommand');
        app()->instance(OnesiBoxCommandService::class, $mock);

        Livewire::actingAs($this->readOnlyCaregiver)
            ->test(MeetingSchedule::class, ['onesiBox' => $this->box])
            ->call('joinNow')
            ->assertForbidden();

        expect(MeetingInstance::query()->where('type', MeetingType::Adhoc)->exists())->toBeFalse();
        expect(MeetingAttendance::query()->count())->toBe(1);
    });

    it('allows joinNow to a full permission caregiver', function (): void {
        $mock = Mockery::mock(OnesiBoxCommandService::class);
        $mock->shouldReceive('sendZoomUrlCommand')->once();
        app()->instance(OnesiBoxCommandService::class, $mock);

        Livewire::actingAs($this->fullCaregiver)
            ->test(MeetingSchedule::class, ['onesiBox' => $this->box])
            ->call('joinNow')
            ->assertOk();

        expect(MeetingInstance::query()->where('type', MeetingType::Adhoc)->count())->toBe(1);
        expect(MeetingInstance::query()->where('type', MeetingType::Adhoc)->first()->status)
            ->toBe(MeetingInstanceStatus::InProgress);
    });

    it('hides the action buttons from a read-only caregiver', function (): void {
        Livewire::actingAs($this->readOnlyCaregiver)
            ->test(MeetingSchedule::class, ['onesiBox' => $this->box])
            ->assertDontSee('Collega ora')
            ->assertDontSee('wire:click="toggleJoinMode"', escape: false)
            ->assertDontSee('Conferma')
            ->assertDontSee('Salta');
    });

    it('shows the action buttons to a full permission caregiver', function (): void {
        Livewire::actingAs($this->fullCaregiver)
            ->test(MeetingSchedule::class, ['onesiBox' => $this->box])
            ->assertSee('Collega ora')
            ->assertSee('wire:click="toggleJoinMode"', escape: false)
            ->assertSee('Conferma')
            ->assertSee('Salta');
    });
});

it('shows the meeting to confirm as soon as it is announced, without reloading the page', function (): void {
    $schedule = Livewire::test(MeetingSchedule::class, ['onesiBox' => $this->box])
        ->assertDontSee('in arrivo');

    $instance = MeetingInstance::factory()->notified()->create(['congregation_id' => $this->congregation->id]);
    MeetingAttendance::factory()->create([
        'meeting_instance_id' => $instance->id,
        'onesi_box_id' => $this->box->id,
        'status' => MeetingAttendanceStatus::Pending,
    ]);

    $schedule->call('checkPendingAttendance')
        ->assertSee('in arrivo')
        ->assertSee('Conferma');
});

it('hides the meeting to confirm once someone else has answered', function (): void {
    $instance = MeetingInstance::factory()->notified()->create(['congregation_id' => $this->congregation->id]);
    $attendance = MeetingAttendance::factory()->create([
        'meeting_instance_id' => $instance->id,
        'onesi_box_id' => $this->box->id,
        'status' => MeetingAttendanceStatus::Pending,
    ]);

    $schedule = Livewire::test(MeetingSchedule::class, ['onesiBox' => $this->box])->assertSee('in arrivo');

    $attendance->update(['status' => MeetingAttendanceStatus::Skipped]);

    $schedule->call('checkPendingAttendance')->assertDontSee('in arrivo');
});

it('checks for a meeting to confirm with a handful of queries and no re-render when nothing changed', function (): void {
    $schedule = Livewire::test(MeetingSchedule::class, ['onesiBox' => $this->box]);

    DB::enableQueryLog();
    $schedule->call('checkPendingAttendance');

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(4)
        ->and($schedule->effects)->not->toHaveKey('html');
});

it('polls for a meeting to confirm while it is on screen', function (): void {
    Livewire::test(MeetingSchedule::class, ['onesiBox' => $this->box])
        ->assertSeeHtml('wire:poll.30s.visible="checkPendingAttendance"');
});

it('shows the meeting history in the display timezone, not in UTC', function (): void {
    config(['app.display_timezone' => 'Europe/Rome']);

    $instance = MeetingInstance::factory()->completed()->create([
        'congregation_id' => $this->congregation->id,
        'scheduled_at' => '2026-07-01 17:30:00',
    ]);
    MeetingAttendance::factory()->completed()->create([
        'meeting_instance_id' => $instance->id,
        'onesi_box_id' => $this->box->id,
    ]);

    Livewire::test(MeetingSchedule::class, ['onesiBox' => $this->box])
        ->assertSee('01/07/2026 19:30')
        ->assertDontSee('01/07/2026 17:30');
});
