<?php

declare(strict_types=1);

namespace App\Livewire\Filament;

use App\Models\ApplianceScreenshot;
use App\Models\OnesiBox;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * @property-read Collection<int, ApplianceScreenshot> $screenshots
 * @property-read Collection<int, ApplianceScreenshot> $top10
 * @property-read SupportCollection<int, ApplianceScreenshot> $hourlyBeyondTop10
 */
class ScreenshotsViewer extends Component
{
    public const DISPLAY_TIMEZONE = 'Europe/Rome';

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
     * @return SupportCollection<int, ApplianceScreenshot>
     */
    #[Computed]
    public function hourlyBeyondTop10(): SupportCollection
    {
        $top10 = $this->top10;
        $top10Ids = $top10->pluck('id')->all();
        $top10HourBuckets = $top10
            ->map(fn (ApplianceScreenshot $s): string => $this->localHourKey($s))
            ->unique()
            ->all();

        $cutoff = now()->subDay();

        return $this->screenshots
            ->filter(fn (ApplianceScreenshot $s): bool => $s->captured_at->gte($cutoff))
            ->reject(fn (ApplianceScreenshot $s): bool => in_array($s->id, $top10Ids, true))
            ->reject(fn (ApplianceScreenshot $s): bool => in_array($this->localHourKey($s), $top10HourBuckets, true))
            ->groupBy(fn (ApplianceScreenshot $s): string => $this->localHourKey($s))
            ->map(fn (SupportCollection $bucket): ApplianceScreenshot => $bucket->first())
            ->sortByDesc(fn (ApplianceScreenshot $s): int => $s->captured_at->getTimestamp())
            ->values();
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

    private function localHourKey(ApplianceScreenshot $screenshot): string
    {
        return $screenshot->captured_at
            ->copy()
            ->setTimezone(self::DISPLAY_TIMEZONE)
            ->format('Y-m-d H');
    }
}
