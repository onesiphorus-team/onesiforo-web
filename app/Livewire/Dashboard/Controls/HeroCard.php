<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Models\OnesiBox;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class HeroCard extends Component
{
    #[Locked]
    public OnesiBox $onesiBox;

    /** @var 'offline'|'call'|'media'|'idle' */
    #[Locked]
    public string $state = 'idle';

    public bool $isPaused = false;

    public function render(): View
    {
        return view('livewire.dashboard.controls.hero-card');
    }
}
