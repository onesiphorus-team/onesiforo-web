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
 * Provides 5 preset volume levels: 20%, 40%, 60%, 80%, 100%.
 */
class VolumeControl extends Component
{
    use AuthorizesRequests;
    use ChecksOnesiBoxPermission;
    use HandlesOnesiBoxErrors;

    #[Locked]
    public OnesiBox $onesiBox;

    /**
     * Available volume levels.
     *
     * @var list<int>
     */
    public array $volumeLevels = [20, 40, 60, 80, 100];

    /**
     * Get the current volume level from the OnesiBox, rounded to the nearest preset.
     */
    #[Computed]
    public function currentVolume(): int
    {
        $actualVolume = $this->onesiBox->volume ?? 80;

        return $this->findNearestPreset($actualVolume);
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
     * Set the volume level on the OnesiBox.
     */
    public function setVolume(int $level): void
    {
        // Check permission first
        if (! $this->canControl()) {
            return;
        }

        // Validate level
        if (! in_array($level, $this->volumeLevels, true)) {
            $this->addError('level', __('Il livello del volume deve essere uno di: :levels.', [
                'levels' => implode(', ', $this->volumeLevels),
            ]));

            return;
        }

        try {
            $action = new CreateVolumeCommandAction;
            $action->execute($this->onesiBox, $level);

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

    public function render(): View
    {
        return view('livewire.dashboard.controls.volume-control');
    }

    /**
     * Find the nearest preset value for a given volume.
     */
    private function findNearestPreset(int $volume): int
    {
        return collect($this->volumeLevels)
            ->sortBy(fn (int $preset): int => abs($volume - $preset))
            ->first();
    }
}
