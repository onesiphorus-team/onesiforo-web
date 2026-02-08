<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Actions\Commands\CreateVolumeCommandAction;
use App\Concerns\ChecksOnesiBoxPermission;
use App\Concerns\HandlesOnesiBoxErrors;
use App\Models\OnesiBox;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Livewire component for controlling OnesiBox volume.
 *
 * Provides preset buttons and a granular slider (0-100, step 5) for volume control.
 */
class VolumeControl extends Component
{
    use AuthorizesRequests;
    use ChecksOnesiBoxPermission;
    use HandlesOnesiBoxErrors;

    #[Locked]
    public OnesiBox $onesiBox;

    /**
     * Available volume preset levels for quick-access buttons.
     *
     * @var list<int>
     */
    public array $volumeLevels = [0, 50, 60, 70, 80, 90, 100];

    /**
     * Current slider volume value, synced with Alpine.js via entangle.
     */
    public int $sliderVolume = 80;

    public function mount(): void
    {
        $this->sliderVolume = $this->roundToStep($this->onesiBox->volume ?? 80);
    }

    /**
     * Get the current volume level from the OnesiBox, rounded to the nearest step of 5.
     */
    #[Computed]
    public function currentVolume(): int
    {
        $actualVolume = $this->onesiBox->volume ?? 80;

        return $this->roundToStep($actualVolume);
    }

    /**
     * Get the nearest preset button value to the current volume.
     */
    #[Computed]
    public function nearestPreset(): int
    {
        return collect($this->volumeLevels)
            ->sortBy(fn (int $preset): int => abs($this->currentVolume - $preset))
            ->first();
    }

    /**
     * Check if the OnesiBox is online.
     */
    #[Computed]
    public function isOnline(): bool
    {
        return $this->onesiBox->isOnline();
    }

    /**
     * Set the volume level on the OnesiBox (used by preset buttons).
     */
    public function setVolume(int $level): void
    {
        $this->applyVolume($level);
    }

    /**
     * Set the volume level from the slider (used by Alpine.js with debounce).
     */
    public function setSliderVolume(int $level): void
    {
        $this->applyVolume($level);
    }

    public function render(): View
    {
        return view('livewire.dashboard.controls.volume-control');
    }

    /**
     * Apply a volume level: validate, create command, and update local state.
     */
    private function applyVolume(int $level): void
    {
        if (! $this->canControl()) {
            return;
        }

        if (! $this->isValidLevel($level)) {
            $this->addError('level', __('Il livello del volume deve essere tra :min e :max, con incrementi di :step.', [
                'min' => CreateVolumeCommandAction::MIN_LEVEL,
                'max' => CreateVolumeCommandAction::MAX_LEVEL,
                'step' => CreateVolumeCommandAction::STEP,
            ]));

            return;
        }

        try {
            $action = new CreateVolumeCommandAction;
            $action->execute($this->onesiBox, $level);

            // Optimistically update the local model so the UI reflects the change immediately
            $this->onesiBox->volume = $level;
            $this->sliderVolume = $level;
            unset($this->currentVolume, $this->nearestPreset);

            $this->dispatch('notify', [
                'message' => __('Comando volume inviato'),
                'type' => 'success',
            ]);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }
        }
    }

    /**
     * Check if a volume level is valid (0-100, multiple of 5).
     */
    private function isValidLevel(int $level): bool
    {
        return $level >= CreateVolumeCommandAction::MIN_LEVEL
            && $level <= CreateVolumeCommandAction::MAX_LEVEL
            && $level % CreateVolumeCommandAction::STEP === 0;
    }

    /**
     * Round a volume value to the nearest multiple of 5.
     */
    private function roundToStep(int $volume): int
    {
        $step = CreateVolumeCommandAction::STEP;

        return (int) (round($volume / $step) * $step);
    }
}
