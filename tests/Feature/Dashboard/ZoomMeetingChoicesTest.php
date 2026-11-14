<?php

declare(strict_types=1);

use App\Enums\CommandType;
use App\Enums\MeetingType;
use App\Enums\OnesiBoxPermission;
use App\Livewire\Dashboard\Controls\QuickPlaySheet;
use App\Livewire\Dashboard\Controls\ZoomCall;
use App\Models\Congregation;
use App\Models\MeetingAttendance;
use App\Models\MeetingInstance;
use App\Models\OnesiBox;
use App\Models\Recipient;
use App\Models\User;
use App\Models\ZoomMeeting;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->congregation = Congregation::factory()->create(['name' => 'Cappelle sul Tavo']);
    $this->box = OnesiBox::factory()->online()->withCaregiver($this->user)->create([
        'recipient_id' => Recipient::factory()->create(['congregation_id' => $this->congregation->id])->id,
    ]);
});

it('offers the congregation meeting first, then the saved meetings, most recently used first', function (): void {
    ZoomMeeting::factory()->for($this->box, 'onesiBox')->create(['name' => 'Comitiva Cappelle']);
    ZoomMeeting::factory()->for($this->box, 'onesiBox')->usedAt(now()->subWeek())->create(['name' => 'Adunanza Loreto']);
    ZoomMeeting::factory()->for($this->box, 'onesiBox')->usedAt(now()->subDay())->create(['name' => 'Adunanza Pescara']);
    ZoomMeeting::factory()->create(['name' => 'Riunione di un altro box']);

    Livewire::test(ZoomCall::class, ['onesiBox' => $this->box])
        ->assertSeeInOrder(['Adunanza Cappelle sul Tavo', 'Adunanza Pescara', 'Adunanza Loreto', 'Comitiva Cappelle', 'Incolla il link della riunione'])
        ->assertDontSee('Riunione di un altro box');
});

it('joins a saved meeting with one tap, remembers when it was used and closes the sheet', function (): void {
    $meeting = ZoomMeeting::factory()->for($this->box, 'onesiBox')->create(['name' => 'Adunanza Loreto']);

    Livewire::test(ZoomCall::class, ['onesiBox' => $this->box])
        ->call('joinSavedMeeting', $meeting->id)
        ->assertDispatched('close-quick-play');

    expect($this->box->commands()->where('type', CommandType::JoinZoom)->sole()->payload)
        ->toMatchArray(['meeting_url' => $meeting->zoom_url, 'participant_name' => $this->box->recipient->full_name])
        ->and($meeting->refresh()->last_used_at)->not->toBeNull();
});

it('joins the congregation meeting and records it in the meeting history', function (): void {
    Livewire::test(ZoomCall::class, ['onesiBox' => $this->box])
        ->call('joinCongregationMeeting')
        ->assertDispatched('close-quick-play');

    expect($this->box->commands()->where('type', CommandType::JoinZoom)->sole()->payload['meeting_url'])->toBe($this->congregation->zoom_url)
        ->and(MeetingInstance::query()->sole()->type)->toBe(MeetingType::Adhoc)
        ->and(MeetingAttendance::query()->where('onesi_box_id', $this->box->id)->count())->toBe(1);
});

it('does not join a meeting saved for another box', function (): void {
    $otherMeeting = ZoomMeeting::factory()->create();

    Livewire::test(ZoomCall::class, ['onesiBox' => $this->box])
        ->call('joinSavedMeeting', $otherMeeting->id)
        ->assertNotFound();

    expect($this->box->commands()->count())->toBe(0);
});

it('saves a pasted meeting for next time when asked', function (): void {
    $zoomUrl = 'https://us05web.zoom.us/j/85099838349?pwd=abc';

    Livewire::test(ZoomCall::class, ['onesiBox' => $this->box])
        ->set('zoomUrl', $zoomUrl)
        ->set('saveMeeting', true)
        ->set('meetingName', ' Comitiva Cappelle ')
        ->call('startCall')
        ->assertHasNoErrors()
        ->assertSet('zoomUrl', '')
        ->assertSee('Comitiva Cappelle');

    expect($this->box->zoomMeetings()->sole())
        ->name->toBe('Comitiva Cappelle')
        ->zoom_url->toBe($zoomUrl);
});

it('needs a name to save a pasted meeting, and does not save it otherwise', function (): void {
    Livewire::test(ZoomCall::class, ['onesiBox' => $this->box])
        ->set('zoomUrl', 'https://us05web.zoom.us/j/85099838349?pwd=abc')
        ->set('saveMeeting', true)
        ->call('startCall')
        ->assertHasErrors(['meetingName' => 'required']);

    Livewire::test(ZoomCall::class, ['onesiBox' => $this->box])
        ->set('zoomUrl', 'https://us05web.zoom.us/j/85099838349?pwd=abc')
        ->call('startCall')
        ->assertHasNoErrors();

    expect($this->box->zoomMeetings()->count())->toBe(0)
        ->and($this->box->commands()->where('type', CommandType::JoinZoom)->count())->toBe(1);
});

it('removes a saved meeting', function (): void {
    $meeting = ZoomMeeting::factory()->for($this->box, 'onesiBox')->create(['name' => 'Gruppo Montesilvano']);

    Livewire::test(ZoomCall::class, ['onesiBox' => $this->box])
        ->call('deleteSavedMeeting', $meeting->id)
        ->assertDontSee('Gruppo Montesilvano');

    expect(ZoomMeeting::query()->count())->toBe(0);
});

it('forbids joining or managing meetings to a caregiver with read-only permission', function (string $action): void {
    $viewer = User::factory()->create();
    $this->box->caregivers()->attach($viewer, ['permission' => OnesiBoxPermission::ReadOnly->value]);
    $meeting = ZoomMeeting::factory()->for($this->box, 'onesiBox')->create();

    Livewire::actingAs($viewer)
        ->test(ZoomCall::class, ['onesiBox' => $this->box])
        ->call($action, ...($action === 'joinCongregationMeeting' ? [] : [$meeting->id]))
        ->assertForbidden();

    expect($this->box->commands()->count())->toBe(0)
        ->and(ZoomMeeting::query()->count())->toBe(1);
})->with(['joinSavedMeeting', 'joinCongregationMeeting', 'deleteSavedMeeting']);

it('closes the sheet when a tab is done', function (): void {
    Livewire::test(QuickPlaySheet::class, ['onesiBox' => $this->box])
        ->dispatch('open-quick-play', tab: 'zoom')
        ->assertSet('open', true)
        ->dispatch('close-quick-play')
        ->assertSet('open', false)
        ->assertSet('tab', null);
});
