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
