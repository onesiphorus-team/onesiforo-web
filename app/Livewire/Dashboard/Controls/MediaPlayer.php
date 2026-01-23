<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Concerns\HandlesOnesiBoxErrors;
use App\Concerns\MediaUrlValidation;
use App\Models\OnesiBox;
use App\Services\OnesiBoxCommandServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * Abstract base class for media playback components (Audio and Video).
 *
 * This class provides common functionality for AudioPlayer and VideoPlayer,
 * eliminating code duplication for authorization, validation, and error handling.
 */
abstract class MediaPlayer extends Component
{
    use AuthorizesRequests;
    use HandlesOnesiBoxErrors;
    use MediaUrlValidation;

    public OnesiBox $onesiBox;

    /**
     * Get the media type identifier ('audio' or 'video').
     */
    abstract protected function getMediaType(): string;

    /**
     * Get the public property name holding the media URL.
     */
    abstract protected function getMediaUrlProperty(): string;

    /**
     * Get the success message to display after sending the play command.
     */
    abstract protected function getPlaySuccessMessage(): string;

    /**
     * Get the Blade view name for this component.
     *
     * @return view-string
     */
    abstract protected function getViewName(): string;

    /**
     * Play the media on the OnesiBox.
     */
    public function playMedia(OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->authorize('control', $this->onesiBox);

        $this->validate();

        $mediaUrl = $this->{$this->getMediaUrlProperty()};

        $this->executeWithErrorHandling(
            callback: fn () => $commandService->sendMediaCommand(
                $this->onesiBox,
                $mediaUrl,
                $this->getMediaType()
            ),
            successMessage: $this->getPlaySuccessMessage()
        );

        $this->reset($this->getMediaUrlProperty());
    }

    /**
     * Stop the current playback on the OnesiBox.
     */
    public function stopPlayback(OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->authorize('control', $this->onesiBox);

        $this->executeWithErrorHandling(
            callback: fn () => $commandService->sendStopCommand($this->onesiBox),
            successMessage: 'Riproduzione interrotta'
        );
    }

    public function render(): View
    {
        return view($this->getViewName());
    }

    /**
     * Get the validation rules.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return $this->mediaUrlRules($this->getMediaUrlProperty());
    }
}
