<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Concerns\ChecksOnesiBoxPermission;
use App\Enums\OnesiBoxPermission;
use App\Enums\Roles;
use App\Models\OnesiBox;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Dettaglio OnesiBox')]
class OnesiBoxDetail extends Component
{
    use AuthorizesRequests;
    use ChecksOnesiBoxPermission;

    public OnesiBox $onesiBox;

    public function mount(OnesiBox $onesiBox): void
    {
        $this->authorize('view', $onesiBox);
        $this->onesiBox = $onesiBox->load('recipient');
    }

    /**
     * Get the recipient associated with the OnesiBox.
     */
    #[Computed]
    public function recipient(): ?Recipient
    {
        return $this->onesiBox->recipient;
    }

    /**
     * Get the permission level of the current user.
     */
    #[Computed]
    public function permission(): ?OnesiBoxPermission
    {
        /** @var User $user */
        $user = auth()->user();

        $pivot = $this->onesiBox->caregivers()
            ->where('user_id', $user->id)
            ->first()
            ?->pivot;

        if ($pivot === null) {
            return null;
        }

        /** @var OnesiBoxPermission|null $permission */
        $permission = $pivot->getAttribute('permission');

        return $permission;
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
     * Check if the current user is an admin.
     */
    #[Computed]
    public function isAdmin(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->hasAnyRoles(Roles::SuperAdmin, Roles::Admin);
    }

    /**
     * Get current media information if playing.
     *
     * @return array{url: string|null, type: string|null, title: string|null}|null
     */
    #[Computed]
    public function currentMediaInfo(): ?array
    {
        if ($this->onesiBox->current_media_url === null) {
            return null;
        }

        return [
            'url' => $this->onesiBox->current_media_url,
            'type' => $this->onesiBox->current_media_type,
            'title' => $this->onesiBox->current_media_title,
        ];
    }

    /**
     * Get current meeting information if in a call.
     *
     * @return array{meeting_id: string}|null
     */
    #[Computed]
    public function currentMeetingInfo(): ?array
    {
        if ($this->onesiBox->current_meeting_id === null) {
            return null;
        }

        return [
            'meeting_id' => $this->onesiBox->current_meeting_id,
        ];
    }

    /**
     * Get the current volume level.
     */
    #[Computed]
    public function currentVolume(): int
    {
        return $this->onesiBox->volume ?? 80;
    }

    /**
     * Handle real-time status update from Echo.
     *
     * @param  array<string, mixed>  $payload
     */
    #[On('echo-private:onesibox.{onesiBox.id},StatusUpdated')]
    public function refreshStatus(array $payload): void
    {
        $this->onesiBox->refresh();
    }

    /**
     * Navigate back to the OnesiBox list.
     */
    public function goBack(): void
    {
        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.dashboard.onesi-box-detail');
    }
}
