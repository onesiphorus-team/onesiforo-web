<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Actions\Playlists\ExtractJwOrgVideosAction;
use App\Concerns\ChecksOnesiBoxPermission;
use App\Models\OnesiBox;
use App\Rules\JwOrgSectionUrl;
use App\Rules\JwOrgUrl;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Component;
use RuntimeException;

/**
 * Livewire component for building a playlist of video URLs.
 *
 * Supports two modes: manual URL input and JW.org section extraction.
 */
class PlaylistBuilder extends Component
{
    use ChecksOnesiBoxPermission;

    public OnesiBox $onesiBox;

    public string $sourceType = 'manual';

    public string $newUrl = '';

    public string $sectionUrl = '';

    /** @var array<int, string> */
    public array $videoUrls = [];

    /**
     * Extracted videos from JW.org section.
     *
     * @var list<array{title: string, url: string, duration_seconds: int, duration_formatted: string}>
     */
    public array $extractedVideos = [];

    public string $extractedCategoryName = '';

    public string $extractedTotalDuration = '';

    public bool $isExtracting = false;

    /**
     * Load video URLs from a saved playlist.
     *
     * @param  list<string>  $videoUrls
     */
    #[On('load-saved-playlist')]
    public function loadSavedPlaylist(array $videoUrls): void
    {
        $this->sourceType = 'manual';
        $this->videoUrls = $videoUrls;
        $this->extractedVideos = [];
        $this->extractedCategoryName = '';
        $this->extractedTotalDuration = '';
        $this->dispatch('playlist-updated', videoUrls: $this->videoUrls);
    }

    /**
     * Switch between manual and JW.org section source types.
     */
    public function switchSourceType(string $type): void
    {
        $this->sourceType = $type;
        $this->clearAll();
        $this->extractedVideos = [];
        $this->extractedCategoryName = '';
        $this->extractedTotalDuration = '';
    }

    /**
     * Add a URL to the playlist (manual mode).
     */
    public function addUrl(): void
    {
        $this->validate([
            'newUrl' => ['required', 'url', 'max:2048', new JwOrgUrl],
        ]);

        $this->videoUrls[] = $this->newUrl;
        $this->newUrl = '';
        $this->dispatch('playlist-updated', videoUrls: $this->videoUrls);
    }

    /**
     * Remove a URL from the playlist by index.
     */
    public function removeUrl(int $index): void
    {
        if (isset($this->videoUrls[$index])) {
            unset($this->videoUrls[$index]);
            $this->videoUrls = array_values($this->videoUrls);
            $this->dispatch('playlist-updated', videoUrls: $this->videoUrls);
        }
    }

    /**
     * Move a URL up in the list.
     */
    public function moveUp(int $index): void
    {
        if ($index > 0 && isset($this->videoUrls[$index])) {
            [$this->videoUrls[$index - 1], $this->videoUrls[$index]] =
                [$this->videoUrls[$index], $this->videoUrls[$index - 1]];
            $this->dispatch('playlist-updated', videoUrls: $this->videoUrls);
        }
    }

    /**
     * Move a URL down in the list.
     */
    public function moveDown(int $index): void
    {
        if ($index < count($this->videoUrls) - 1 && isset($this->videoUrls[$index])) {
            [$this->videoUrls[$index], $this->videoUrls[$index + 1]] =
                [$this->videoUrls[$index + 1], $this->videoUrls[$index]];
            $this->dispatch('playlist-updated', videoUrls: $this->videoUrls);
        }
    }

    /**
     * Clear all URLs and extracted data.
     */
    public function clearAll(): void
    {
        $this->videoUrls = [];
        $this->dispatch('playlist-updated', videoUrls: $this->videoUrls);
    }

    /**
     * Extract videos from a JW.org section URL.
     */
    public function extractFromJwOrg(ExtractJwOrgVideosAction $extractAction): void
    {
        $this->validate([
            'sectionUrl' => ['required', 'url', 'max:2048', new JwOrgSectionUrl],
        ]);

        $this->isExtracting = true;

        try {
            $result = $extractAction->execute($this->sectionUrl);

            $this->extractedVideos = $result['videos'];
            $this->extractedCategoryName = $result['category_name'];
            $this->extractedTotalDuration = $result['total_duration_formatted'];

            $this->videoUrls = array_map(
                fn (array $video): string => $video['url'],
                $result['videos']
            );

            $this->dispatch('playlist-updated', videoUrls: $this->videoUrls);

            Flux::toast("Estratti {$result['total_count']} video dalla sezione \"{$result['category_name']}\"");
        } catch (RuntimeException $e) {
            Flux::toast('Errore durante l\'estrazione dei video: '.$e->getMessage(), variant: 'danger');
        } catch (InvalidArgumentException $e) {
            Flux::toast('URL non valido: '.$e->getMessage(), variant: 'danger');
        } finally {
            $this->isExtracting = false;
        }
    }

    public function render(): View
    {
        return view('livewire.dashboard.controls.playlist-builder');
    }
}
