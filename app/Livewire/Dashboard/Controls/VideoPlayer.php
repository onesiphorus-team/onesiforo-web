<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Services\OnesiBoxCommandServiceInterface;

/**
 * Livewire component for video playback controls.
 */
class VideoPlayer extends MediaPlayer
{
    public string $videoUrl = '';

    /**
     * Play video - delegates to parent playMedia method.
     */
    public function playVideo(OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->playMedia($commandService);
    }

    protected function getMediaType(): string
    {
        return 'video';
    }

    protected function getMediaUrlProperty(): string
    {
        return 'videoUrl';
    }

    protected function getPlaySuccessMessage(): string
    {
        return 'Comando video inviato con successo';
    }

    protected function getViewName(): string
    {
        return 'livewire.dashboard.controls.video-player';
    }
}
