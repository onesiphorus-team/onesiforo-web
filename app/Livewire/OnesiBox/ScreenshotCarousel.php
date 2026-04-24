<?php

declare(strict_types=1);

namespace App\Livewire\OnesiBox;

use App\Models\ApplianceScreenshot;
use App\Models\OnesiBox;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * @property-read Collection<int, ApplianceScreenshot> $screenshots
 */
class ScreenshotCarousel extends Component
{
    public OnesiBox $box;

    public string $variant = 'full';

    public int $limit = 10;

    /**
     * @return Collection<int, ApplianceScreenshot>
     */
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

    public function render(): View
    {
        return view('livewire.onesi-box.screenshot-carousel');
    }
}
