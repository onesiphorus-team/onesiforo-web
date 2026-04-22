<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Concerns\ChecksOnesiBoxPermission;
use App\Models\OnesiBox;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class BottomBar extends Component
{
    use ChecksOnesiBoxPermission;

    #[Locked]
    public OnesiBox $onesiBox;

    #[Computed]
    public function isOnline(): bool
    {
        return $this->onesiBox->isOnline();
    }

    public function render(): View
    {
        return view('livewire.dashboard.controls.bottom-bar');
    }
}
