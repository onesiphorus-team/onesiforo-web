<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Enums\Roles;
use App\Models\OnesiBox;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('I miei box')]
class OnesiBoxList extends Component
{
    /**
     * A family member who follows a single box goes straight to it: the list would only be an extra step.
     */
    public function mount(): void
    {
        /** @var User $user */
        $user = auth()->user();

        if ($user->hasAnyRoles(Roles::SuperAdmin, Roles::Admin)) {
            return;
        }

        $onesiBoxIds = $user->onesiBoxes()->limit(2)->pluck('onesi_boxes.id');

        if ($onesiBoxIds->count() === 1) {
            $this->redirect(route('dashboard.show', $onesiBoxIds->first()), navigate: true);
        }
    }

    /**
     * Get the OnesiBoxes assigned to the authenticated user.
     *
     * @return Collection<int, OnesiBox>
     */
    #[Computed]
    public function onesiBoxes(): Collection
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->onesiBoxes()
            ->with('recipient')
            ->orderBy('name')
            ->get();
    }

    /**
     * Navigate to the OnesiBox detail page.
     */
    public function selectOnesiBox(int $id): void
    {
        $this->redirect(route('dashboard.show', $id), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.dashboard.onesi-box-list');
    }
}
