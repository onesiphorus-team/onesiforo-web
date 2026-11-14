<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Enums\OnesiBoxStatus;
use App\Models\OnesiBox;
use Livewire\Attributes\Locked;

/**
 * Lets a Livewire component that already refreshes the box (poll, real-time events) tell
 * the rest of the page when what the command bar shows has changed: online or not, in a
 * call or not, volume.
 *
 * The other components listen to `box-state-changed` instead of polling on their own, so
 * they cost a request only when there is something new to show.
 *
 * Requires the using class to have a public `$onesiBox` property of type OnesiBox.
 *
 * @property OnesiBox $onesiBox
 */
trait AnnouncesOnesiBoxState
{
    /**
     * The state the page was last told about (null until the first render).
     */
    #[Locked]
    public ?string $announcedBoxState = null;

    /**
     * Dispatch `box-state-changed` when the state differs from the last announced one.
     *
     * Being online depends on the clock too, so the comparison is against the remembered
     * state and not against the model before its refresh.
     */
    protected function announceBoxStateChange(): void
    {
        $state = implode('|', [
            $this->onesiBox->isOnline() ? 'online' : 'offline',
            $this->onesiBox->status === OnesiBoxStatus::Calling ? 'call' : 'no-call',
            (string) ($this->onesiBox->volume ?? 80),
        ]);

        if ($this->announcedBoxState !== null && $this->announcedBoxState !== $state) {
            $this->dispatch('box-state-changed');
        }

        $this->announcedBoxState = $state;
    }
}
