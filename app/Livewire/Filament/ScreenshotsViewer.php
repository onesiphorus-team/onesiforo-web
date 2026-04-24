<?php

declare(strict_types=1);

namespace App\Livewire\Filament;

use App\Models\ApplianceScreenshot;
use App\Models\OnesiBox;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * @property-read Collection<int, ApplianceScreenshot> $screenshots
 * @property-read Collection<int, ApplianceScreenshot> $top10
 * @property-read Collection<int, ApplianceScreenshot> $hourlyBeyondTop10
 */
class ScreenshotsViewer extends Component
{
    public OnesiBox $record;

    public ?int $selectedId = null;

    #[Validate('boolean')]
    public bool $enabled = true;

    #[Validate(['integer', 'between:10,3600'])]
    public int $intervalSeconds = 60;

    public function mount(OnesiBox $record): void
    {
        $this->record = $record;
        $this->enabled = $record->screenshot_enabled;
        $this->intervalSeconds = $record->screenshot_interval_seconds;
    }

    /**
     * @return Collection<int, ApplianceScreenshot>
     */
    #[Computed]
    public function screenshots(): Collection
    {
        return $this->record->screenshots()
            ->orderByDesc('captured_at')
            ->get();
    }

    /**
     * @return Collection<int, ApplianceScreenshot>
     */
    #[Computed]
    public function top10(): Collection
    {
        return $this->screenshots->take(10)->values();
    }

    /**
     * @return Collection<int, ApplianceScreenshot>
     */
    #[Computed]
    public function hourlyBeyondTop10(): Collection
    {
        return $this->screenshots->slice(10)->values();
    }

    public function select(int $id): void
    {
        $this->selectedId = $id;
    }

    public function toggle(): void
    {
        Gate::authorize('update', $this->record);

        $this->record->update(['screenshot_enabled' => ! $this->record->screenshot_enabled]);
        $this->record->refresh();
        $this->enabled = $this->record->screenshot_enabled;
    }

    public function saveInterval(): void
    {
        Gate::authorize('update', $this->record);

        $this->validateOnly('intervalSeconds');
        $this->record->update(['screenshot_interval_seconds' => $this->intervalSeconds]);
        $this->record->refresh();
    }

    #[On('echo-private:onesibox.{record.id},ApplianceScreenshotReceived')]
    public function onNewScreenshot(): void
    {
        unset($this->screenshots);
        unset($this->top10);
        unset($this->hourlyBeyondTop10);
    }

    public function render(): View
    {
        return view('livewire.filament.screenshots-viewer');
    }
}
