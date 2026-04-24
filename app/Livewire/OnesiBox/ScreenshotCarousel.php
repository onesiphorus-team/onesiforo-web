<?php

declare(strict_types=1);

namespace App\Livewire\OnesiBox;

use App\Models\OnesiBox;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class ScreenshotCarousel extends Component
{
    public OnesiBox $box;
    public string $variant = 'full';
    public int $limit = 10;

    #[Computed]
    public function screenshots(): Collection
    {
        return $this->box->screenshots()
            ->orderByDesc('captured_at')
            ->limit($this->limit)
            ->get();
    }

    #[On('echo-private:onesibox.{box.id},ApplianceScreenshotReceived')]
    public function refresh(): void
    {
        unset($this->screenshots);
    }

    public function render()
    {
        return view('livewire.onesi-box.screenshot-carousel');
    }
}
