<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\OnesiBox;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Dashboard')]
class OnesiBoxList extends Component
{
    /**
     * Get the OnesiBoxes assigned to the authenticated user.
     *
     * @return Collection<int, OnesiBox>
     */
    #[Computed]
    public function onesiBoxes(): Collection
    {
        /** @var \App\Models\User $user */
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
