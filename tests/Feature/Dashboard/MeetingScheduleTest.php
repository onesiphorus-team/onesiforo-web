<?php

declare(strict_types=1);

use App\Enums\MeetingAttendanceStatus;
use App\Enums\MeetingJoinMode;
use App\Livewire\Dashboard\Controls\MeetingSchedule;
use App\Models\Congregation;
use App\Models\MeetingAttendance;
use App\Models\MeetingInstance;
use App\Models\OnesiBox;
use App\Models\Recipient;
use App\Models\User;
use App\Services\OnesiBoxCommandService;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->congregation = Congregation::factory()->create();
    $this->recipient = Recipient::factory()->create(['congregation_id' => $this->congregation->id]);
    $this->box = OnesiBox::factory()->create([
        'recipient_id' => $this->recipient->id,
        'meeting_join_mode' => MeetingJoinMode::Manual,
    ]);
    $this->box->caregivers()->attach($this->user);
});

it('shows next meeting info', function (): void {
    Livewire::test(MeetingSchedule::class, ['onesiBox' => $this->box])
        ->assertSee($this->congregation->name)
        ->assertSee('Manuale');
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
