<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Models\OnesiBox;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class QuickPlaySheet extends Component
{
    use AuthorizesRequests;

    private const VALID_TABS = ['audio', 'video', 'stream', 'zoom', 'playlists', 'session'];

    #[Locked]
    public OnesiBox $onesiBox;

    public bool $open = false;

    public ?string $tab = null;

    #[On('open-quick-play')]
    public function openSheet(?string $tab = null): void
    {
        $this->authorize('control', $this->onesiBox);

        $this->open = true;
        $this->tab = ($tab !== null && in_array($tab, self::VALID_TABS, true)) ? $tab : null;
    }

    public function selectTab(string $tab): void
    {
        if (! in_array($tab, self::VALID_TABS, true)) {
            return;
        }

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
