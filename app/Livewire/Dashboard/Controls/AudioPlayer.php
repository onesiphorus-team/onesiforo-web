<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Services\OnesiBoxCommandServiceInterface;

/**
 * Livewire component for audio playback controls.
 */
class AudioPlayer extends MediaPlayer
{
    public string $audioUrl = '';

    /**
     * Play audio - delegates to parent playMedia method.
     */
    public function playAudio(OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->playMedia($commandService);
    }

    protected function getMediaType(): string
    {
        return 'audio';
    }

    protected function getMediaUrlProperty(): string
    {
        return 'audioUrl';
    }

    protected function getPlaySuccessMessage(): string
    {
        return 'Comando audio inviato con successo';
    }

    protected function getViewName(): string
    {
        return 'livewire.dashboard.controls.audio-player';
    }
}
