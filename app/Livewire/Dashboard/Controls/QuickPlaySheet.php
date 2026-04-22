<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Models\OnesiBox;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class QuickPlaySheet extends Component
{
    #[Locked]
    public OnesiBox $onesiBox;

    public bool $open = false;

    public ?string $tab = null;

    #[On('open-quick-play')]
    public function openSheet(?string $tab = null): void
    {
        $this->open = true;
        $this->tab = $tab;
    }

    public function selectTab(string $tab): void
    {
        $this->tab = $tab;
    }

    public function back(): void
    {
        $this->tab = null;
    }

    public function close(): void
    {
        $this->open = false;
        $this->tab = null;
    }

    public function render(): View
    {
        return view('livewire.dashboard.controls.quick-play-sheet');
    }
}
