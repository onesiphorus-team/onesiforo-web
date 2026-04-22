<?php

declare(strict_types=1);

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Enums\PlaybackEventType;
use App\Livewire\Dashboard\Controls\StreamPlayer;
use App\Models\Command;
use App\Models\OnesiBox;
use App\Models\PlaybackEvent;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->box = OnesiBox::factory()->create();
    $this->box->caregivers()->attach($this->user, ['permission' => \App\Enums\OnesiBoxPermission::Full]);
});

it('mounts with clean state when no recent stream item commands', function () {
    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->assertSet('url', '')
        ->assertSet('lastOrdinalSent', null)
        ->assertSet('errorCode', null)
        ->assertSet('reachedEnd', false);
});

it('restores url and lastOrdinalSent from latest play_stream_item command in last 6 hours', function () {
    Command::query()->create([
        'onesi_box_id' => $this->box->id,
        'type' => CommandType::PlayStreamItem,
        'payload' => ['url' => 'https://stream.jw.org/6311-4713-5379-2156', 'ordinal' => 3],
        'priority' => 2,
        'status' => CommandStatus::Pending,
        'created_at' => now()->subHour(),
    ]);

    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->assertSet('url', 'https://stream.jw.org/6311-4713-5379-2156')
        ->assertSet('lastOrdinalSent', 3)
        ->assertSet('errorCode', null)
        ->assertSet('reachedEnd', false);
});

it('ignores stream item commands older than 6 hours', function () {
    Command::query()->create([
        'onesi_box_id' => $this->box->id,
        'type' => CommandType::PlayStreamItem,
        'payload' => ['url' => 'https://stream.jw.org/x', 'ordinal' => 2],
        'priority' => 2,
        'status' => CommandStatus::Pending,
        'created_at' => now()->subHours(10),
    ]);

    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->assertSet('url', '')
        ->assertSet('lastOrdinalSent', null);
});

it('restores reachedEnd true if latest error event has code E112', function () {
    $url = 'https://stream.jw.org/6311-4713-5379-2156';

    $command = Command::query()->create([
        'onesi_box_id' => $this->box->id,
        'type' => CommandType::PlayStreamItem,
        'payload' => ['url' => $url, 'ordinal' => 4],
        'priority' => 2,
        'status' => CommandStatus::Pending,
        'created_at' => now()->subHour(),
    ]);

    PlaybackEvent::query()->create([
        'onesi_box_id' => $this->box->id,
        'event' => PlaybackEventType::Error,
        'media_url' => $url,
        'media_type' => 'video',
        'error_code' => 'E112',
        'error_message' => 'Ordinal 5 exceeds playlist length 4',
        'created_at' => $command->created_at->addMinute(),
    ]);

    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->assertSet('reachedEnd', true)
        ->assertSet('errorCode', 'E112');
});

it('restores errorCode from latest error event (E110/E111/E113) without setting reachedEnd', function () {
    $url = 'https://stream.jw.org/6311-4713-5379-2156';

    $command = Command::query()->create([
        'onesi_box_id' => $this->box->id,
        'type' => CommandType::PlayStreamItem,
        'payload' => ['url' => $url, 'ordinal' => 1],
        'priority' => 2,
        'status' => CommandStatus::Pending,
        'created_at' => now()->subHour(),
    ]);

    PlaybackEvent::query()->create([
        'onesi_box_id' => $this->box->id,
        'event' => PlaybackEventType::Error,
        'media_url' => $url,
        'media_type' => 'video',
        'error_code' => 'E111',
        'error_message' => 'No tiles found',
        'created_at' => $command->created_at->addMinute(),
    ]);

    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->assertSet('errorCode', 'E111')
        ->assertSet('reachedEnd', false);
});

it('playFromStart validates empty url and shows error', function () {
    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->set('url', '')
        ->call('playFromStart')
        ->assertHasErrors(['url']);
});

it('playFromStart rejects non-stream.jw.org URL', function () {
    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->set('url', 'https://www.jw.org/mediaitems/x')
        ->call('playFromStart')
        ->assertHasErrors(['url']);
});

it('playFromStart calls sendStreamItemCommand with ordinal 1', function () {
    $this->box->update(['last_seen_at' => now()]);

    $service = $this->mock(\App\Services\OnesiBoxCommandServiceInterface::class);
    $service->shouldReceive('sendStreamItemCommand')
        ->once()
        ->with(
            \Mockery::on(fn ($box) => $box->id === $this->box->id),
            'https://stream.jw.org/6311-4713-5379-2156',
            1
        );

    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->set('url', 'https://stream.jw.org/6311-4713-5379-2156')
        ->call('playFromStart')
        ->assertSet('lastOrdinalSent', 1)
        ->assertSet('reachedEnd', false)
        ->assertSet('errorCode', null);
});

it('next increments ordinal and calls service', function () {
    $this->box->update(['last_seen_at' => now()]);

    $service = $this->mock(\App\Services\OnesiBoxCommandServiceInterface::class);
    $service->shouldReceive('sendStreamItemCommand')
        ->once()
        ->with(\Mockery::any(), 'https://stream.jw.org/x', 3);

    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->set('url', 'https://stream.jw.org/x')
        ->set('lastOrdinalSent', 2)
        ->set('errorCode', 'E113')
        ->call('next')
        ->assertSet('lastOrdinalSent', 3)
        ->assertSet('errorCode', null);
});

it('next does nothing when reachedEnd is true', function () {
    $service = $this->mock(\App\Services\OnesiBoxCommandServiceInterface::class);
    $service->shouldNotReceive('sendStreamItemCommand');

    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->set('url', 'https://stream.jw.org/x')
        ->set('lastOrdinalSent', 4)
        ->set('reachedEnd', true)
        ->call('next')
        ->assertSet('lastOrdinalSent', 4);
});

it('previous decrements ordinal and resets reachedEnd', function () {
    $this->box->update(['last_seen_at' => now()]);

    $service = $this->mock(\App\Services\OnesiBoxCommandServiceInterface::class);
    $service->shouldReceive('sendStreamItemCommand')
        ->once()
        ->with(\Mockery::any(), 'https://stream.jw.org/x', 2);

    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->set('url', 'https://stream.jw.org/x')
        ->set('lastOrdinalSent', 3)
        ->set('reachedEnd', true)
        ->call('previous')
        ->assertSet('lastOrdinalSent', 2)
        ->assertSet('reachedEnd', false);
});

it('previous does nothing when lastOrdinalSent is 1', function () {
    $service = $this->mock(\App\Services\OnesiBoxCommandServiceInterface::class);
    $service->shouldNotReceive('sendStreamItemCommand');

    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->set('url', 'https://stream.jw.org/x')
        ->set('lastOrdinalSent', 1)
        ->call('previous')
        ->assertSet('lastOrdinalSent', 1);
});

it('stop calls sendStopCommand', function () {
    $this->box->update(['last_seen_at' => now()]);

    $service = $this->mock(\App\Services\OnesiBoxCommandServiceInterface::class);
    $service->shouldReceive('sendStopCommand')
        ->once()
        ->with(\Mockery::on(fn ($box) => $box->id === $this->box->id));

    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->call('stop');
});

it('handlePlaybackEvent sets reachedEnd true when error code is E112', function () {
    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->set('url', 'https://stream.jw.org/x')
        ->call('handlePlaybackEvent', [
            'event' => 'error',
            'media_url' => 'https://stream.jw.org/x',
            'media_type' => 'video',
            'error_code' => 'E112',
            'error_message' => 'Ordinal 5 exceeds playlist length 4',
        ])
        ->assertSet('errorCode', 'E112')
        ->assertSet('reachedEnd', true);
});

it('handlePlaybackEvent sets errorCode for E110/E111/E113 but not reachedEnd', function () {
    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->set('url', 'https://stream.jw.org/x')
        ->call('handlePlaybackEvent', [
            'event' => 'error',
            'media_url' => 'https://stream.jw.org/x',
            'media_type' => 'video',
            'error_code' => 'E110',
            'error_message' => 'DNS timeout',
        ])
        ->assertSet('errorCode', 'E110')
        ->assertSet('reachedEnd', false);
});

it('handlePlaybackEvent ignores events with different media_url', function () {
    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->set('url', 'https://stream.jw.org/current')
        ->set('errorCode', null)
        ->call('handlePlaybackEvent', [
            'event' => 'error',
            'media_url' => 'https://stream.jw.org/OTHER',
            'media_type' => 'video',
            'error_code' => 'E112',
        ])
        ->assertSet('errorCode', null)
        ->assertSet('reachedEnd', false);
});

it('handlePlaybackEvent does nothing on non-error events', function () {
    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->set('url', 'https://stream.jw.org/x')
        ->set('errorCode', 'E110')
        ->call('handlePlaybackEvent', [
            'event' => 'started',
            'media_url' => 'https://stream.jw.org/x',
            'media_type' => 'video',
        ])
        ->assertSet('errorCode', 'E110');
});

it('dismissError clears errorCode', function () {
    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->set('errorCode', 'E110')
        ->call('dismissError')
        ->assertSet('errorCode', null);
});

it('renders the form with stream.jw.org placeholder', function () {
    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->assertSee('stream.jw.org')
        ->assertSee('Avvia playlist');
});

it('renders the Precedente/Successivo/Stop controls when lastOrdinalSent is set', function () {
    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->set('lastOrdinalSent', 2)
        ->assertSee('Precedente')
        ->assertSee('Successivo')
        ->assertSee('Stop');
});

it('renders the error banner with E112 message when reachedEnd', function () {
    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->set('errorCode', 'E112')
        ->set('reachedEnd', true)
        ->assertSee('Ultimo video della playlist raggiunto');
});
