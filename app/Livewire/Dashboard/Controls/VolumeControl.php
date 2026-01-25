<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Actions\Commands\CreateVolumeCommandAction;
use App\Concerns\HandlesOnesiBoxErrors;
use App\Enums\OnesiBoxPermission;
use App\Models\OnesiBox;
use App\Models\User;
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
     * Get the current volume level from the OnesiBox.
     */
    #[Computed]
    public function currentVolume(): int
    {
        return $this->onesiBox->volume ?? 80;
    }

    /**
     * Check if the current user can control this OnesiBox.
     */
    #[Computed]
    public function canControl(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        $pivot = $this->onesiBox->caregivers()
            ->where('user_id', $user->id)
            ->first()
            ?->pivot;

        if ($pivot === null) {
            return false;
        }

        /** @var OnesiBoxPermission|null $permission */
        $permission = $pivot->getAttribute('permission');

        return $permission === OnesiBoxPermission::Full;
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
}
