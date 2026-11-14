<?php

declare(strict_types=1);

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Enums\OnesiBoxPermission;
use App\Enums\OnesiBoxStatus;
use App\Enums\PlaybackSessionStatus;
use App\Livewire\Dashboard\Controls\HeroCard;
use App\Models\ApplianceScreenshot;
use App\Models\Command;
use App\Models\Congregation;
use App\Models\OnesiBox;
use App\Models\PlaybackEvent;
use App\Models\PlaybackSession;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\Recipient;
use App\Models\User;
use App\Services\OnesiBoxCommandServiceInterface;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Mockery\MockInterface;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function boxPlayingAudio(User $caregiver, array $attributes = []): OnesiBox
{
    return OnesiBox::factory()->online()->withCaregiver($caregiver)->create([
        'status' => OnesiBoxStatus::Playing,
        'current_media_url' => 'https://example.com/song.mp3',
        'current_media_type' => 'audio',
        ...$attributes,
    ]);
}

it('derives what the box is doing from its state', function (string $expectedState, string $label, Closure $makeBox): void {
    Livewire::test(HeroCard::class, ['onesiBox' => $makeBox()])
        ->assertSet('state', $expectedState)
        ->assertSeeHtml("data-hero-state=\"{$expectedState}\"")
        ->assertSee($label);
})->with([
    'offline' => ['offline', 'Il box non è raggiungibile', fn () => OnesiBox::factory()->offline()->create()],
    'call' => ['call', 'Riunione Zoom in corso', fn () => OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Calling,
        'current_meeting_id' => '1234567890',
    ])],
    'media' => ['media', 'In riproduzione', fn () => OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Playing,
        'current_media_url' => 'https://example.com/song.mp3',
        'current_media_type' => 'audio',
    ])],
    'idle' => ['idle', 'Nessun contenuto in riproduzione', fn () => OnesiBox::factory()->online()->create(['status' => OnesiBoxStatus::Idle])],
]);

it('is paused only when the latest event of the current media is a pause', function (array $events, bool $isPaused): void {
    $box = boxPlayingAudio($this->user);

    foreach ($events as $event) {
        PlaybackEvent::factory()->for($box, 'onesiBox')->{$event}()->create(['media_url' => 'https://example.com/song.mp3']);
    }

    Livewire::test(HeroCard::class, ['onesiBox' => $box])
        ->assertSet('isPaused', $isPaused)
        ->assertSee($isPaused ? 'Riprendi' : 'Pausa');
})->with([
    'paused' => [['started', 'paused'], true],
    'resumed' => [['paused', 'resumed'], false],
]);

it('picks up a new state of the box when it refreshes', function (): void {
    $box = OnesiBox::factory()->online()->create(['status' => OnesiBoxStatus::Idle]);

    $card = Livewire::test(HeroCard::class, ['onesiBox' => $box])->assertSet('state', 'idle');

    $box->update(['status' => OnesiBoxStatus::Playing, 'current_media_url' => 'https://example.com/song.mp3', 'current_media_type' => 'audio']);

    $card->call('refreshBox')->assertSet('state', 'media');
});

it('shows the title of the media and the time left, estimated from the last report of the box', function (): void {
    $box = boxPlayingAudio($this->user, [
        'current_media_title' => 'Ave Maria',
        'current_media_position' => 60,
        'current_media_duration' => 180,
        'last_seen_at' => now()->subSeconds(20),
    ]);

    Livewire::test(HeroCard::class, ['onesiBox' => $box])
        ->assertSet('estimatedPosition', 80)
        ->assertSee(['Ave Maria', 'Audio', 'mancano', '01:40'])
        ->assertSeeHtml('role="progressbar"');
});

it('does not render the progress bar when position and duration are unknown', function (): void {
    Livewire::test(HeroCard::class, ['onesiBox' => boxPlayingAudio($this->user)])
        ->assertDontSeeHtml('role="progressbar"');
});

it('shows the JW.org title and thumbnail of the media being played', function (): void {
    Http::preventStrayRequests();
    Http::fake(['b.jw-cdn.org/apis/mediator/v1/media-items/I/pub-ebtv_103_VIDEO?*' => Http::response([
        'media' => [jwOrgMedia('pub-ebtv_103_VIDEO', 'Si può essere amici di Dio?')],
    ])]);
    $box = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Playing,
        'current_media_url' => 'https://www.jw.org/it/biblioteca-digitale/video/#it/mediaitems/SeriesBibleTeachings/pub-ebtv_103_VIDEO',
        'current_media_type' => 'video',
    ]);

    Livewire::test(HeroCard::class, ['onesiBox' => $box])
        ->assertSee(['Si può essere amici di Dio?', 'Video · jw.org'])
        ->assertSeeHtml('src="https://cms-imgp.jw-cdn.org/img/pub-ebtv_103_VIDEO_wss_sm.jpg"');
});

it('falls back to the host when JW.org cannot describe the media', function (): void {
    Http::preventStrayRequests();
    Http::fake(['b.jw-cdn.org/apis/mediator/v1/media-items/I/*' => Http::response(status: 503)]);
    $box = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Playing,
        'current_media_url' => 'https://www.jw.org/it/biblioteca-digitale/video/#it/mediaitems/SeriesBibleTeachings/pub-ebtv_103_VIDEO',
        'current_media_type' => 'video',
    ]);

    Livewire::test(HeroCard::class, ['onesiBox' => $box])
        ->assertSee('Video · www.jw.org')
        ->assertDontSeeHtml('data-slot="media-thumbnail"');
});

it('shows what comes next in a running playlist', function (): void {
    $box = boxPlayingAudio($this->user, ['current_media_position' => 0, 'current_media_duration' => 120, 'last_seen_at' => now()]);
    $playlist = Playlist::factory()->forOnesiBox($box)->create(['name' => 'Playlist di audio']);
    foreach (['Primo', 'Secondo', 'Terzo', 'Quarto', 'Quinto', 'Sesto'] as $position => $title) {
        PlaylistItem::factory()->for($playlist)->atPosition($position)->create(['title' => $title, 'duration_seconds' => 120]);
    }
    PlaybackSession::factory()->forOnesiBox($box)->forPlaylist($playlist)->create(['current_position' => 1, 'media_type' => 'audio']);

    Livewire::test(HeroCard::class, ['onesiBox' => $box])
        ->assertSee(['Playlist di audio · 2 di 6', 'Dopo', 'Terzo', 'Quarto', 'Quinto', 'e altri 1 contenuti'])
        ->assertDontSee('Sesto')
        ->assertSet('sessionSecondsLeft', 120 * 5);
});

it('shows the latest picture of the box screen, or why there is none', function (): void {
    $box = OnesiBox::factory()->online()->create(['screenshot_enabled' => false]);

    Livewire::test(HeroCard::class, ['onesiBox' => $box])
        ->assertSee('Immagine dello schermo non attiva per questo box.');

    ApplianceScreenshot::query()->create([
        'onesi_box_id' => $box->id,
        'captured_at' => now()->subMinute(),
        'width' => 1280,
        'height' => 720,
        'bytes' => 1000,
        'storage_path' => 'screenshots/test.webp',
    ]);

    Livewire::test(HeroCard::class, ['onesiBox' => $box->refresh()])
        ->assertSeeHtml('data-slot="screen-preview"')
        ->assertSee('1 minuto fa');
});

it('shows the next congregation meeting', function (): void {
    $congregation = Congregation::factory()->create();
    $box = OnesiBox::factory()->online()->create([
        'recipient_id' => Recipient::factory()->create(['congregation_id' => $congregation->id])->id,
    ]);

    Livewire::test(HeroCard::class, ['onesiBox' => $box])
        ->assertSee('Prossima adunanza:');
});

it('tells the caregiver when a command has not reached the box yet', function (): void {
    $box = OnesiBox::factory()->online()->create(['status' => OnesiBoxStatus::Idle]);
    Command::factory()->for($box, 'onesiBox')->create(['status' => CommandStatus::Pending]);

    Livewire::test(HeroCard::class, ['onesiBox' => $box])
        ->assertSee('Un comando sta arrivando al box');
});

it('offers one-tap shortcuts to choose what to show when the box is idle', function (): void {
    $box = OnesiBox::factory()->online()->withCaregiver($this->user)->create(['status' => OnesiBoxStatus::Idle]);

    Livewire::test(HeroCard::class, ['onesiBox' => $box])
        ->assertSeeHtml("open-quick-play', { tab: 'jw-video' }")
        ->assertSeeHtml("open-quick-play', { tab: 'jw-audio' }")
        ->assertSeeHtml("open-quick-play', { tab: 'zoom' }")
        ->assertDontSee('Avvia sessione playlist');
});

it('hides the controls from a caregiver with read-only permission', function (): void {
    $viewer = User::factory()->create();
    $box = OnesiBox::factory()->online()->withCaregiver($viewer, OnesiBoxPermission::ReadOnly)->create([
        'status' => OnesiBoxStatus::Playing,
        'current_media_url' => 'https://example.com/song.mp3',
        'current_media_type' => 'audio',
    ]);

    Livewire::actingAs($viewer)
        ->test(HeroCard::class, ['onesiBox' => $box])
        ->assertSee('In riproduzione')
        ->assertDontSee('Pausa');
});

it('sends pause, resume and leave-call commands', function (string $action, string $commandMethod, array $attributes): void {
    $box = OnesiBox::factory()->online()->withCaregiver($this->user)->create($attributes);

    $this->mock(OnesiBoxCommandServiceInterface::class, function (MockInterface $mock) use ($box, $commandMethod): void {
        $mock->shouldReceive($commandMethod)->once()->withArgs(fn (OnesiBox $target): bool => $target->is($box));
    });

    Livewire::test(HeroCard::class, ['onesiBox' => $box])->call($action);
})->with([
    'pause' => ['pause', 'sendPauseCommand', ['status' => OnesiBoxStatus::Playing, 'current_media_url' => 'https://example.com/x.mp3', 'current_media_type' => 'audio']],
    'resume' => ['resume', 'sendResumeCommand', ['status' => OnesiBoxStatus::Playing, 'current_media_url' => 'https://example.com/x.mp3', 'current_media_type' => 'audio']],
    'leave call' => ['leaveZoom', 'sendLeaveZoomCommand', ['status' => OnesiBoxStatus::Calling, 'current_meeting_id' => '123']],
]);

it('stop() stops the current media when no playlist is running', function (): void {
    $box = boxPlayingAudio($this->user);

    $this->mock(OnesiBoxCommandServiceInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('sendStopCommand')->once();
    });

    Livewire::test(HeroCard::class, ['onesiBox' => $box])->call('stop');
});

it('stop() ends a running playlist, so it does not move on to the next item', function (): void {
    $box = boxPlayingAudio($this->user);
    $session = PlaybackSession::factory()->forOnesiBox($box)->create();

    Livewire::test(HeroCard::class, ['onesiBox' => $box])
        ->assertSee('Ferma tutto')
        ->call('stop');

    expect($session->refresh()->status)->toBe(PlaybackSessionStatus::Stopped)
        ->and($box->commands()->where('type', CommandType::StopMedia)->count())->toBe(1);
});

it('forbids the commands to a user without Full permission', function (string $action): void {
    $box = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Playing,
        'current_media_url' => 'https://example.com/x.mp3',
        'current_media_type' => 'audio',
    ]);

    Livewire::test(HeroCard::class, ['onesiBox' => $box])
        ->call($action)
        ->assertForbidden();
})->with(['pause', 'resume', 'stop', 'leaveZoom']);
