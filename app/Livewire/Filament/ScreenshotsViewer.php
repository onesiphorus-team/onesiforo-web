<?php

declare(strict_types=1);

namespace App\Livewire\Filament;

use App\Models\OnesiBox;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

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
        $this->record          = $record;
        $this->enabled         = $record->screenshot_enabled;
        $this->intervalSeconds = $record->screenshot_interval_seconds;
    }

    #[Computed]
    public function screenshots(): Collection
    {
        return $this->record->screenshots()
            ->orderByDesc('captured_at')
            ->get();
    }

    #[Computed]
    public function top10(): Collection
    {
        return $this->screenshots->take(10)->values();
    }

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
        $this->record->update(['screenshot_enabled' => ! $this->record->screenshot_enabled]);
        $this->record->refresh();
        $this->enabled = $this->record->screenshot_enabled;
    }

    public function saveInterval(): void
    {
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

    public function render()
    {
        return view('livewire.filament.screenshots-viewer');
    }
}
