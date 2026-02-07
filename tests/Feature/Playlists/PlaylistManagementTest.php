<?php

declare(strict_types=1);

use App\Enums\OnesiBoxPermission;
use App\Livewire\Dashboard\Controls\SavedPlaylists;
use App\Models\OnesiBox;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->onesiBox = OnesiBox::factory()->online()->create();
    $this->user = User::factory()->create();
    $this->onesiBox->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::Full]);
    $this->actingAs($this->user);
});

test('save playlist with name creates is_saved=true record', function (): void {
    Livewire::test(SavedPlaylists::class, ['onesiBox' => $this->onesiBox])
        ->set('playlistName', 'La mia playlist')
        ->set('videoUrls', [
            'https://www.jw.org/it/biblioteca/video/#it/mediaitems/VODBible/pub-nwtsv_I_1_VIDEO',
            'https://www.jw.org/it/biblioteca/video/#it/mediaitems/VODBible/pub-nwtsv_I_2_VIDEO',
        ])
        ->call('savePlaylist')
        ->assertHasNoErrors();

    $playlist = Playlist::query()->where('onesi_box_id', $this->onesiBox->id)->where('is_saved', true)->first();
    expect($playlist)->not->toBeNull();
    expect($playlist->name)->toBe('La mia playlist');
    expect($playlist->is_saved)->toBeTrue();
    expect($playlist->items)->toHaveCount(2);
});

test('load saved playlist returns correct items', function (): void {
    $playlist = Playlist::factory()->forOnesiBox($this->onesiBox)->saved()->create(['name' => 'Test Playlist']);
    PlaylistItem::factory()->for($playlist)->atPosition(0)->create(['media_url' => 'https://www.jw.org/video/1.mp4']);
    PlaylistItem::factory()->for($playlist)->atPosition(1)->create(['media_url' => 'https://www.jw.org/video/2.mp4']);

    Livewire::test(SavedPlaylists::class, ['onesiBox' => $this->onesiBox])
        ->call('loadPlaylist', $playlist->id)
        ->assertDispatched('load-saved-playlist', fn (string $event, array $params): bool => count($params['videoUrls']) === 2
            && $params['videoUrls'][0] === 'https://www.jw.org/video/1.mp4'
            && $params['videoUrls'][1] === 'https://www.jw.org/video/2.mp4'
        );
});

test('delete playlist removes record', function (): void {
    $playlist = Playlist::factory()->forOnesiBox($this->onesiBox)->saved()->create();
    PlaylistItem::factory()->for($playlist)->atPosition(0)->create();

    Livewire::test(SavedPlaylists::class, ['onesiBox' => $this->onesiBox])
        ->call('deletePlaylist', $playlist->id);

    expect(Playlist::query()->find($playlist->id))->toBeNull();
});

test('playlist visible to all full-permission caregivers of same OnesiBox', function (): void {
    $otherUser = User::factory()->create();
    $this->onesiBox->caregivers()->attach($otherUser, ['permission' => OnesiBoxPermission::Full]);

    Playlist::factory()->forOnesiBox($this->onesiBox)->saved()->create(['name' => 'Shared Playlist']);

    $this->actingAs($otherUser);

    Livewire::test(SavedPlaylists::class, ['onesiBox' => $this->onesiBox])
        ->assertSee('Shared Playlist');
});

test('read-only caregivers cannot save playlists', function (): void {
    $readOnlyUser = User::factory()->create();
    $this->onesiBox->caregivers()->attach($readOnlyUser, ['permission' => OnesiBoxPermission::ReadOnly]);
    $this->actingAs($readOnlyUser);

    Livewire::test(SavedPlaylists::class, ['onesiBox' => $this->onesiBox])
        ->set('playlistName', 'Test')
        ->set('videoUrls', ['https://www.jw.org/video/1.mp4'])
        ->call('savePlaylist')
        ->assertForbidden();
});

test('read-only caregivers cannot delete playlists', function (): void {
    $readOnlyUser = User::factory()->create();
    $this->onesiBox->caregivers()->attach($readOnlyUser, ['permission' => OnesiBoxPermission::ReadOnly]);

    $playlist = Playlist::factory()->forOnesiBox($this->onesiBox)->saved()->create();

    $this->actingAs($readOnlyUser);

    Livewire::test(SavedPlaylists::class, ['onesiBox' => $this->onesiBox])
        ->call('deletePlaylist', $playlist->id)
        ->assertForbidden();
});

test('playlist name is required for saved playlists', function (): void {
    Livewire::test(SavedPlaylists::class, ['onesiBox' => $this->onesiBox])
        ->set('playlistName', '')
        ->set('videoUrls', ['https://www.jw.org/video/1.mp4'])
        ->call('savePlaylist')
        ->assertHasErrors(['playlistName' => 'required']);
});

test('start session from saved playlist works correctly', function (): void {
    $playlist = Playlist::factory()->forOnesiBox($this->onesiBox)->saved()->create(['name' => 'Session Playlist']);
    PlaylistItem::factory()->for($playlist)->atPosition(0)->create(['media_url' => 'https://www.jw.org/video/1.mp4']);
    PlaylistItem::factory()->for($playlist)->atPosition(1)->create(['media_url' => 'https://www.jw.org/video/2.mp4']);

    Livewire::test(SavedPlaylists::class, ['onesiBox' => $this->onesiBox])
        ->call('loadPlaylist', $playlist->id)
        ->assertDispatched('load-saved-playlist');
});
