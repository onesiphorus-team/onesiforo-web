<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Actions\Playlists\CreatePlaylistAction;
use App\Concerns\ChecksOnesiBoxPermission;
use App\Models\OnesiBox;
use App\Models\Playlist;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Livewire component for managing saved playlists.
 *
 * Allows caregivers to save, load, and delete playlists for reuse.
 */
class SavedPlaylists extends Component
{
    use AuthorizesRequests;
    use ChecksOnesiBoxPermission;

    public OnesiBox $onesiBox;

    public string $playlistName = '';

    /** @var list<string> */
    public array $videoUrls = [];

    /**
     * Get saved playlists for this OnesiBox.
     *
     * @return Collection<int, Playlist>
     */
    #[Computed]
    public function savedPlaylists(): Collection
    {
        return $this->onesiBox->playlists()
            ->onlySaved()
            ->withCount('items')
            ->latest()
            ->get();
    }

    /**
     * Receive video URLs from PlaylistBuilder for saving.
     *
     * @param  list<string>  $videoUrls
     */
    #[On('playlist-updated')]
    public function updateVideoUrls(array $videoUrls): void
    {
        $this->videoUrls = $videoUrls;
    }

    /**
     * Save the current playlist with a name.
     */
    public function savePlaylist(CreatePlaylistAction $createAction): void
    {
        $this->authorize('control', $this->onesiBox);

        $this->validate([
            'playlistName' => ['required', 'string', 'max:255'],
            'videoUrls' => ['required', 'array', 'min:1', 'max:100'],
            'videoUrls.*' => ['required', 'url', 'max:2048'],
        ]);

        $videos = array_map(fn (string $url): array => ['url' => $url], $this->videoUrls);

        $createAction->execute(
            onesiBox: $this->onesiBox,
            videos: $videos,
            name: $this->playlistName,
            isSaved: true,
        );

        $this->playlistName = '';

        Flux::toast('Playlist salvata con successo');

        unset($this->savedPlaylists);
    }

    /**
     * Load a saved playlist into the PlaylistBuilder.
     */
    public function loadPlaylist(int $playlistId): void
    {
        $playlist = $this->onesiBox->playlists()
            ->onlySaved()
            ->with('items')
            ->findOrFail($playlistId);

        $urls = $playlist->items->pluck('media_url')->values()->all();

        $this->dispatch('load-saved-playlist', videoUrls: $urls);

        Flux::toast("Playlist \"{$playlist->name}\" caricata");
    }

    /**
     * Delete a saved playlist.
     */
    public function deletePlaylist(int $playlistId): void
    {
        $this->authorize('control', $this->onesiBox);

        $playlist = $this->onesiBox->playlists()
            ->onlySaved()
            ->findOrFail($playlistId);

        $playlist->items()->delete();
        $playlist->delete();

        Flux::toast('Playlist eliminata');

        unset($this->savedPlaylists);
    }

    public function render(): View
    {
        return view('livewire.dashboard.controls.saved-playlists');
    }
}
