<?php

declare(strict_types=1);

use App\Enums\OnesiBoxPermission;
use App\Livewire\Dashboard\Controls\AudioPlayer;
use App\Livewire\Dashboard\Controls\JwMediaBrowser;
use App\Livewire\Dashboard\Controls\QuickPlaySheet;
use App\Livewire\Dashboard\Controls\RandomSessionStarter;
use App\Livewire\Dashboard\Controls\StreamPlayer;
use App\Livewire\Dashboard\Controls\VideoPlayer;
use App\Livewire\Dashboard\Controls\ZoomCall;
use App\Models\OnesiBox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('starts closed with no active tab', function (): void {
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::test(QuickPlaySheet::class, ['onesiBox' => $box])
        ->assertSet('open', false)
        ->assertSet('tab', null);
});

it('opens and shows the initial menu when receiving open-quick-play', function (): void {
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::test(QuickPlaySheet::class, ['onesiBox' => $box])
        ->dispatch('open-quick-play')
        ->assertSet('open', true)
        ->assertSet('tab', null)
        ->assertSee('Cosa vuoi mostrare sul box?');
});

it('preselects a tab when open-quick-play carries a tab parameter', function (): void {
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::test(QuickPlaySheet::class, ['onesiBox' => $box])
        ->dispatch('open-quick-play', tab: 'zoom')
        ->assertSet('open', true)
        ->assertSet('tab', 'zoom');
});

it('close() resets state', function (): void {
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::test(QuickPlaySheet::class, ['onesiBox' => $box])
        ->set('open', true)
        ->set('tab', 'audio')
        ->call('close')
        ->assertSet('open', false)
        ->assertSet('tab', null);
});

it('mounts AudioPlayer inside the sheet when tab=audio', function (): void {
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::test(QuickPlaySheet::class, ['onesiBox' => $box])
        ->dispatch('open-quick-play', tab: 'audio')
        ->assertSeeLivewire(AudioPlayer::class);
});

it('mounts VideoPlayer when tab=video', function (): void {
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::test(QuickPlaySheet::class, ['onesiBox' => $box])
        ->dispatch('open-quick-play', tab: 'video')
        ->assertSeeLivewire(VideoPlayer::class);
});

it('mounts StreamPlayer when tab=stream', function (): void {
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::test(QuickPlaySheet::class, ['onesiBox' => $box])
        ->dispatch('open-quick-play', tab: 'stream')
        ->assertSeeLivewire(StreamPlayer::class);
});

it('mounts ZoomCall when tab=zoom', function (): void {
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::test(QuickPlaySheet::class, ['onesiBox' => $box])
        ->dispatch('open-quick-play', tab: 'zoom')
        ->assertSeeLivewire(ZoomCall::class);
});

it('no longer offers the playlist builder tabs', function (string $tab): void {
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::test(QuickPlaySheet::class, ['onesiBox' => $box])
        ->dispatch('open-quick-play', tab: $tab)
        ->assertSet('tab', null)
        ->call('selectTab', $tab)
        ->assertSet('tab', null)
        ->assertDontSee(['Sessione playlist', 'Dalle playlist salvate']);
})->with(['session', 'playlists']);

it('back() clears the active tab', function (): void {
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::test(QuickPlaySheet::class, ['onesiBox' => $box])
        ->set('open', true)
        ->set('tab', 'audio')
        ->call('back')
        ->assertSet('tab', null)
        ->assertSet('open', true);
});

it('selectTab() ignores invalid tab names', function (): void {
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::test(QuickPlaySheet::class, ['onesiBox' => $box])
        ->call('selectTab', 'bogus')
        ->assertSet('tab', null);
});

it('openSheet() is forbidden for a user without Full permission', function (): void {
    $box = OnesiBox::factory()->online()->create();
    // No caregiver attach — user has no permission

    Livewire::actingAs($this->user)
        ->test(QuickPlaySheet::class, ['onesiBox' => $box])
        ->dispatch('open-quick-play')
        ->assertForbidden();
});

it('shows the JW.org shortcuts first and keeps the other options under Altro', function (): void {
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::test(QuickPlaySheet::class, ['onesiBox' => $box])
        ->dispatch('open-quick-play')
        ->assertSeeInOrder(['Video', 'Audio', 'Zoom e adunanze', 'Video casuali', 'Audio casuali', 'Altro', 'Video da un link', 'Audio da un link', 'Programma di JW Stream'])
        ->assertDontSee('Avvia chiamata Zoom');
});

it('mounts the JW.org components for the new tabs', function (string $tab, string $component): void {
    fakeJwOrgCategories([
        'VideoOnDemand' => ['key' => 'VideoOnDemand', 'name' => 'Categorie', 'type' => 'container', 'subcategories' => []],
        'Audio' => ['key' => 'Audio', 'name' => 'Audio', 'type' => 'container', 'subcategories' => []],
    ]);
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::test(QuickPlaySheet::class, ['onesiBox' => $box])
        ->dispatch('open-quick-play', tab: $tab)
        ->assertSeeLivewire($component);
})->with([
    'video browser' => ['jw-video', JwMediaBrowser::class],
    'audio browser' => ['jw-audio', JwMediaBrowser::class],
    'random video' => ['random-video', RandomSessionStarter::class],
    'random audio' => ['random-audio', RandomSessionStarter::class],
]);
