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
 * @property-read Collection<int, ApplianceScreenshot> $top10
 * @property-read Collection<int, ApplianceScreenshot> $recent24h
 * @property-read SupportCollection<int, ApplianceScreenshot> $hourlyBeyondTop10
 */
class ScreenshotsViewer extends Component
{
    public const DEFAULT_DISPLAY_TIMEZONE = 'Europe/Rome';

    public OnesiBox $record;

    public ?int $selectedId = null;

    #[Validate('boolean')]
    public bool $enabled = true;

    #[Validate(['integer', 'between:10,3600'])]
    public int $intervalSeconds = 60;

    public static function displayTimezone(): string
    {
        return config('app.display_timezone', self::DEFAULT_DISPLAY_TIMEZONE);
    }

    public function mount(OnesiBox $record): void
    {
        $this->record = $record;
        $this->enabled = $record->screenshot_enabled;
        $this->intervalSeconds = $record->screenshot_interval_seconds;
    }

    /**
     * Most recent 10 screenshots regardless of age — supports the realtime strip
     * even when the box has been offline for more than 24h.
     *
     * @return Collection<int, ApplianceScreenshot>
     */
    #[Computed]
    public function top10(): Collection
    {
        return $this->record->screenshots()
            ->orderByDesc('captured_at')
            ->limit(10)
            ->get();
    }

    /**
     * @return Collection<int, ApplianceScreenshot>
     */
    #[Computed]
    public function recent24h(): Collection
    {
        return $this->record->screenshots()
            ->where('captured_at', '>=', now()->subDay())
            ->orderByDesc('captured_at')
            ->get();
    }

    /**
     * One representative screenshot per local hour for the last 24h, excluding
     * hours already covered by top10. The exclusion avoids visually duplicating
     * the same hour in both strips; the trade-off is that a box producing 10
     * captures inside a single minute will hide the rest of that hour from the
     * hourly view.
     *
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

        return $this->recent24h
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
        unset($this->top10);
        unset($this->recent24h);
        unset($this->hourlyBeyondTop10);
    }

    public function render(): View
    {
        return view('livewire.filament.screenshots-viewer', [
            'displayTimezone' => self::displayTimezone(),
        ]);
    }

    private function localHourKey(ApplianceScreenshot $screenshot): string
    {
        return $screenshot->captured_at
            ->copy()
            ->setTimezone(self::displayTimezone())
            ->format('Y-m-d H');
    }
}
